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
            $stmt = $pdo->prepare("SELECT * FROM promo WHERE id_promo = :id");
            $stmt->execute(['id' => $id]);
            $promo = $stmt->fetch();
            if (!$promo) json_error('Promo tidak ditemukan', 404);
            json_response(['status' => 'success', 'data' => $promo]);
        } else {
            $stmt = $pdo->query("SELECT * FROM promo ORDER BY tanggal_mulai DESC");
            $promo = $stmt->fetchAll();
            json_response(['status' => 'success', 'data' => $promo]);
        }
        break;

    case 'POST':
        $user = require_role(['admin']);
        $input = get_json_input();
        validate_required($input, ['kode', 'diskon']);

        $stmt = $pdo->prepare("INSERT INTO promo (kode, diskon, tanggal_mulai, tanggal_selesai) VALUES (:kode, :diskon, :tgl_mulai, :tgl_selesai)");
        $stmt->execute([
            'kode' => strtoupper($input['kode']),
            'diskon' => $input['diskon'],
            'tgl_mulai' => $input['tanggal_mulai'] ?? null,
            'tgl_selesai' => $input['tanggal_selesai'] ?? null,
        ]);

        $stmt = $pdo->prepare("INSERT INTO audit_log (aktivitas, user_id, ip_address) VALUES (:akt, :uid, :ip)");
        $stmt->execute(['akt' => 'Tambah promo ' . $input['kode'], 'uid' => $user['user_id'], 'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);

        json_response(['status' => 'success', 'message' => 'Promo berhasil ditambahkan', 'id_promo' => $pdo->lastInsertId()], 201);
        break;

    case 'PUT':
        $user = require_role(['admin']);
        if (!$id) json_error('ID promo diperlukan');
        $input = get_json_input();

        $fields = [];
        $params = ['id' => $id];
        if (isset($input['kode'])) { $fields[] = 'kode = :kode'; $params['kode'] = strtoupper($input['kode']); }
        if (isset($input['diskon'])) { $fields[] = 'diskon = :diskon'; $params['diskon'] = $input['diskon']; }
        if (array_key_exists('tanggal_mulai', $input)) { $fields[] = 'tanggal_mulai = :tgl_mulai'; $params['tgl_mulai'] = $input['tanggal_mulai']; }
        if (array_key_exists('tanggal_selesai', $input)) { $fields[] = 'tanggal_selesai = :tgl_selesai'; $params['tgl_selesai'] = $input['tanggal_selesai']; }

        if (empty($fields)) json_error('Tidak ada data yang diupdate');
        $sql = "UPDATE promo SET " . implode(', ', $fields) . " WHERE id_promo = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $stmt = $pdo->prepare("INSERT INTO audit_log (aktivitas, user_id, ip_address) VALUES (:akt, :uid, :ip)");
        $stmt->execute(['akt' => 'Update promo ID ' . $id, 'uid' => $user['user_id'], 'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);

        json_response(['status' => 'success', 'message' => 'Promo berhasil diupdate']);
        break;

    case 'DELETE':
        require_role(['admin']);
        if (!$id) json_error('ID promo diperlukan');
        $stmt = $pdo->prepare("DELETE FROM promo WHERE id_promo = :id");
        $stmt->execute(['id' => $id]);
        json_response(['status' => 'success', 'message' => 'Promo berhasil dihapus']);
        break;

    default:
        json_error('Method tidak diizinkan', 405);
}
