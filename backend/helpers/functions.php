<?php

define('AUTH_SECRET', 'Hotelku_DB_Rahasia2026');
define('TOKEN_EXPIRY', 86400);

function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function json_error($message, $status = 400) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

function get_json_input() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

function validate_required($data, $fields) {
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            json_error("Field '$field' wajib diisi");
        }
    }
}

function generate_token($user) {
    global $pdo;
    $payload = base64_encode(json_encode([
        'user_id' => (int)$user['id_user'],
        'username' => $user['username'],
        'role' => $user['nama_role'],
        'exp' => time() + TOKEN_EXPIRY
    ]));
    $signature = hash_hmac('sha256', $payload, AUTH_SECRET);
    $token = $payload . '.' . $signature;
    $stmt = $pdo->prepare("INSERT INTO audit_log (aktivitas, user_id, ip_address) VALUES (:akt, :uid, :ip)");
    $stmt->execute([
        'akt' => 'Login ' . $user['username'] . ' sebagai ' . $user['nama_role'],
        'uid' => $user['id_user'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);
    return $token;
}

function get_token_from_header() {
    $headers = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $headers = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $req_headers = apache_request_headers();
        $headers = $req_headers['Authorization'] ?? '';
    }
    if (preg_match('/Bearer\s+(.+)/', $headers, $matches)) {
        return $matches[1];
    }
    return null;
}

function verify_token() {
    $token = get_token_from_header();
    if (!$token) {
        json_error('Token tidak ditemukan. Login dulu!', 401);
    }
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        json_error('Token tidak valid', 401);
    }
    $payload = $parts[0];
    $signature = $parts[1];
    $expected = hash_hmac('sha256', $payload, AUTH_SECRET);
    if (!hash_equals($expected, $signature)) {
        json_error('Token tidak valid atau sudah expired', 401);
    }
    $data = json_decode(base64_decode($payload), true);
    if (!$data || !isset($data['exp']) || $data['exp'] < time()) {
        json_error('Token sudah expired. Login ulang', 401);
    }
    return $data;
}

function require_role($allowed_roles) {
    $user = verify_token();
    if (!in_array($user['role'], $allowed_roles)) {
        json_error('Akses ditolak. Role "' . $user['role'] . '" tidak memiliki izin', 403);
    }
    return $user;
}
