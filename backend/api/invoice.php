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
$reservasi_id = $_GET['reservasi_id'] ?? null;
$no_invoice = $_GET['no_invoice'] ?? null;

if (!$reservasi_id && !$no_invoice) {
    json_error('Parameter reservasi_id atau no_invoice diperlukan');
}

// Ambil data reservasi + invoice
$sql = "
    SELECT r.id_reservasi, r.tanggal_pesan, sr.status AS status_reservasi,
           i.nomor_invoice, i.tanggal_terbit,
           t.id_tamu, t.nama AS nama_tamu, t.email AS email_tamu, 
           t.telepon AS telepon_tamu, t.alamat AS alamat_tamu,
           p.id_pembayaran, p.total AS total_bayar, p.tanggal_bayar,
           mp.nama_metode
    FROM reservasi r
    JOIN tamu t ON r.tamu_id = t.id_tamu
    JOIN status_reservasi sr ON r.status_reservasi_id = sr.id_status_reservasi
    JOIN invoice i ON r.id_reservasi = i.reservasi_id
    JOIN pembayaran p ON r.id_reservasi = p.reservasi_id
    JOIN metode_pembayaran mp ON p.metode_id = mp.id_metode
";

$params = [];

if ($reservasi_id) {
    $sql .= " WHERE r.id_reservasi = :rid";
    $params['rid'] = $reservasi_id;
} else {
    $sql .= " WHERE i.nomor_invoice = :inv";
    $params['inv'] = $no_invoice;
}

// Cek akses: tamu hanya bisa lihat invoice sendiri
if ($user['role'] === 'tamu') {
    if ($reservasi_id) {
        $sql .= " AND t.user_id = :uid";
    } else {
        $sql .= " AND t.user_id = :uid";
    }
    $params['uid'] = $user['user_id'];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$header = $stmt->fetch();

if (!$header) {
    json_error('Invoice tidak ditemukan', 404);
}

// Ambil detail kamar
$stmt = $pdo->prepare("
    SELECT dr.tanggal_checkin, dr.tanggal_checkout, dr.harga,
           k.nomor_kamar, tk.nama_tipe, tk.kapasitas
    FROM detail_reservasi dr
    JOIN kamar k ON dr.kamar_id = k.id_kamar
    JOIN tipe_kamar tk ON k.tipe_id = tk.id_tipe
    WHERE dr.reservasi_id = :rid
");
$stmt->execute(['rid' => $header['id_reservasi']]);
$details = $stmt->fetchAll();

// Hitung subtotal
$subtotal = 0;
foreach ($details as $d) {
    $selisih = strtotime($d['tanggal_checkout']) - strtotime($d['tanggal_checkin']);
    $malam = max(1, ceil($selisih / 86400));
    $subtotal += $d['harga'] * $malam;
}

// Ambil promo jika ada
$stmt = $pdo->prepare("
    SELECT pr.kode, pr.diskon
    FROM reservasi r
    JOIN promo pr ON r.promo_id = pr.id_promo
    WHERE r.id_reservasi = :rid
");
$stmt->execute(['rid' => $header['id_reservasi']]);
$promo = $stmt->fetch();

$data = [
    'invoice' => [
        'nomor' => $header['nomor_invoice'],
        'tanggal_terbit' => $header['tanggal_terbit'],
        'tanggal_pesan' => $header['tanggal_pesan'],
    ],
    'tamu' => [
        'id' => $header['id_tamu'],
        'nama' => $header['nama_tamu'],
        'email' => $header['email_tamu'],
        'telepon' => $header['telepon_tamu'],
        'alamat' => $header['alamat_tamu'],
    ],
    'reservasi' => [
        'id' => $header['id_reservasi'],
        'status' => $header['status_reservasi'],
        'detail_kamar' => $details,
        'subtotal' => $subtotal,
        'promo' => $promo ? $promo : null,
    ],
    'pembayaran' => [
        'id' => $header['id_pembayaran'],
        'total' => $header['total_bayar'],
        'tanggal_bayar' => $header['tanggal_bayar'],
        'metode' => $header['nama_metode'],
    ],
];

json_response(['status' => 'success', 'data' => $data]);
