-- ====================================================================
-- Hotelku_DB - Fitur Lanjutan DBMS
-- BAGIAN 2: 3 Trigger + 2 Function + 3 SP + 1 Cursor + 5 Laporan
-- PAKAI DELIMITER biar aman di VSCode extension
-- ====================================================================

USE Hotelku_DB;

-- ======================== TRIGGER ===========================

-- Trigger 1: Catat log setiap reservasi baru
DROP TRIGGER IF EXISTS after_insert_reservasi;
CREATE TRIGGER after_insert_reservasi
AFTER INSERT ON reservasi
FOR EACH ROW
INSERT INTO audit_log (aktivitas, user_id, ip_address)
VALUES (CONCAT('Reservasi baru #', NEW.id_reservasi, ' untuk tamu ID ', NEW.tamu_id), 1, '10.0.0.1');

-- Trigger 2: Validasi tanggal checkout > checkin (pakai DELIMITER)
DROP TRIGGER IF EXISTS before_insert_detail_reservasi;
DELIMITER //

CREATE TRIGGER before_insert_detail_reservasi
BEFORE INSERT ON detail_reservasi
FOR EACH ROW
BEGIN
    IF NEW.tanggal_checkout <= NEW.tanggal_checkin THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tanggal checkout harus lebih besar dari tanggal checkin';
    END IF;
END //

DELIMITER ;

-- Trigger 3: Update status kamar saat status reservasi berubah
DROP TRIGGER IF EXISTS after_update_status_reservasi;
DELIMITER //

CREATE TRIGGER after_update_status_reservasi
AFTER UPDATE ON reservasi
FOR EACH ROW
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE kamar_id_val INT;
    DECLARE cur CURSOR FOR SELECT dr.kamar_id FROM detail_reservasi dr WHERE dr.reservasi_id = NEW.id_reservasi;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    IF NEW.status_reservasi_id <> OLD.status_reservasi_id THEN
        IF NEW.status_reservasi_id = 2 THEN
            OPEN cur;
            read_loop: LOOP
                FETCH cur INTO kamar_id_val;
                IF done THEN LEAVE read_loop; END IF;
                UPDATE kamar SET status_id = 2 WHERE id_kamar = kamar_id_val;
            END LOOP;
            CLOSE cur;
        END IF;
        IF NEW.status_reservasi_id IN (3, 4) THEN
            OPEN cur;
            read_loop: LOOP
                FETCH cur INTO kamar_id_val;
                IF done THEN LEAVE read_loop; END IF;
                UPDATE kamar SET status_id = 1 WHERE id_kamar = kamar_id_val;
            END LOOP;
            CLOSE cur;
        END IF;
        INSERT INTO audit_log (aktivitas, user_id, ip_address)
        VALUES (CONCAT('Reservasi #', NEW.id_reservasi, ' berubah status: ', OLD.status_reservasi_id, ' -> ', NEW.status_reservasi_id), 1, '10.0.0.1');
    END IF;
END //

DELIMITER ;

-- ======================== FUNCTION ===========================

-- Function 1: Hitung total harga reservasi
DROP FUNCTION IF EXISTS hitung_total_harga;
DELIMITER //

CREATE FUNCTION hitung_total_harga(p_reservasi_id INT) RETURNS DECIMAL(10,2) DETERMINISTIC
BEGIN
    DECLARE total DECIMAL(10,2);
    SELECT SUM(harga) INTO total FROM detail_reservasi WHERE reservasi_id = p_reservasi_id;
    RETURN IFNULL(total, 0);
END //

DELIMITER ;

-- Function 2: Cek ketersediaan kamar
DROP FUNCTION IF EXISTS cek_ketersediaan_kamar;
DELIMITER //

CREATE FUNCTION cek_ketersediaan_kamar(p_tipe_id INT, p_checkin DATE, p_checkout DATE) RETURNS INT DETERMINISTIC
BEGIN
    DECLARE tersedia INT;
    SELECT COUNT(*) INTO tersedia
    FROM kamar k
    WHERE k.tipe_id = p_tipe_id AND k.status_id = 1
      AND k.id_kamar NOT IN (
          SELECT dr.kamar_id FROM detail_reservasi dr
          JOIN reservasi r ON dr.reservasi_id = r.id_reservasi
          WHERE r.status_reservasi_id IN (1, 2)
            AND dr.tanggal_checkin < p_checkout AND dr.tanggal_checkout > p_checkin
      );
    RETURN tersedia;
END //

DELIMITER ;

-- ======================== STORED PROCEDURE ===========================

-- SP 1: Tambah reservasi baru
DROP PROCEDURE IF EXISTS tambah_reservasi;
DELIMITER //

CREATE PROCEDURE tambah_reservasi(
    IN p_tamu_id INT, IN p_kamar_id INT, IN p_tanggal_checkin DATE,
    IN p_tanggal_checkout DATE, IN p_promo_id INT)
BEGIN
    DECLARE v_status_baru INT;
    DECLARE v_harga_kamar DECIMAL(10,2);
    DECLARE v_reservasi_id INT;
    SELECT id_status_reservasi INTO v_status_baru FROM status_reservasi WHERE status = 'Baru' LIMIT 1;
    SELECT tk.harga INTO v_harga_kamar FROM kamar k JOIN tipe_kamar tk ON k.tipe_id = tk.id_tipe WHERE k.id_kamar = p_kamar_id LIMIT 1;
    START TRANSACTION;
    INSERT INTO reservasi (tamu_id, status_reservasi_id, promo_id) VALUES (p_tamu_id, v_status_baru, p_promo_id);
    SET v_reservasi_id = LAST_INSERT_ID();
    INSERT INTO detail_reservasi (reservasi_id, kamar_id, tanggal_checkin, tanggal_checkout, harga) VALUES (v_reservasi_id, p_kamar_id, p_tanggal_checkin, p_tanggal_checkout, v_harga_kamar);
    COMMIT;
    SELECT v_reservasi_id AS id_reservasi_baru;
END //

DELIMITER ;

-- SP 2: Proses checkout
DROP PROCEDURE IF EXISTS proses_checkout;
DELIMITER //

CREATE PROCEDURE proses_checkout(IN p_reservasi_id INT, IN p_pegawai_id INT)
BEGIN
    DECLARE v_status_checkout INT;
    SELECT id_status_reservasi INTO v_status_checkout FROM status_reservasi WHERE status = 'Check-out' LIMIT 1;
    START TRANSACTION;
    INSERT INTO checkout (reservasi_id, pegawai_id) VALUES (p_reservasi_id, p_pegawai_id);
    UPDATE reservasi SET status_reservasi_id = v_status_checkout WHERE id_reservasi = p_reservasi_id;
    COMMIT;
    SELECT CONCAT('Reservasi #', p_reservasi_id, ' berhasil check-out') AS hasil;
END //

DELIMITER ;

-- SP 3: Generate laporan harian (dengan CURSOR)
DROP PROCEDURE IF EXISTS generate_laporan_harian;
DELIMITER //

CREATE PROCEDURE generate_laporan_harian(IN p_tanggal DATE)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_reservasi_id INT;
    DECLARE v_total DECIMAL(10,2);
    DECLARE v_nama_tamu VARCHAR(150);
    DECLARE v_grand_total DECIMAL(10,2) DEFAULT 0;
    DECLARE cur_pembayaran CURSOR FOR
        SELECT p.reservasi_id, p.total, t.nama
        FROM pembayaran p JOIN reservasi r ON p.reservasi_id = r.id_reservasi
        JOIN tamu t ON r.tamu_id = t.id_tamu
        WHERE DATE(p.tanggal_bayar) = p_tanggal;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    DROP TEMPORARY TABLE IF EXISTS laporan_harian;
    CREATE TEMPORARY TABLE laporan_harian (no INT AUTO_INCREMENT PRIMARY KEY, reservasi_id INT, nama_tamu VARCHAR(150), total_bayar DECIMAL(10,2));
    OPEN cur_pembayaran;
    read_loop: LOOP
        FETCH cur_pembayaran INTO v_reservasi_id, v_total, v_nama_tamu;
        IF done THEN LEAVE read_loop; END IF;
        INSERT INTO laporan_harian (reservasi_id, nama_tamu, total_bayar) VALUES (v_reservasi_id, v_nama_tamu, v_total);
        SET v_grand_total = v_grand_total + v_total;
    END LOOP;
    CLOSE cur_pembayaran;
    SELECT * FROM laporan_harian;
    SELECT p_tanggal AS tanggal, v_grand_total AS grand_total;
    DROP TEMPORARY TABLE IF EXISTS laporan_harian;
END //

DELIMITER ;

-- ======================== AGGREGATE (5 query) ===========================

-- 1. SUM
SELECT '1. SUM - Total Pendapatan' AS '';
SELECT SUM(total) AS total_pendapatan FROM pembayaran;

-- 2. AVG
SELECT '2. AVG - Rata-rata Rating Kamar' AS '';
SELECT AVG(rating) AS rata_rata_rating FROM ulasan;

-- 3. MAX
SELECT '3. MAX - Harga Kamar Termahal' AS '';
SELECT MAX(harga) AS harga_termahal FROM tipe_kamar;

-- 4. MIN
SELECT '4. MIN - Harga Kamar Termurah' AS '';
SELECT MIN(harga) AS harga_termurah FROM tipe_kamar;

-- 5. COUNT
SELECT '5. COUNT - Jumlah Reservasi per Status' AS '';
SELECT sr.status, COUNT(r.id_reservasi) AS jumlah
FROM status_reservasi sr LEFT JOIN reservasi r ON sr.id_status_reservasi = r.status_reservasi_id
GROUP BY sr.id_status_reservasi, sr.status;

-- ======================== TCL ===========================

START TRANSACTION;
INSERT INTO reservasi (tamu_id, status_reservasi_id, promo_id) VALUES (9, 1, NULL);
SET @id_reservasi_baru = LAST_INSERT_ID();
INSERT INTO detail_reservasi (reservasi_id, kamar_id, tanggal_checkin, tanggal_checkout, harga) VALUES (@id_reservasi_baru, 12, '2026-02-01', '2026-02-03', 850000.00);
INSERT INTO pembayaran (reservasi_id, metode_id, total) VALUES (@id_reservasi_baru, 1, 850000.00);
INSERT INTO invoice (reservasi_id, nomor_invoice) VALUES (@id_reservasi_baru, 'INV-202602-001');
COMMIT;
SELECT 'COMMIT berhasil' AS hasil;

START TRANSACTION;
INSERT INTO reservasi (tamu_id, status_reservasi_id, promo_id) VALUES (10, 1, NULL);
SET @id_reservasi_gagal = LAST_INSERT_ID();
INSERT INTO detail_reservasi (reservasi_id, kamar_id, tanggal_checkin, tanggal_checkout, harga) VALUES (@id_reservasi_gagal, 3, '2026-02-01', '2026-02-03', 350000.00);
ROLLBACK;
SELECT 'ROLLBACK - data tidak jadi tersimpan' AS hasil;

-- ======================== 5 LAPORAN ===========================

-- Laporan 1
SELECT 'LAPORAN 1: Okupansi Kamar' AS '';
SELECT DATE_FORMAT(dr.tanggal_checkin, '%Y-%m') AS bulan,
       COUNT(DISTINCT dr.kamar_id) AS kamar_terisi, (SELECT COUNT(*) FROM kamar) AS total_kamar,
       ROUND((COUNT(DISTINCT dr.kamar_id) / (SELECT COUNT(*) FROM kamar)) * 100, 2) AS persentase
FROM detail_reservasi dr JOIN reservasi r ON dr.reservasi_id = r.id_reservasi
WHERE r.status_reservasi_id IN (2,3) GROUP BY bulan ORDER BY bulan;

-- Laporan 2
SELECT 'LAPORAN 2: Pendapatan Bulanan' AS '';
SELECT DATE_FORMAT(tanggal_bayar, '%Y-%m') AS bulan, COUNT(id_pembayaran) AS jumlah, SUM(total) AS total
FROM pembayaran GROUP BY bulan ORDER BY bulan;

-- Laporan 3
SELECT 'LAPORAN 3: 5 Tamu Paling Aktif' AS '';
SELECT t.nama, t.email, COUNT(r.id_reservasi) AS jumlah_booking, IFNULL(SUM(p.total),0) AS total_pengeluaran
FROM tamu t JOIN reservasi r ON t.id_tamu = r.tamu_id LEFT JOIN pembayaran p ON r.id_reservasi = p.reservasi_id
GROUP BY t.id_tamu ORDER BY jumlah_booking DESC LIMIT 5;

-- Laporan 4
SELECT 'LAPORAN 4: Rating per Tipe Kamar' AS '';
SELECT tk.nama_tipe, COUNT(u.id_ulasan) AS jumlah_ulasan, ROUND(AVG(u.rating),2) AS rata_rata
FROM tipe_kamar tk LEFT JOIN kamar k ON tk.id_tipe = k.tipe_id LEFT JOIN ulasan u ON k.id_kamar = u.kamar_id
GROUP BY tk.id_tipe ORDER BY rata_rata DESC;

-- Laporan 5
SELECT 'LAPORAN 5: Efektivitas Promo' AS '';
SELECT pr.kode, pr.diskon, COUNT(r.id_reservasi) AS jumlah_penggunaan
FROM promo pr LEFT JOIN reservasi r ON pr.id_promo = r.promo_id
GROUP BY pr.id_promo ORDER BY jumlah_penggunaan DESC;
