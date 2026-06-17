<?php
// ====================================================================
// HOTELKU_API - Payments (Pembayaran & Invoice)
// Role: admin/resepsionis → full, tamu → lihat pembayaran sendiri
// Auth: Authorization: Bearer <token>
// ====================================================================

require_once '../config/database.php';
require_once '../helpers/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$user = verify_token();

switch ($method) {

    case 'GET':
        $id = $_GET['id'] ?? null;

        if ($user['role'] === 'tamu') {
            $stmt = $pdo->prepare("
                SELECT p.id_pembayaran, p.reservasi_id, p.total, p.tanggal_bayar,
                       mp.nama_metode, i.nomor_invoice
                FROM pembayaran p
                JOIN reservasi r ON p.reservasi_id = r.id_reservasi
                JOIN tamu t ON r.tamu_id = t.id_tamu
                JOIN metode_pembayaran mp ON p.metode_id = mp.id_metode
                LEFT JOIN invoice i ON p.reservasi_id = i.reservasi_id
                WHERE t.user_id = :uid
                ORDER BY p.tanggal_bayar DESC
            ");
            $stmt->execute(['uid' => $user['user_id']]);
        } elseif ($id) {
            $stmt = $pdo->prepare("
                SELECT p.id_pembayaran, p.reservasi_id, p.total, p.tanggal_bayar,
                       mp.nama_metode, i.nomor_invoice
                FROM pembayaran p
                JOIN metode_pembayaran mp ON p.metode_id = mp.id_metode
                LEFT JOIN invoice i ON p.reservasi_id = i.reservasi_id
                WHERE p.id_pembayaran = :id
            ");
            $stmt->execute(['id' => $id]);
        } else {
            $stmt = $pdo->query("
                SELECT p.id_pembayaran, p.reservasi_id, p.total, p.tanggal_bayar,
                       mp.nama_metode, i.nomor_invoice
                FROM pembayaran p
                JOIN metode_pembayaran mp ON p.metode_id = mp.id_metode
                LEFT JOIN invoice i ON p.reservasi_id = i.reservasi_id
                ORDER BY p.tanggal_bayar DESC
            ");
        }

        $data = $stmt->fetchAll();
        json_response(['status' => 'success', 'data' => $data]);
        break;

    case 'POST':
        if ($user['role'] === 'tamu') {
            json_error('Akses ditolak. Hanya admin/resepsionis', 403);
        }

        $input = get_json_input();
        validate_required($input, ['reservasi_id', 'metode_id', 'total']);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservasi WHERE id_reservasi = :id");
        $stmt->execute(['id' => $input['reservasi_id']]);
        if (!$stmt->fetchColumn()) {
            json_error('Reservasi tidak ditemukan');
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO pembayaran (reservasi_id, metode_id, total) VALUES (:reservasi_id, :metode_id, :total)");
            $stmt->execute([
                'reservasi_id' => $input['reservasi_id'],
                'metode_id' => $input['metode_id'],
                'total' => $input['total']
            ]);

            $nomor_invoice = 'INV-' . date('Ym') . '-' . str_pad($input['reservasi_id'], 3, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("INSERT INTO invoice (reservasi_id, nomor_invoice) VALUES (:reservasi_id, :nomor_invoice)");
            $stmt->execute([
                'reservasi_id' => $input['reservasi_id'],
                'nomor_invoice' => $nomor_invoice
            ]);

            $pdo->commit();

            $stmt = $pdo->prepare("INSERT INTO audit_log (aktivitas, user_id, ip_address) VALUES (:akt, :uid, :ip)");
            $stmt->execute([
                'akt' => 'Pembayaran reservasi #' . $input['reservasi_id'] . ' - Rp' . number_format($input['total'], 0, ',', '.'),
                'uid' => $user['user_id'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);

            json_response([
                'status' => 'success',
                'message' => 'Pembayaran berhasil',
                'nomor_invoice' => $nomor_invoice
            ], 201);
        } catch (Exception $e) {
            $pdo->rollBack();
            json_error('Pembayaran gagal: ' . $e->getMessage(), 500);
        }
        break;

    default:
        json_error('Method tidak diizinkan', 405);
}
