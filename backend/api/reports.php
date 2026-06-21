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
$action = $_GET['action'] ?? '';

if ($action === 'revenue_chart') {
    $tahun = $_GET['tahun'] ?? date('Y');

    $stmt = $pdo->prepare("
        SELECT
            MONTH(p.tanggal_bayar) AS bulan,
            SUM(p.total) AS total
        FROM pembayaran p
        JOIN reservasi r ON p.reservasi_id = r.id_reservasi
        JOIN status_reservasi sr ON r.status_reservasi_id = sr.id_status_reservasi
        WHERE YEAR(p.tanggal_bayar) = :tahun
        GROUP BY MONTH(p.tanggal_bayar)
        ORDER BY bulan
    ");
    $stmt->execute(['tahun' => $tahun]);
    $rows = $stmt->fetchAll();

    $months = [];
    $values = [];
    for ($i = 1; $i <= 12; $i++) {
        $months[] = date('F', mktime(0, 0, 0, $i, 1));
        $found = 0;
        foreach ($rows as $r) {
            if ((int)$r['bulan'] === $i) {
                $found = (float)$r['total'];
                break;
            }
        }
        $values[] = $found;
    }

    json_response([
        'status' => 'success',
        'data' => [
            'tahun' => $tahun,
            'labels' => $months,
            'values' => $values
        ]
    ]);
} elseif ($action === 'tahun_list') {
    $stmt = $pdo->query("SELECT DISTINCT YEAR(tanggal_bayar) AS tahun FROM pembayaran WHERE tanggal_bayar IS NOT NULL ORDER BY tahun DESC");
    $tahun = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tahun)) $tahun = [date('Y')];
    json_response(['status' => 'success', 'data' => $tahun]);
} else {
    json_error('Action harus revenue_chart atau tahun_list');
}
