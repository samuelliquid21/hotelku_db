<?php
// ====================================================================
// HOTELKU_API - Rooms (Kamar, Tipe Kamar, Fasilitas)
// Role: admin → full, resepsionis → GET only, tamu → NO akses
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
$id = $_GET['id'] ?? null;

// Auth check
$user = verify_token();

switch ($method) {

    case 'GET':
        if ($user['role'] === 'tamu') {
            json_error('Akses ditolak. Hanya admin/resepsionis', 403);
        }

        if ($id) {
            $stmt = $pdo->prepare("
                SELECT k.id_kamar, k.nomor_kamar, tk.nama_tipe, tk.harga, tk.kapasitas,
                       sk.status AS status_kamar
                FROM kamar k
                JOIN tipe_kamar tk ON k.tipe_id = tk.id_tipe
                JOIN status_kamar sk ON k.status_id = sk.id_status
                WHERE k.id_kamar = :id
            ");
            $stmt->execute(['id' => $id]);
            $kamar = $stmt->fetch();

            if (!$kamar) {
                json_error('Kamar tidak ditemukan', 404);
            }

            $stmt = $pdo->prepare("
                SELECT f.id_fasilitas, f.nama_fasilitas
                FROM kamar_fasilitas kf
                JOIN fasilitas f ON kf.fasilitas_id = f.id_fasilitas
                WHERE kf.kamar_id = :id
            ");
            $stmt->execute(['id' => $id]);
            $kamar['fasilitas'] = $stmt->fetchAll();

            json_response(['status' => 'success', 'data' => $kamar]);
        } else {
            $stmt = $pdo->query("
                SELECT k.id_kamar, k.nomor_kamar, tk.nama_tipe, tk.harga, sk.status AS status_kamar
                FROM kamar k
                JOIN tipe_kamar tk ON k.tipe_id = tk.id_tipe
                JOIN status_kamar sk ON k.status_id = sk.id_status
                ORDER BY k.nomor_kamar
            ");
            $kamar = $stmt->fetchAll();

            foreach ($kamar as &$kmr) {
                $stmt = $pdo->prepare("
                    SELECT f.nama_fasilitas
                    FROM kamar_fasilitas kf
                    JOIN fasilitas f ON kf.fasilitas_id = f.id_fasilitas
                    WHERE kf.kamar_id = :id
                ");
                $stmt->execute(['id' => $kmr['id_kamar']]);
                $kmr['fasilitas'] = array_column($stmt->fetchAll(), 'nama_fasilitas');
            }

            json_response(['status' => 'success', 'data' => $kamar]);
        }
        break;

    case 'POST':
        if ($user['role'] !== 'admin') {
            json_error('Akses ditolak. Hanya admin boleh tambah kamar', 403);
        }

        $input = get_json_input();
        validate_required($input, ['nomor_kamar', 'tipe_id']);

        $stmt = $pdo->prepare("
            INSERT INTO kamar (nomor_kamar, tipe_id, status_id)
            VALUES (:nomor_kamar, :tipe_id, :status_id)
        ");
        $stmt->execute([
            'nomor_kamar' => $input['nomor_kamar'],
            'tipe_id' => $input['tipe_id'],
            'status_id' => $input['status_id'] ?? 1
        ]);

        $stmt = $pdo->prepare("INSERT INTO audit_log (aktivitas, user_id, ip_address) VALUES (:akt, :uid, :ip)");
        $stmt->execute([
            'akt' => 'Tambah kamar ' . $input['nomor_kamar'],
            'uid' => $user['user_id'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);

        json_response([
            'status' => 'success',
            'message' => 'Kamar berhasil ditambahkan',
            'id_kamar' => $pdo->lastInsertId()
        ], 201);
        break;

    case 'PUT':
        if ($user['role'] !== 'admin') {
            json_error('Akses ditolak. Hanya admin boleh edit kamar', 403);
        }
        if (!$id) json_error('ID kamar diperlukan');

        $input = get_json_input();

        $fields = [];
        $params = ['id' => $id];

        if (isset($input['nomor_kamar'])) { $fields[] = 'nomor_kamar = :nomor_kamar'; $params['nomor_kamar'] = $input['nomor_kamar']; }
        if (isset($input['tipe_id'])) { $fields[] = 'tipe_id = :tipe_id'; $params['tipe_id'] = $input['tipe_id']; }
        if (isset($input['status_id'])) { $fields[] = 'status_id = :status_id'; $params['status_id'] = $input['status_id']; }

        if (empty($fields)) json_error('Tidak ada data yang diupdate');

        $sql = "UPDATE kamar SET " . implode(', ', $fields) . " WHERE id_kamar = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $stmt = $pdo->prepare("INSERT INTO audit_log (aktivitas, user_id, ip_address) VALUES (:akt, :uid, :ip)");
        $stmt->execute([
            'akt' => 'Update kamar ID ' . $id,
            'uid' => $user['user_id'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);

        json_response(['status' => 'success', 'message' => 'Kamar berhasil diupdate']);
        break;

    case 'DELETE':
        if ($user['role'] !== 'admin') {
            json_error('Akses ditolak. Hanya admin boleh hapus kamar', 403);
        }
        if (!$id) json_error('ID kamar diperlukan');

        $stmt = $pdo->prepare("DELETE FROM kamar WHERE id_kamar = :id");
        $stmt->execute(['id' => $id]);

        $stmt = $pdo->prepare("INSERT INTO audit_log (aktivitas, user_id, ip_address) VALUES (:akt, :uid, :ip)");
        $stmt->execute([
            'akt' => 'Hapus kamar ID ' . $id,
            'uid' => $user['user_id'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);

        json_response(['status' => 'success', 'message' => 'Kamar berhasil dihapus']);
        break;

    default:
        json_error('Method tidak diizinkan', 405);
}
