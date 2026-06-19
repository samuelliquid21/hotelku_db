<?php
// ====================================================================
// HOTELKU_API - Report (Laporan)
// Role: admin only, resepsionis read-only
// Type: okupansi, pendapatan, tamu_aktif, rating, promo
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

if ($user['role'] === 'tamu') {
    json_error('Akses ditolak. Hanya admin/resepsionis', 403);
}

$type = $_GET['type'] ?? '';

switch ($type) {
    case 'okupansi':
        $stmt = $pdo->query("
            SELECT DATE_FORMAT(dr.tanggal_checkin, '%Y-%m') AS bulan,
                   COUNT(DISTINCT dr.kamar_id) AS kamar_terisi,
                   (SELECT COUNT(*) FROM kamar) AS total_kamar,
                   ROUND((COUNT(DISTINCT dr.kamar_id) / (SELECT COUNT(*) FROM kamar)) * 100, 2) AS persentase
            FROM detail_reservasi dr
            JOIN reservasi r ON dr.reservasi_id = r.id_reservasi
            WHERE r.status_reservasi_id IN (2,3)
            GROUP BY bulan ORDER BY bulan
        ");
        $data = ['laporan' => 'Okupansi Kamar', 'data' => $stmt->fetchAll()];
        break;

    case 'pendapatan':
        $stmt = $pdo->query("
            SELECT DATE_FORMAT(tanggal_bayar, '%Y-%m') AS bulan,
                   COUNT(id_pembayaran) AS jumlah_transaksi,
                   SUM(total) AS total_pendapatan
            FROM pembayaran
            GROUP BY bulan ORDER BY bulan
        ");
        $data = ['laporan' => 'Pendapatan Bulanan', 'data' => $stmt->fetchAll()];
        break;

    case 'tamu_aktif':
        $stmt = $pdo->query("
            SELECT t.nama, t.email, COUNT(r.id_reservasi) AS jumlah_booking,
                   IFNULL(SUM(p.total),0) AS total_pengeluaran
            FROM tamu t
            JOIN reservasi r ON t.id_tamu = r.tamu_id
            LEFT JOIN pembayaran p ON r.id_reservasi = p.reservasi_id
            GROUP BY t.id_tamu ORDER BY jumlah_booking DESC LIMIT 5
        ");
        $data = ['laporan' => '5 Tamu Paling Aktif', 'data' => $stmt->fetchAll()];
        break;

    case 'rating':
        $stmt = $pdo->query("
            SELECT tk.nama_tipe, COUNT(u.id_ulasan) AS jumlah_ulasan,
                   ROUND(AVG(u.rating),2) AS rata_rata_rating
            FROM tipe_kamar tk
            LEFT JOIN kamar k ON tk.id_tipe = k.tipe_id
            LEFT JOIN ulasan u ON k.id_kamar = u.kamar_id
            GROUP BY tk.id_tipe ORDER BY rata_rata_rating DESC
        ");
        $data = ['laporan' => 'Rating per Tipe Kamar', 'data' => $stmt->fetchAll()];
        break;

    case 'promo':
        $stmt = $pdo->query("
            SELECT pr.kode, pr.diskon, COUNT(r.id_reservasi) AS jumlah_penggunaan
            FROM promo pr
            LEFT JOIN reservasi r ON pr.id_promo = r.promo_id
            GROUP BY pr.id_promo ORDER BY jumlah_penggunaan DESC
        ");
        $data = ['laporan' => 'Efektivitas Promo', 'data' => $stmt->fetchAll()];
        break;

    default:
        json_error('Type laporan tidak dikenal. Pilih: okupansi, pendapatan, tamu_aktif, rating, promo');
}

$data['role'] = $user['role'];
json_response(['status' => 'success', 'data' => $data]);
