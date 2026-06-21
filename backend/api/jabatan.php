<?php
require_once '../config/database.php';
require_once '../helpers/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$user = verify_token();
if ($user['role'] !== 'admin') {
    json_error('Akses ditolak', 403);
}

$stmt = $pdo->query("SELECT id_jabatan, nama_jabatan FROM jabatan ORDER BY id_jabatan");
json_response(['status' => 'success', 'data' => $stmt->fetchAll()]);
