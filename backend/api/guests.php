<?php
// ====================================================================
// HOTELKU_API - Guests (Data Tamu)
// Role: admin/resepsionis → lihat semua, tamu → lihat data sendiri
// Auth: Authorization: Bearer <token>
// ====================================================================

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
$id = $_GET['id'] ?? null;

if ($user['role'] === 'tamu') {
    // Tamu cuma bisa liat data dirinya sendiri
    if ($id) {
        $stmt = $pdo->prepare("
            SELECT t.id_tamu, t.nama, t.email, t.telepon, t.alamat,
                   COUNT(r.id_reservasi) AS total_booking
            FROM tamu t
            LEFT JOIN reservasi r ON t.id_tamu = r.tamu_id
            WHERE t.user_id = :uid
            GROUP BY t.id_tamu
        ");
        $stmt->execute(['uid' => $user['user_id']]);
    } else {
        $stmt = $pdo->prepare("
            SELECT t.id_tamu, t.nama, t.email, t.telepon
            FROM tamu t
            WHERE t.user_id = :uid
        ");
        $stmt->execute(['uid' => $user['user_id']]);
    }
} elseif ($id) {
    $stmt = $pdo->prepare("
        SELECT t.id_tamu, t.nama, t.email, t.telepon, t.alamat, t.user_id,
               COUNT(r.id_reservasi) AS total_booking
        FROM tamu t
        LEFT JOIN reservasi r ON t.id_tamu = r.tamu_id
        WHERE t.id_tamu = :id
        GROUP BY t.id_tamu
    ");
    $stmt->execute(['id' => $id]);
} else {
    $stmt = $pdo->query("
        SELECT t.id_tamu, t.nama, t.email, t.telepon,
               COUNT(r.id_reservasi) AS total_booking
        FROM tamu t
        LEFT JOIN reservasi r ON t.id_tamu = r.tamu_id
        GROUP BY t.id_tamu
        ORDER BY total_booking DESC
    ");
}

$data = $stmt->fetchAll();

// Kalo detail tamu, ambil juga history booking-nya
if ($id && $data) {
    $tamu_id = $data['id_tamu'] ?? $data[0]['id_tamu'];
    $stmt = $pdo->prepare("
        SELECT r.id_reservasi, sr.status, r.tanggal_pesan,
               dr.tanggal_checkin, dr.tanggal_checkout
        FROM reservasi r
        JOIN status_reservasi sr ON r.status_reservasi_id = sr.id_status_reservasi
        JOIN detail_reservasi dr ON dr.reservasi_id = r.id_reservasi
        WHERE r.tamu_id = :tamu_id
        ORDER BY r.tanggal_pesan DESC
    ");
    $stmt->execute(['tamu_id' => $tamu_id]);
    $data['riwayat_reservasi'] = $stmt->fetchAll();
}

json_response(['status' => 'success', 'data' => $data]);
