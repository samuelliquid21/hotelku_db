<?php

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

switch ($method) {

    case 'GET':
        if ($id) {
            $stmt = $pdo->prepare("
                SELECT k.id_kamar, k.nomor_kamar, tk.nama_tipe, tk.harga, tk.kapasitas,
                       sk.status AS status_kamar, k.foto
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
                SELECT k.id_kamar, k.nomor_kamar, tk.nama_tipe, tk.harga, sk.status AS status_kamar, k.foto
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
        $user = require_role(['admin']);

        if (!empty($_FILES['foto'])) {
            $nomor_kamar = $_POST['nomor_kamar'] ?? '';
            $tipe_id = $_POST['tipe_id'] ?? '';
            $status_id = $_POST['status_id'] ?? 1;
            if (empty($nomor_kamar) || empty($tipe_id)) json_error('Field nomor_kamar dan tipe_id wajib diisi');

            $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $filename = 'room_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $dest = __DIR__ . '/../../frontend/uploads/rooms/' . $filename;
            move_uploaded_file($_FILES['foto']['tmp_name'], $dest);

            $foto = 'uploads/rooms/' . $filename;
        } else {
            $input = get_json_input();
            validate_required($input, ['nomor_kamar', 'tipe_id']);
            $nomor_kamar = $input['nomor_kamar'];
            $tipe_id = $input['tipe_id'];
            $status_id = $input['status_id'] ?? 1;
            $foto = $input['foto'] ?? null;
        }

        $stmt = $pdo->prepare("
            INSERT INTO kamar (nomor_kamar, tipe_id, status_id, foto)
            VALUES (:nomor_kamar, :tipe_id, :status_id, :foto)
        ");
        $stmt->execute([
            'nomor_kamar' => $nomor_kamar,
            'tipe_id' => $tipe_id,
            'status_id' => $status_id,
            'foto' => $foto
        ]);

        $inserted_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO audit_log (aktivitas, user_id, ip_address) VALUES (:akt, :uid, :ip)");
        $stmt->execute([
            'akt' => 'Tambah kamar ' . $nomor_kamar,
            'uid' => $user['user_id'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);

        json_response([
            'status' => 'success',
            'message' => 'Kamar berhasil ditambahkan',
            'id_kamar' => $inserted_id,
            'foto' => $foto
        ], 201);
        break;

    case 'PUT':
        $user = require_role(['admin']);
        if (!$id) json_error('ID kamar diperlukan');

        $fields = [];
        $params = ['id' => $id];

        if (!empty($_FILES['foto'])) {
            $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $filename = 'room_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $dest = __DIR__ . '/../../frontend/uploads/rooms/' . $filename;
            move_uploaded_file($_FILES['foto']['tmp_name'], $dest);
            $fields[] = 'foto = :foto';
            $params['foto'] = 'uploads/rooms/' . $filename;
        }

        $input = get_json_input();

        if (isset($input['nomor_kamar'])) { $fields[] = 'nomor_kamar = :nomor_kamar'; $params['nomor_kamar'] = $input['nomor_kamar']; }
        if (isset($input['tipe_id'])) { $fields[] = 'tipe_id = :tipe_id'; $params['tipe_id'] = $input['tipe_id']; }
        if (isset($input['status_id'])) { $fields[] = 'status_id = :status_id'; $params['status_id'] = $input['status_id']; }
        if (isset($input['foto'])) { $fields[] = 'foto = :foto'; $params['foto'] = $input['foto']; }

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
        $user = require_role(['admin']);
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
