<?php

require_once '../config/database.php';
require_once '../helpers/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method tidak diizinkan', 405);
}

$user = verify_token();

$stmt = $pdo->prepare("SELECT p.id_pegawai, p.nama, p.jabatan_id FROM pegawai p WHERE p.user_id = :uid");
$stmt->execute(['uid' => $user['user_id']]);
$pegawai = $stmt->fetch();

if (!$pegawai || $pegawai['jabatan_id'] != 3) {
    json_error('Akses hanya untuk housekeeping', 403);
}

$pegawai_id = $pegawai['id_pegawai'];
$action = $_GET['action'] ?? 'profile';

switch ($action) {

    case 'profile':
        $stmt = $pdo->prepare("
            SELECT tk.id_tipe, tk.nama_tipe
            FROM penugasan_housekeeping ph
            JOIN tipe_kamar tk ON ph.tipe_id = tk.id_tipe
            WHERE ph.pegawai_id = :pid
            ORDER BY tk.id_tipe
        ");
        $stmt->execute(['pid' => $pegawai_id]);
        $tiers = $stmt->fetchAll();

        json_response([
            'status' => 'success',
            'data' => [
                'nama' => $pegawai['nama'],
                'pegawai_id' => $pegawai_id,
                'assigned_tiers' => $tiers
            ]
        ]);
        break;

    case 'current_guests':
        $stmt = $pdo->prepare("
            SELECT tk.id_tipe, tk.nama_tipe
            FROM penugasan_housekeeping ph
            JOIN tipe_kamar tk ON ph.tipe_id = tk.id_tipe
            WHERE ph.pegawai_id = :pid
        ");
        $stmt->execute(['pid' => $pegawai_id]);
        $tier_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        if (empty($tier_ids)) {
            json_response(['status' => 'success', 'data' => []]);
        }

        $placeholders = implode(',', array_fill(0, count($tier_ids), '?'));

        $stmt = $pdo->prepare("
            SELECT
                k.id_kamar, k.nomor_kamar,
                tk.nama_tipe AS tipe_kamar,
                t.id_tamu, t.nama AS nama_tamu,
                dr.tanggal_checkin, dr.tanggal_checkout,
                r.id_reservasi,
                c.waktu_checkin
            FROM reservasi r
            JOIN detail_reservasi dr ON r.id_reservasi = dr.reservasi_id
            JOIN kamar k ON dr.kamar_id = k.id_kamar
            JOIN tipe_kamar tk ON k.tipe_id = tk.id_tipe
            JOIN tamu t ON r.tamu_id = t.id_tamu
            LEFT JOIN checkin c ON r.id_reservasi = c.reservasi_id
            WHERE r.status_reservasi_id = 2
              AND k.tipe_id IN ($placeholders)
            ORDER BY tk.id_tipe, k.nomor_kamar
        ");
        $stmt->execute($tier_ids);
        $guests = $stmt->fetchAll();

        json_response(['status' => 'success', 'data' => $guests]);
        break;

    case 'stats':
        $stmt = $pdo->prepare("
            SELECT tk.id_tipe, tk.nama_tipe
            FROM penugasan_housekeeping ph
            JOIN tipe_kamar tk ON ph.tipe_id = tk.id_tipe
            WHERE ph.pegawai_id = :pid
        ");
        $stmt->execute(['pid' => $pegawai_id]);
        $tier_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        if (empty($tier_ids)) {
            json_response(['status' => 'success', 'data' => [
                'total_kamar' => 0,
                'kamar_terisi' => 0,
                'kamar_tersedia' => 0
            ]]);
        }

        $placeholders = implode(',', array_fill(0, count($tier_ids), '?'));

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total_kamar,
                SUM(CASE WHEN sk.status = 'Terisi' THEN 1 ELSE 0 END) AS kamar_terisi,
                SUM(CASE WHEN sk.status = 'Tersedia' THEN 1 ELSE 0 END) AS kamar_tersedia
            FROM kamar k
            JOIN status_kamar sk ON k.status_id = sk.id_status
            WHERE k.tipe_id IN ($placeholders)
        ");
        $stmt->execute($tier_ids);
        $stats = $stmt->fetch();

        json_response(['status' => 'success', 'data' => $stats]);
        break;

    default:
        json_error('Action tidak dikenal', 400);
}
