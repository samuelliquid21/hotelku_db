<?php
// ====================================================================
// HOTELKU_API - Auth (Login & Register)
// Endpoint: POST /api/auth.php?action=login
//           POST /api/auth.php?action=register
//
// Login returns token. Kirim token di header:
// Authorization: Bearer <token>
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

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method tidak diizinkan', 405);
}

$input = get_json_input();

switch ($action) {

    case 'login':
        validate_required($input, ['username', 'password']);

        $stmt = $pdo->prepare("
            SELECT u.id_user, u.username, u.email, u.password, u.role_id, r.nama_role
            FROM users u
            JOIN roles r ON u.role_id = r.id_role
            WHERE u.username = :username
        ");
        $stmt->execute(['username' => $input['username']]);
        $user = $stmt->fetch();

        if (!$user || $user['password'] !== $input['password']) {
            json_error('Username atau password salah', 401);
        }

        $token = generate_token($user);

        json_response([
            'status' => 'success',
            'data' => [
                'id_user' => $user['id_user'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['nama_role'],
                'token' => $token
            ]
        ]);
        break;

    case 'register':
        validate_required($input, ['username', 'email', 'password', 'nama', 'telepon']);

        $stmt = $pdo->prepare("SELECT id_user FROM users WHERE username = :username OR email = :email");
        $stmt->execute(['username' => $input['username'], 'email' => $input['email']]);
        if ($stmt->fetch()) {
            json_error('Username atau email sudah terdaftar');
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT id_role FROM roles WHERE nama_role = 'tamu'");
            $stmt->execute();
            $role = $stmt->fetch();

            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role_id) VALUES (:username, :email, :password, :role_id)");
            $stmt->execute([
                'username' => $input['username'],
                'email' => $input['email'],
                'password' => $input['password'],
                'role_id' => $role['id_role']
            ]);
            $user_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO tamu (nama, email, telepon, alamat, user_id) VALUES (:nama, :email, :telepon, :alamat, :user_id)");
            $stmt->execute([
                'nama' => $input['nama'],
                'email' => $input['email'],
                'telepon' => $input['telepon'],
                'alamat' => $input['alamat'] ?? '',
                'user_id' => $user_id
            ]);

            $pdo->commit();

            // Auto-login: generate token buat user baru
            $stmt = $pdo->prepare("SELECT u.id_user, u.username, u.email, u.role_id, r.nama_role FROM users u JOIN roles r ON u.role_id = r.id_role WHERE u.id_user = :id");
            $stmt->execute(['id' => $user_id]);
            $new_user = $stmt->fetch();
            $token = generate_token($new_user);

            json_response([
                'status' => 'success',
                'message' => 'Registrasi berhasil',
                'data' => ['token' => $token, 'username' => $input['username'], 'role' => 'tamu']
            ], 201);
        } catch (Exception $e) {
            $pdo->rollBack();
            json_error('Registrasi gagal: ' . $e->getMessage(), 500);
        }
        break;

    default:
        json_error('Action tidak dikenal. Gunakan: login atau register');
}
