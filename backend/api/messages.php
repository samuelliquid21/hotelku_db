<?php

require_once '../config/database.php';
require_once '../helpers/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

switch ($_SERVER['REQUEST_METHOD']) {

    case 'GET':
        $user = verify_token();
        $isAdmin = in_array($user['role'], ['admin', 'resepsionis']);

        if ($isAdmin && !isset($_GET['email'])) {
            $stmt = $pdo->query("
                SELECT id_pesan, nama, email, subjek, pesan, balasan, tipe, parent_id, is_read, created_at
                FROM pesan
                ORDER BY created_at DESC
            ");
        } else {
            if (!$isAdmin) {
                $stmt = $pdo->prepare("SELECT email FROM users WHERE id_user = :id");
                $stmt->execute(['id' => $user['user_id']]);
                $u = $stmt->fetch();
                $email = $u['email'] ?? $_GET['email'] ?? '';
            } else {
                $email = $_GET['email'] ?? '';
            }
            if (!$email) json_error('Email tidak ditemukan');

            $stmt = $pdo->prepare("
                SELECT id_pesan, nama, email, subjek, pesan, balasan, tipe, parent_id, is_read, created_at
                FROM pesan
                WHERE email = :email
                ORDER BY COALESCE(parent_id, id_pesan), created_at ASC
            ");
            $stmt->execute(['email' => $email]);
        }

        $rows = $stmt->fetchAll();
        $roots = [];
        $children = [];

        foreach ($rows as $r) {
            if ($r['parent_id'] === null) {
                $r['children'] = [];
                $roots[$r['id_pesan']] = $r;
            } else {
                $children[] = $r;
            }
        }

        foreach ($children as $c) {
            $pid = $c['parent_id'];
            if (isset($roots[$pid])) {
                $roots[$pid]['children'][] = $c;
            }
        }

        json_response(['status' => 'success', 'data' => array_values($roots)]);
        break;

    case 'POST':
        $input = get_json_input();
        validate_required($input, ['nama', 'email', 'pesan']);

        $tipe = $input['tipe'] ?? 'pesan';
        $parent_id = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;

        $stmt = $pdo->prepare("
            INSERT INTO pesan (nama, email, subjek, pesan, tipe, parent_id)
            VALUES (:nama, :email, :subjek, :pesan, :tipe, :parent_id)
        ");
        $stmt->execute([
            'nama' => $input['nama'],
            'email' => $input['email'],
            'subjek' => $input['subjek'] ?? '',
            'pesan' => $input['pesan'],
            'tipe' => $tipe,
            'parent_id' => $parent_id
        ]);

        if ($parent_id) {
            $pdo->prepare("UPDATE pesan SET is_read = 0 WHERE id_pesan = :id")
                ->execute(['id' => $parent_id]);
        }

        json_response(['status' => 'success', 'message' => 'Pesan berhasil dikirim'], 201);
        break;

    case 'PUT':
        $user = require_role(['admin', 'resepsionis']);

        $input = get_json_input();
        $id = $_GET['id'] ?? null;
        if (!$id) json_error('ID pesan diperlukan');

        if (!empty($input['balasan'])) {
            $stmt = $pdo->prepare("UPDATE pesan SET is_read = 1, balasan = :balasan WHERE id_pesan = :id");
            $stmt->execute(['balasan' => $input['balasan'], 'id' => $id]);

            $pdo->prepare("UPDATE pesan SET is_read = 1 WHERE parent_id = :pid")
                ->execute(['pid' => $id]);

            json_response(['status' => 'success', 'message' => 'Balasan terkirim']);
        } else {
            $stmt = $pdo->prepare("UPDATE pesan SET is_read = 1 WHERE id_pesan = :id");
            $stmt->execute(['id' => $id]);
            json_response(['status' => 'success']);
        }
        break;

    default:
        json_error('Method tidak diizinkan', 405);
}
