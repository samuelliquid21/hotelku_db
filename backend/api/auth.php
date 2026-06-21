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
            WHERE u.username = :username OR u.email = :username2
        ");
        $stmt->execute(['username' => $input['username'], 'username2' => $input['username']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($input['password'], $user['password'])) {
            json_error('Username atau email salah', 401);
        }

        $token = generate_token($user);

        $tamu_id = null;
        $pegawai_id = null;

        if ($user['nama_role'] === 'tamu') {
            $stmt = $pdo->prepare("SELECT id_tamu FROM tamu WHERE user_id = :uid");
            $stmt->execute(['uid' => $user['id_user']]);
            $tamu_id = $stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("SELECT id_pegawai FROM pegawai WHERE user_id = :uid");
            $stmt->execute(['uid' => $user['id_user']]);
            $pegawai_id = $stmt->fetchColumn();
        }

        $pegawai_nama = null;
        $jabatan_id = null;
        if ($pegawai_id) {
            $stmt = $pdo->prepare("SELECT p.nama, p.jabatan_id FROM pegawai p WHERE p.id_pegawai = :pid");
            $stmt->execute(['pid' => $pegawai_id]);
            $emp = $stmt->fetch();
            $pegawai_nama = $emp['nama'] ?? null;
            $jabatan_id = $emp['jabatan_id'] ?? null;
        }

        json_response([
            'status' => 'success',
            'data' => [
                'id_user' => $user['id_user'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['nama_role'],
                'token' => $token,
                'tamu_id' => $tamu_id,
                'pegawai_id' => $pegawai_id,
                'pegawai_nama' => $pegawai_nama,
                'jabatan_id' => $jabatan_id
            ]
        ]);
        break;

    case 'register':
        validate_required($input, ['username', 'email', 'password', 'nama', 'telepon']);

        if (!str_ends_with($input['email'], '@email.com')) {
            json_error('Email harus menggunakan domain @email.com');
        }

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
                'password' => password_hash($input['password'], PASSWORD_BCRYPT),
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

    case 'update_profile':
        $user = verify_token();

        $stmt = $pdo->prepare("SELECT id_tamu FROM tamu WHERE user_id = :uid");
        $stmt->execute(['uid' => $user['user_id']]);
        $tamu_id = $stmt->fetchColumn();
        if (!$tamu_id) {
            json_error('Data tamu tidak ditemukan', 404);
        }

        $fields = [];
        $params = ['id' => $tamu_id];

        if (!empty($input['nama'])) {
            $fields[] = "nama = :nama";
            $params['nama'] = $input['nama'];
        }
        if (!empty($input['telepon'])) {
            $fields[] = "telepon = :telepon";
            $params['telepon'] = $input['telepon'];
        }
        if (!empty($input['alamat'])) {
            $fields[] = "alamat = :alamat";
            $params['alamat'] = $input['alamat'];
        }

        if (empty($fields)) {
            json_error('Tidak ada data yang diupdate');
        }

        $sql = "UPDATE tamu SET " . implode(', ', $fields) . " WHERE id_tamu = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        json_response(['status' => 'success', 'message' => 'Profil berhasil diupdate']);
        break;

    case 'forgot_password':
        validate_required($input, ['email']);

        $stmt = $pdo->prepare("SELECT id_user FROM users WHERE email = :email");
        $stmt->execute(['email' => $input['email']]);
        $user = $stmt->fetch();

        if (!$user) {
            $stmt = $pdo->prepare("SELECT id_tamu FROM tamu WHERE email = :email");
            $stmt->execute(['email' => $input['email']]);
            if (!$stmt->fetch()) {
                json_error('Email tidak terdaftar');
            }
        }

        $token = bin2hex(random_bytes(32));

        $stmt = $pdo->prepare("INSERT INTO reset_password (email, token) VALUES (:email, :token)");
        $stmt->execute(['email' => $input['email'], 'token' => $token]);

        $stmt = $pdo->prepare("INSERT INTO pesan (nama, email, subjek, pesan, tipe) VALUES ('Sistem', :email, 'Permintaan Reset Password', :pesan, 'reset_request')");
        $stmt->execute([
            'email' => $input['email'],
            'pesan' => "Permintaan reset password dari {$input['email']}. Silakan setujui atau tolak."
        ]);

        json_response(['status' => 'success', 'message' => 'Permintaan reset password telah dikirim ke admin. Silakan tunggu persetujuan.']);
        break;

    case 'approve_reset':
        $user = require_role(['admin']);
        validate_required($input, ['email']);

        $stmt = $pdo->prepare("SELECT id_reset FROM reset_password WHERE email = :email AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute(['email' => $input['email']]);
        $reset = $stmt->fetch();

        if (!$reset) json_error('Tidak ada permintaan reset untuk email tersebut');

        try {
            $stmt = $pdo->prepare("UPDATE reset_password SET status = 'approved', processed_at = NOW(), admin_id = :admin_id WHERE id_reset = :id");
            $stmt->execute(['admin_id' => $user['user_id'], 'id' => $reset['id_reset']]);

            $stmt = $pdo->prepare("UPDATE pesan SET is_read = 1 WHERE email = :email AND tipe = 'reset_request' AND is_read = 0");
            $stmt->execute(['email' => $input['email']]);

            $stmt = $pdo->prepare("INSERT INTO audit_log (aktivitas, user_id, ip_address) VALUES (:akt, :uid, :ip)");
            $stmt->execute(['akt' => 'Menyetujui reset password untuk ' . $input['email'], 'uid' => $user['user_id'], 'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);

            json_response(['status' => 'success', 'message' => 'Permintaan reset disetujui. User dapat mengatur password baru sendiri.']);
        } catch (Exception $e) {
            json_error('Gagal menyetujui reset', 500);
        }
        break;

    case 'set_new_password':
        validate_required($input, ['email', 'password_baru']);

        $stmt = $pdo->prepare("SELECT id_reset FROM reset_password WHERE email = :email AND status = 'approved' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute(['email' => $input['email']]);
        $reset = $stmt->fetch();

        if (!$reset) json_error('Tidak ada permintaan reset yang disetujui. Minta admin untuk menyetujui terlebih dahulu.');

        try {
            $stmt = $pdo->prepare("UPDATE users SET password = :pass WHERE email = :email");
            $stmt->execute(['pass' => password_hash($input['password_baru'], PASSWORD_BCRYPT), 'email' => $input['email']]);

            if ($stmt->rowCount() === 0) {
                $stmt = $pdo->prepare("SELECT id_tamu, user_id FROM tamu WHERE email = :email");
                $stmt->execute(['email' => $input['email']]);
                $tamu = $stmt->fetch();

                if (!$tamu) {
                    json_error('Email tidak terdaftar sebagai tamu. Hubungi admin.');
                }

                if ($tamu['user_id']) {
                    $stmt = $pdo->prepare("UPDATE users SET password = :pass WHERE id_user = :id");
                    $stmt->execute(['pass' => password_hash($input['password_baru'], PASSWORD_BCRYPT), 'id' => $tamu['user_id']]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO users (email, password, role_id) VALUES (:email, :pass, 3)");
                    $stmt->execute(['email' => $input['email'], 'pass' => password_hash($input['password_baru'], PASSWORD_BCRYPT)]);
                    $new_uid = $pdo->lastInsertId();
                    $pdo->prepare("UPDATE tamu SET user_id = :uid WHERE id_tamu = :tid")
                        ->execute(['uid' => $new_uid, 'tid' => $tamu['id_tamu']]);
                }
            }

            $stmt = $pdo->prepare("UPDATE reset_password SET status = 'completed' WHERE id_reset = :id");
            $stmt->execute(['id' => $reset['id_reset']]);

            $stmt = $pdo->prepare("INSERT INTO audit_log (aktivitas, user_id, ip_address) VALUES (:akt, :uid, :ip)");
            $stmt->execute(['akt' => 'User ' . $input['email'] . ' mengatur password baru', 'uid' => null, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);

            json_response(['status' => 'success', 'message' => 'Password berhasil direset. Silakan login dengan password baru.']);
        } catch (Exception $e) {
            json_error('Gagal mereset password: ' . $e->getMessage(), 500);
        }
        break;

    default:
        json_error('Action tidak dikenal. Gunakan: login, register, update_profile, forgot_password, approve_reset, atau set_new_password');
}
