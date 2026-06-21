<?php
// ====================================================================
// HOTELKU_API - Bookings (Reservasi, Check-in, Check-out)
// Role: admin/resepsionis → full, tamu → lihat booking sendiri
// Auth: Authorization: Bearer <token>
// ====================================================================

require_once '../config/database.php';
require_once '../helpers/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

$user = verify_token();

switch ($method) {

    case 'GET':
        if ($user['role'] === 'tamu') {
            $stmt = $pdo->prepare("
                SELECT r.id_reservasi, t.nama AS tamu, sr.status, r.tanggal_pesan,
                       dr.tanggal_checkin, dr.tanggal_checkout, dr.harga,
                       k.nomor_kamar, tk.nama_tipe
                FROM reservasi r
                JOIN tamu t ON r.tamu_id = t.id_tamu
                JOIN status_reservasi sr ON r.status_reservasi_id = sr.id_status_reservasi
                JOIN detail_reservasi dr ON dr.reservasi_id = r.id_reservasi
                JOIN kamar k ON dr.kamar_id = k.id_kamar
                JOIN tipe_kamar tk ON k.tipe_id = tk.id_tipe
                WHERE t.user_id = :uid
                ORDER BY r.tanggal_pesan DESC
            ");
            $stmt->execute(['uid' => $user['user_id']]);
        } elseif ($id) {
            $stmt = $pdo->prepare("
                SELECT r.id_reservasi, t.nama AS tamu, t.email, t.telepon,
                       sr.status, r.tanggal_pesan,
                       dr.tanggal_checkin, dr.tanggal_checkout, dr.harga,
                       k.nomor_kamar, tk.nama_tipe,
                       p.total AS total_bayar, p.tanggal_bayar, mp.nama_metode
                FROM reservasi r
                JOIN tamu t ON r.tamu_id = t.id_tamu
                JOIN status_reservasi sr ON r.status_reservasi_id = sr.id_status_reservasi
                JOIN detail_reservasi dr ON dr.reservasi_id = r.id_reservasi
                JOIN kamar k ON dr.kamar_id = k.id_kamar
                JOIN tipe_kamar tk ON k.tipe_id = tk.id_tipe
                LEFT JOIN pembayaran p ON r.id_reservasi = p.reservasi_id
                LEFT JOIN metode_pembayaran mp ON p.metode_id = mp.id_metode
                WHERE r.id_reservasi = :id
            ");
            $stmt->execute(['id' => $id]);
        } else {
            $stmt = $pdo->query("
                SELECT r.id_reservasi, t.nama AS tamu, sr.status, r.tanggal_pesan,
                       dr.tanggal_checkin, dr.tanggal_checkout, dr.harga,
                       k.nomor_kamar, tk.nama_tipe
                FROM reservasi r
                JOIN tamu t ON r.tamu_id = t.id_tamu
                JOIN status_reservasi sr ON r.status_reservasi_id = sr.id_status_reservasi
                JOIN detail_reservasi dr ON dr.reservasi_id = r.id_reservasi
                JOIN kamar k ON dr.kamar_id = k.id_kamar
                JOIN tipe_kamar tk ON k.tipe_id = tk.id_tipe
                ORDER BY r.tanggal_pesan DESC
            ");
        }

        $data = $stmt->fetchAll();
        json_response(['status' => 'success', 'data' => $data]);
        break;

    case 'POST':
        $input = get_json_input();
        validate_required($input, ['tamu_id', 'kamar_id']);

        if ($user['role'] === 'tamu') {
            $stmt = $pdo->prepare("SELECT id_tamu FROM tamu WHERE user_id = :uid");
            $stmt->execute(['uid' => $user['user_id']]);
            $my_tamu_id = $stmt->fetchColumn();
            if ((int)$input['tamu_id'] !== (int)$my_tamu_id) {
                json_error('Anda hanya bisa booking untuk diri sendiri', 403);
            }
        }

        $checkin = $input['tanggal_checkin'] ?? date('Y-m-d');
        $checkout = $input['tanggal_checkout'] ?? date('Y-m-d', strtotime('+1 day'));
        $promo_id = $input['promo_id'] ?? null;

        // Cek ketersediaan kamar
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM detail_reservasi dr
            JOIN reservasi r ON dr.reservasi_id = r.id_reservasi
            WHERE dr.kamar_id = :kamar_id
              AND r.status_reservasi_id IN (1, 2)
              AND dr.tanggal_checkin < :checkout
              AND dr.tanggal_checkout > :checkin
        ");
        $stmt->execute([
            'kamar_id' => $input['kamar_id'],
            'checkin' => $checkin,
            'checkout' => $checkout
        ]);

        if ($stmt->fetchColumn() > 0) {
            json_error('Kamar sudah dibooking di tanggal tersebut');
        }

        // Ambil harga
        $stmt = $pdo->prepare("
            SELECT tk.harga FROM kamar k
            JOIN tipe_kamar tk ON k.tipe_id = tk.id_tipe
            WHERE k.id_kamar = :id
        ");
        $stmt->execute(['id' => $input['kamar_id']]);
        $harga_data = $stmt->fetch();
        $harga = $harga_data ? $harga_data['harga'] : 0;

        // Cari status 'Baru'
        $stmt = $pdo->query("SELECT id_status_reservasi FROM status_reservasi WHERE status = 'Baru' LIMIT 1");
        $status_baru = $stmt->fetchColumn();

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO reservasi (tamu_id, status_reservasi_id, promo_id) VALUES (:tamu_id, :status_id, :promo_id)");
            $stmt->execute([
                'tamu_id' => $input['tamu_id'],
                'status_id' => $status_baru,
                'promo_id' => $promo_id
            ]);
            $reservasi_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO detail_reservasi (reservasi_id, kamar_id, tanggal_checkin, tanggal_checkout, harga)
                VALUES (:reservasi_id, :kamar_id, :checkin, :checkout, :harga)
            ");
            $stmt->execute([
                'reservasi_id' => $reservasi_id,
                'kamar_id' => $input['kamar_id'],
                'checkin' => $checkin,
                'checkout' => $checkout,
                'harga' => $harga
            ]);

            $pdo->commit();

            $stmt = $pdo->prepare("INSERT INTO audit_log (aktivitas, user_id, ip_address) VALUES (:akt, :uid, :ip)");
            $stmt->execute([
                'akt' => 'Buat reservasi #' . $reservasi_id . ' untuk tamu ID ' . $input['tamu_id'],
                'uid' => $user['user_id'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);

            json_response(['status' => 'success', 'message' => 'Reservasi berhasil', 'id_reservasi' => $reservasi_id], 201);
        } catch (Exception $e) {
            $pdo->rollBack();
            json_error('Reservasi gagal: ' . $e->getMessage(), 500);
        }
        break;

    case 'PUT':
        if (!$id) json_error('ID reservasi diperlukan');
        $input = get_json_input();
        $action = $input['action'] ?? '';

        if ($action === 'cancel') {
            $stmt = $pdo->prepare("SELECT r.id_reservasi, r.tamu_id, r.status_reservasi_id, sr.status FROM reservasi r JOIN status_reservasi sr ON r.status_reservasi_id = sr.id_status_reservasi WHERE r.id_reservasi = :id");
            $stmt->execute(['id' => $id]);
            $booking = $stmt->fetch();

            if (!$booking) json_error('Reservasi tidak ditemukan', 404);

            if ($user['role'] === 'tamu') {
                $stmt = $pdo->prepare("SELECT id_tamu FROM tamu WHERE user_id = :uid");
                $stmt->execute(['uid' => $user['user_id']]);
                $tamu_id = $stmt->fetchColumn();
                if ((int)$booking['tamu_id'] !== (int)$tamu_id) {
                    json_error('Akses ditolak. Anda hanya bisa membatalkan booking sendiri', 403);
                }
            }

            if ($booking['status'] !== 'Baru') {
                json_error('Hanya booking dengan status Baru yang dapat dibatalkan');
            }

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->query("SELECT id_status_reservasi FROM status_reservasi WHERE status = 'Dibatalkan' LIMIT 1");
                $status_batal = $stmt->fetchColumn();

                $stmt = $pdo->prepare("UPDATE reservasi SET status_reservasi_id = :status_id WHERE id_reservasi = :id");
                $stmt->execute(['status_id' => $status_batal, 'id' => $id]);

                $stmt = $pdo->prepare("SELECT id_pembayaran, total FROM pembayaran WHERE reservasi_id = :rid");
                $stmt->execute(['rid' => $id]);
                $payment = $stmt->fetch();

                $log_msg = 'Pembatalan reservasi #' . $id;
                if ($payment) {
                    $log_msg .= ' (refund Rp ' . number_format((float)$payment['total'], 0, ',', '.') . ')';
                }

                $stmt = $pdo->prepare("INSERT INTO audit_log (aktivitas, user_id, ip_address) VALUES (:akt, :uid, :ip)");
                $stmt->execute([
                    'akt' => $log_msg,
                    'uid' => $user['user_id'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                ]);

                $pdo->commit();
                json_response(['status' => 'success', 'message' => 'Booking berhasil dibatalkan' . ($payment ? ' (refund akan diproses)' : '')]);
            } catch (Exception $e) {
                $pdo->rollBack();
                json_error('Pembatalan gagal', 500);
            }
        } else {
            if ($user['role'] === 'tamu') {
                json_error('Akses ditolak. Hanya admin/resepsionis', 403);
            }

            if ($action === 'checkin') {
                $stmt = $pdo->query("SELECT id_status_reservasi FROM status_reservasi WHERE status = 'Check-in' LIMIT 1");
                $status_checkin = $stmt->fetchColumn();

                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("UPDATE reservasi SET status_reservasi_id = :status_id WHERE id_reservasi = :id");
                    $stmt->execute(['status_id' => $status_checkin, 'id' => $id]);

                    $pegawai_id = $input['pegawai_id'] ?? 1;
                    $stmt = $pdo->prepare("INSERT INTO checkin (reservasi_id, pegawai_id) VALUES (:reservasi_id, :pegawai_id)");
                    $stmt->execute(['reservasi_id' => $id, 'pegawai_id' => $pegawai_id]);

                    $pdo->commit();

                    $stmt = $pdo->prepare("INSERT INTO audit_log (aktivitas, user_id, ip_address) VALUES (:akt, :uid, :ip)");
                    $stmt->execute([
                        'akt' => 'Check-in reservasi #' . $id,
                        'uid' => $user['user_id'],
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                    ]);

                    json_response(['status' => 'success', 'message' => 'Check-in berhasil']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    json_error('Check-in gagal', 500);
                }
            } elseif ($action === 'checkout') {
                $stmt = $pdo->query("SELECT id_status_reservasi FROM status_reservasi WHERE status = 'Check-out' LIMIT 1");
                $status_checkout = $stmt->fetchColumn();

                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("UPDATE reservasi SET status_reservasi_id = :status_id WHERE id_reservasi = :id");
                    $stmt->execute(['status_id' => $status_checkout, 'id' => $id]);

                    $pegawai_id = $input['pegawai_id'] ?? 1;
                    $stmt = $pdo->prepare("INSERT INTO checkout (reservasi_id, pegawai_id) VALUES (:reservasi_id, :pegawai_id)");
                    $stmt->execute(['reservasi_id' => $id, 'pegawai_id' => $pegawai_id]);

                    $pdo->commit();

                    $stmt = $pdo->prepare("INSERT INTO audit_log (aktivitas, user_id, ip_address) VALUES (:akt, :uid, :ip)");
                    $stmt->execute([
                        'akt' => 'Check-out reservasi #' . $id,
                        'uid' => $user['user_id'],
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                    ]);

                    json_response(['status' => 'success', 'message' => 'Check-out berhasil']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    json_error('Check-out gagal', 500);
                }
            } else {
                json_error('Action harus checkin, checkout, atau cancel');
            }
        }
        break;

    default:
        json_error('Method tidak diizinkan', 405);
}
