<?php
// ====================================================================
// HOTELKU_API - Employees (Manajemen Karyawan)
// Role: admin only
// ====================================================================

require_once '../config/database.php';
require_once '../helpers/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$user = require_role(['admin']);
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    case 'GET':
        $id = $_GET['id'] ?? null;

        if ($id) {
            $stmt = $pdo->prepare("
                SELECT p.id_pegawai, p.nama, p.alamat, p.telepon,
                       j.id_jabatan, j.nama_jabatan,
                       u.id_user, u.username, u.email
                FROM pegawai p
                LEFT JOIN jabatan j ON p.jabatan_id = j.id_jabatan
                LEFT JOIN users u ON p.user_id = u.id_user
                WHERE p.id_pegawai = :id
            ");
            $stmt->execute(['id' => $id]);
            $data = $stmt->fetch();
            if (!$data) json_error('Karyawan tidak ditemukan', 404);
            json_response(['status' => 'success', 'data' => $data]);
        }

        $stmt = $pdo->query("
            SELECT p.id_pegawai, p.nama, p.alamat, p.telepon,
                   j.nama_jabatan,
                   u.username, u.email, u.role_id
            FROM pegawai p
            LEFT JOIN jabatan j ON p.jabatan_id = j.id_jabatan
            LEFT JOIN users u ON p.user_id = u.id_user
            ORDER BY p.id_pegawai
        ");
        json_response(['status' => 'success', 'data' => $stmt->fetchAll()]);
        break;

    case 'POST':
        $input = get_json_input();
        validate_required($input, ['nama', 'jabatan_id']);

        $pdo->beginTransaction();
        try {
            // Cari role_id berdasarkan jabatan
            $stmt = $pdo->prepare("
                SELECT id_jabatan, nama_jabatan FROM jabatan WHERE id_jabatan = :id
            ");
            $stmt->execute(['id' => $input['jabatan_id']]);
            $jabatan = $stmt->fetch();
            if (!$jabatan) json_error('Jabatan tidak ditemukan');

            $user_id = null;
            // Jika ada username, buat akun user juga
            if (!empty($input['username'])) {
                if (empty($input['email']) || empty($input['password'])) {
                    json_error('Email dan password wajib jika membuat akun');
                }

                $stmt = $pdo->prepare("SELECT id_user FROM users WHERE username = :u OR email = :e");
                $stmt->execute(['u' => $input['username'], 'e' => $input['email']]);
                if ($stmt->fetch()) {
                    json_error('Username atau email sudah terdaftar');
                }

                // Role: Manajer → admin, Resepsionis → resepsionis, lainnya → resepsionis default
                $role_map = [
                    'Manajer Hotel' => 'admin',
                    'Resepsionis' => 'resepsionis',
                    'Housekeeping' => 'resepsionis',
                    'Staff Keuangan' => 'resepsionis',
                ];
                $role_name = $role_map[$jabatan['nama_jabatan']] ?? 'resepsionis';

                $stmt = $pdo->prepare("SELECT id_role FROM roles WHERE nama_role = :r");
                $stmt->execute(['r' => $role_name]);
                $role = $stmt->fetch();
                if (!$role) json_error('Role tidak ditemukan');

                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role_id) VALUES (:u, :e, :p, :r)");
                $stmt->execute([
                    'u' => $input['username'],
                    'e' => $input['email'],
                    'p' => password_hash($input['password'], PASSWORD_BCRYPT),
                    'r' => $role['id_role']
                ]);
                $user_id = $pdo->lastInsertId();
            }

            $stmt = $pdo->prepare("
                INSERT INTO pegawai (nama, alamat, telepon, jabatan_id, user_id)
                VALUES (:nama, :alamat, :telepon, :jabatan_id, :user_id)
            ");
            $stmt->execute([
                'nama' => $input['nama'],
                'alamat' => $input['alamat'] ?? '',
                'telepon' => $input['telepon'] ?? '',
                'jabatan_id' => $input['jabatan_id'],
                'user_id' => $user_id
            ]);
            $pegawai_id = $pdo->lastInsertId();

            $pdo->commit();

            json_response([
                'status' => 'success',
                'message' => 'Karyawan berhasil ditambahkan',
                'id_pegawai' => $pegawai_id
            ], 201);
        } catch (Exception $e) {
            $pdo->rollBack();
            json_error('Gagal: ' . $e->getMessage(), 500);
        }
        break;

    case 'DELETE':
        $id = $_GET['id'] ?? null;
        if (!$id) json_error('ID karyawan diperlukan');

        $stmt = $pdo->prepare("SELECT user_id FROM pegawai WHERE id_pegawai = :id");
        $stmt->execute(['id' => $id]);
        $pegawai = $stmt->fetch();
        if (!$pegawai) json_error('Karyawan tidak ditemukan', 404);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("DELETE FROM pegawai WHERE id_pegawai = :id");
            $stmt->execute(['id' => $id]);

            if ($pegawai['user_id']) {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id_user = :uid");
                $stmt->execute(['uid' => $pegawai['user_id']]);
            }

            $pdo->commit();
            json_response(['status' => 'success', 'message' => 'Karyawan berhasil dihapus']);
        } catch (Exception $e) {
            $pdo->rollBack();
            json_error('Gagal: ' . $e->getMessage(), 500);
        }
        break;

    default:
        json_error('Method tidak diizinkan', 405);
}
