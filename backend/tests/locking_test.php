<?php
// ====================================================================
// HOTELKU_DB - Table Locking & Concurrency Demo
// ====================================================================
// Cara pakai:
// 1. Jalankan di terminal 1: php locking_test.php
// 2. Di terminal 2 (saat masih berjalan): coba akses tabel yang dikunci
//
// Untuk simulasi: jalankan dengan PHP built-in server
// ====================================================================

require_once '../config/database.php';

echo "=== HOTELKU - Table Locking Demo ===\n\n";

// 1. LOCK TABLES
echo "[1] Melakukan LOCK TABLES reservasi WRITE...\n";
$pdo->exec("LOCK TABLES reservasi WRITE, reservasi AS r WRITE");
echo "    ✅ Tabel reservasi berhasil dikunci (WRITE lock)\n\n";

// 2. Operasi INSERT (berhasil karena kita punya lock)
echo "[2] Mencoba INSERT ke tabel yang dikunci...\n";
try {
    $stmt = $pdo->prepare("INSERT INTO reservasi (tamu_id, status_reservasi_id) VALUES (1, 1)");
    $stmt->execute();
    $lastId = $pdo->lastInsertId();
    echo "    ✅ INSERT berhasil! ID: $lastId\n";
    // Hapus data test
    $pdo->exec("DELETE FROM reservasi WHERE id_reservasi = $lastId");
    echo "    🧹 Data test dihapus\n\n";
} catch (Exception $e) {
    echo "    ❌ INSERT gagal: " . $e->getMessage() . "\n\n";
}

// 3. UNLOCK TABLES
echo "[3] Melepaskan kunci (UNLOCK TABLES)...\n";
$pdo->exec("UNLOCK TABLES");
echo "    ✅ Kunci dilepaskan. Tabel dapat diakses kembali.\n\n";

// 4. Demonstrasi READ LOCK (contoh)
echo "[4] Demonstrasi READ LOCK...\n";
$pdo->exec("LOCK TABLES kamar READ");
echo "    ✅ Tabel kamar dikunci dengan READ lock\n";

// READ diperbolehkan
$stmt = $pdo->query("SELECT COUNT(*) FROM kamar");
$total = $stmt->fetchColumn();
echo "    ✅ Membaca data kamar: $total kamar ditemukan\n";

// WRITE akan gagal (simulasi)
// $pdo->exec("UPDATE kamar SET nomor_kamar = '999' WHERE id_kamar = 1"); // Akan error
echo "    ⚠️  WRITE ke tabel yang di-LOCK READ akan ditolak\n";
$pdo->exec("UNLOCK TABLES");

echo "\n=== Demo Selesai ===";
echo "\n\nPenjelasan:\n";
echo "- LOCK TABLES WRITE: hanya session yang memiliki lock bisa membaca/menulis\n";
echo "- LOCK TABLES READ: semua session bisa membaca, hanya session lock yang bisa menulis\n";
echo "- UNLOCK TABLES: melepas semua kunci\n";
echo "- Digunakan di: proses check-in/checkout untuk mencegah race condition\n";
