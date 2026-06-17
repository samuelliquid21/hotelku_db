<?php
// ====================================================================
// HOTELKU_API - Dashboard (Ringkasan)
// Role: admin/resepsionis → full, tamu → ringkasan sendiri
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
$data = [];

if ($user['role'] === 'tamu') {
    // Tamu: ringkasan data pribadi
    $stmt = $pdo->prepare("
        SELECT t.id_tamu, t.nama, t.email
        FROM tamu t WHERE t.user_id = :uid
    ");
    $stmt->execute(['uid' => $user['user_id']]);
    $data['profil'] = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM reservasi r
        JOIN tamu t ON r.tamu_id = t.id_tamu
        WHERE t.user_id = :uid
    ");
    $stmt->execute(['uid' => $user['user_id']]);
    $data['total_reservasi_saya'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM reservasi r
        JOIN tamu t ON r.tamu_id = t.id_tamu
        JOIN status_reservasi sr ON r.status_reservasi_id = sr.id_status_reservasi
        WHERE t.user_id = :uid AND sr.status IN ('Baru', 'Check-in')
    ");
    $stmt->execute(['uid' => $user['user_id']]);
    $data['reservasi_aktif_saya'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT IFNULL(SUM(p.total), 0) FROM pembayaran p
        JOIN reservasi r ON p.reservasi_id = r.id_reservasi
        JOIN tamu t ON r.tamu_id = t.id_tamu
        WHERE t.user_id = :uid
    ");
    $stmt->execute(['uid' => $user['user_id']]);
    $data['total_pengeluaran_saya'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT r.id_reservasi, sr.status, r.tanggal_pesan,
               dr.tanggal_checkin, dr.tanggal_checkout, dr.harga
        FROM reservasi r
        JOIN tamu t ON r.tamu_id = t.id_tamu
        JOIN status_reservasi sr ON r.status_reservasi_id = sr.id_status_reservasi
        JOIN detail_reservasi dr ON dr.reservasi_id = r.id_reservasi
        WHERE t.user_id = :uid
        ORDER BY r.tanggal_pesan DESC
        LIMIT 5
    ");
    $stmt->execute(['uid' => $user['user_id']]);
    $data['reservasi_terbaru_saya'] = $stmt->fetchAll();
} else {
    $data['total_kamar'] = $pdo->query("SELECT COUNT(*) FROM kamar")->fetchColumn();
    $data['kamar_terisi'] = $pdo->query("SELECT COUNT(*) FROM kamar WHERE status_id = 2")->fetchColumn();
    $data['kamar_tersedia'] = $pdo->query("SELECT COUNT(*) FROM kamar WHERE status_id = 1")->fetchColumn();
    $data['total_reservasi'] = $pdo->query("SELECT COUNT(*) FROM reservasi")->fetchColumn();
    $data['reservasi_aktif'] = $pdo->query("
        SELECT COUNT(*) FROM reservasi r
        JOIN status_reservasi sr ON r.status_reservasi_id = sr.id_status_reservasi
        WHERE sr.status IN ('Baru', 'Check-in')
    ")->fetchColumn();
    $data['total_tamu'] = $pdo->query("SELECT COUNT(*) FROM tamu")->fetchColumn();
    $data['total_pendapatan'] = $pdo->query("SELECT IFNULL(SUM(total), 0) FROM pembayaran")->fetchColumn();

    $data['reservasi_terbaru'] = $pdo->query("
        SELECT r.id_reservasi, t.nama AS tamu, sr.status, r.tanggal_pesan
        FROM reservasi r
        JOIN tamu t ON r.tamu_id = t.id_tamu
        JOIN status_reservasi sr ON r.status_reservasi_id = sr.id_status_reservasi
        ORDER BY r.tanggal_pesan DESC LIMIT 5
    ")->fetchAll();

    $data['pendapatan_bulanan'] = $pdo->query("
        SELECT DATE_FORMAT(tanggal_bayar, '%Y-%m') AS bulan,
               SUM(total) AS total
        FROM pembayaran
        WHERE tanggal_bayar >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY bulan ORDER BY bulan
    ")->fetchAll();
}

$data['role'] = $user['role'];
json_response(['status' => 'success', 'data' => $data]);
