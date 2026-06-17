-- ====================================================================
-- Hotelku_DB - Sistem Reservasi Hotel
-- BAGIAN 1: CREATE DATABASE + DDL (21 tabel) + DML (~155 data)
-- ====================================================================

CREATE DATABASE IF NOT EXISTS Hotelku_DB;
USE Hotelku_DB;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_log, ulasan, checkout, checkin, invoice,
    pembayaran, detail_reservasi, reservasi, kamar_fasilitas, promo,
    status_reservasi, metode_pembayaran, kamar, fasilitas, status_kamar,
    tipe_kamar, tamu, pegawai, users, jabatan, roles;

DROP PROCEDURE IF EXISTS tambah_reservasi;
DROP PROCEDURE IF EXISTS proses_checkout;
DROP PROCEDURE IF EXISTS generate_laporan_harian;
DROP FUNCTION IF EXISTS hitung_total_harga;
DROP FUNCTION IF EXISTS cek_ketersediaan_kamar;
DROP TRIGGER IF EXISTS after_insert_reservasi;
DROP TRIGGER IF EXISTS before_insert_detail_reservasi;
DROP TRIGGER IF EXISTS after_update_status_reservasi;

SET FOREIGN_KEY_CHECKS = 1;

-- roles
CREATE TABLE roles (id_role INT AUTO_INCREMENT, nama_role VARCHAR(50) NOT NULL, PRIMARY KEY (id_role));
INSERT INTO roles (nama_role) VALUES ('admin'), ('resepsionis'), ('tamu');

-- users
CREATE TABLE users (
    id_user INT AUTO_INCREMENT, username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL,
    role_id INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_user), FOREIGN KEY (role_id) REFERENCES roles(id_role) ON DELETE SET NULL
);
INSERT INTO users (username, email, password, role_id) VALUES
('admin1','admin1@hotelku.com','password123',1),('admin2','admin2@hotelku.com','password123',1),
('resepsionis1','resepsionis1@hotelku.com','password123',2),('resepsionis2','resepsionis2@hotelku.com','password123',2),
('resepsionis3','resepsionis3@hotelku.com','password123',2),('andi_tamu','andi@email.com','password123',3),
('budi_tamu','budi@email.com','password123',3),('citra_tamu','citra@email.com','password123',3);

-- jabatan
CREATE TABLE jabatan (id_jabatan INT AUTO_INCREMENT, nama_jabatan VARCHAR(100) NOT NULL, PRIMARY KEY (id_jabatan));
INSERT INTO jabatan (nama_jabatan) VALUES ('Manajer Hotel'),('Resepsionis'),('Housekeeping'),('Staff Keuangan');

-- pegawai
CREATE TABLE pegawai (
    id_pegawai INT AUTO_INCREMENT, nama VARCHAR(150) NOT NULL, alamat TEXT,
    telepon VARCHAR(15), jabatan_id INT, user_id INT UNIQUE,
    PRIMARY KEY (id_pegawai), FOREIGN KEY (jabatan_id) REFERENCES jabatan(id_jabatan) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id_user) ON DELETE SET NULL
);
INSERT INTO pegawai (nama, alamat, telepon, jabatan_id, user_id) VALUES
('Bambang Suprapto','Jl. Merdeka No.1','081234567890',1,1),
('Dewi Sartika','Jl. Melati No.5','081234567891',2,3),
('Eko Prasetyo','Jl. Mawar No.10','081234567892',2,4),
('Fitri Handayani','Jl. Kenanga No.3','081234567893',2,5),
('Galih Gumilang','Jl. Anggrek No.7','081234567894',3,NULL),
('Hesti Rahayu','Jl. Dahlia No.2','081234567895',4,2);

-- tamu
CREATE TABLE tamu (
    id_tamu INT AUTO_INCREMENT, nama VARCHAR(150) NOT NULL, email VARCHAR(100) NOT NULL UNIQUE,
    telepon VARCHAR(15), alamat TEXT, user_id INT UNIQUE,
    PRIMARY KEY (id_tamu), FOREIGN KEY (user_id) REFERENCES users(id_user) ON DELETE SET NULL
);
INSERT INTO tamu (nama, email, telepon, alamat, user_id) VALUES
('Andi Pratama','andi@email.com','085111111111','Jl. Sudirman No.10',6),
('Budi Santoso','budi@email.com','085111111112','Jl. Thamrin No.20',7),
('Citra Dewi','citra@email.com','085111111113','Jl. Gatot Subroto No.30',8),
('Dian Permata','dian@email.com','085111111114','Jl. Asia Afrika No.15',NULL),
('Eka Putra','eka@email.com','085111111115','Jl. Diponegoro No.25',NULL),
('Fitria Ningsih','fitria@email.com','085111111116','Jl. Ahmad Yani No.35',NULL),
('Gilang Ramadhan','gilang@email.com','085111111117','Jl. Siliwangi No.8',NULL),
('Hana Safitri','hana@email.com','085111111118','Jl. Pahlawan No.12',NULL),
('Irfan Hakim','irfan@email.com','085111111119','Jl. Veteran No.18',NULL),
('Joko Widianto','joko@email.com','085111111120','Jl. Merdeka No.5',NULL);

-- tipe_kamar
CREATE TABLE tipe_kamar (id_tipe INT AUTO_INCREMENT, nama_tipe VARCHAR(50) NOT NULL, harga DECIMAL(10,2) NOT NULL, kapasitas INT NOT NULL, PRIMARY KEY (id_tipe));
INSERT INTO tipe_kamar (nama_tipe, harga, kapasitas) VALUES
('Standard',350000.00,2),('Deluxe',600000.00,2),('Superior',850000.00,3),('Suite',1500000.00,4);

-- status_kamar
CREATE TABLE status_kamar (id_status INT AUTO_INCREMENT, status VARCHAR(50) NOT NULL, PRIMARY KEY (id_status));
INSERT INTO status_kamar (status) VALUES ('Tersedia'),('Terisi'),('Maintenance');

-- kamar
CREATE TABLE kamar (
    id_kamar INT AUTO_INCREMENT, nomor_kamar VARCHAR(10) NOT NULL UNIQUE, tipe_id INT, status_id INT,
    PRIMARY KEY (id_kamar), FOREIGN KEY (tipe_id) REFERENCES tipe_kamar(id_tipe) ON DELETE SET NULL,
    FOREIGN KEY (status_id) REFERENCES status_kamar(id_status) ON DELETE SET NULL
);
INSERT INTO kamar (nomor_kamar, tipe_id, status_id) VALUES
('101',1,1),('102',1,1),('103',1,2),('104',1,1),
('201',2,2),('202',2,1),('203',2,1),('204',2,3),
('301',3,1),('302',3,2),('303',3,1),('304',3,1),
('401',4,1),('402',4,2),('403',4,1),('404',4,1);

-- fasilitas
CREATE TABLE fasilitas (id_fasilitas INT AUTO_INCREMENT, nama_fasilitas VARCHAR(100) NOT NULL, PRIMARY KEY (id_fasilitas));
INSERT INTO fasilitas (nama_fasilitas) VALUES ('AC'),('TV'),('WiFi'),('Kulkas'),('Bathtub'),('Breakfast'),('Mini Bar'),('Balkon');

-- kamar_fasilitas
CREATE TABLE kamar_fasilitas (id INT AUTO_INCREMENT, kamar_id INT, fasilitas_id INT, PRIMARY KEY (id), FOREIGN KEY (kamar_id) REFERENCES kamar(id_kamar) ON DELETE CASCADE, FOREIGN KEY (fasilitas_id) REFERENCES fasilitas(id_fasilitas) ON DELETE CASCADE);
INSERT INTO kamar_fasilitas (kamar_id, fasilitas_id) VALUES
(1,1),(1,2),(1,3),(2,1),(2,2),(2,3),(5,1),(5,2),(5,3),(5,4),(5,5),(6,1),(6,2),(6,3),(6,4),(9,1),(9,2),(9,3),(9,6),(9,8);

-- status_reservasi
CREATE TABLE status_reservasi (id_status_reservasi INT AUTO_INCREMENT, status VARCHAR(50) NOT NULL, PRIMARY KEY (id_status_reservasi));
INSERT INTO status_reservasi (status) VALUES ('Baru'),('Check-in'),('Check-out'),('Dibatalkan');

-- promo
CREATE TABLE promo (id_promo INT AUTO_INCREMENT, kode VARCHAR(20) NOT NULL UNIQUE, diskon DECIMAL(5,2) NOT NULL, tanggal_mulai DATE, tanggal_selesai DATE, PRIMARY KEY (id_promo));
INSERT INTO promo (kode, diskon, tanggal_mulai, tanggal_selesai) VALUES ('NEWYEAR',20.00,'2025-12-20','2026-01-05'),('WEEKEND',15.00,'2026-01-01','2026-12-31'),('LOYALTY',10.00,'2026-01-01','2026-12-31');

-- reservasi
CREATE TABLE reservasi (
    id_reservasi INT AUTO_INCREMENT, tamu_id INT, tanggal_pesan TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status_reservasi_id INT, promo_id INT,
    PRIMARY KEY (id_reservasi), FOREIGN KEY (tamu_id) REFERENCES tamu(id_tamu) ON DELETE SET NULL,
    FOREIGN KEY (status_reservasi_id) REFERENCES status_reservasi(id_status_reservasi) ON DELETE SET NULL,
    FOREIGN KEY (promo_id) REFERENCES promo(id_promo) ON DELETE SET NULL
);
INSERT INTO reservasi (tamu_id, status_reservasi_id, promo_id) VALUES
(1,3,NULL),(1,2,3),(2,3,NULL),(2,2,NULL),(3,3,1),(4,2,2),(5,1,NULL),(6,1,3),(7,4,NULL),(8,1,NULL);

-- detail_reservasi
CREATE TABLE detail_reservasi (
    id_detail INT AUTO_INCREMENT, reservasi_id INT, kamar_id INT,
    tanggal_checkin DATE NOT NULL, tanggal_checkout DATE NOT NULL, harga DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id_detail), FOREIGN KEY (reservasi_id) REFERENCES reservasi(id_reservasi) ON DELETE CASCADE,
    FOREIGN KEY (kamar_id) REFERENCES kamar(id_kamar) ON DELETE SET NULL
);
INSERT INTO detail_reservasi (reservasi_id, kamar_id, tanggal_checkin, tanggal_checkout, harga) VALUES
(1,3,'2026-01-10','2026-01-12',350000.00),(1,5,'2026-01-10','2026-01-12',600000.00),
(2,6,'2026-01-15','2026-01-17',600000.00),(3,10,'2026-01-11','2026-01-13',850000.00),
(4,9,'2026-01-16','2026-01-18',850000.00),(5,14,'2025-12-25','2025-12-28',1500000.00),
(6,1,'2026-01-14','2026-01-16',350000.00),(6,2,'2026-01-14','2026-01-16',350000.00),
(7,4,'2026-01-20','2026-01-22',350000.00),(8,7,'2026-01-18','2026-01-20',600000.00),
(9,13,'2026-01-05','2026-01-07',1500000.00),(10,11,'2026-01-22','2026-01-24',850000.00);

-- metode_pembayaran
CREATE TABLE metode_pembayaran (id_metode INT AUTO_INCREMENT, nama_metode VARCHAR(50) NOT NULL, PRIMARY KEY (id_metode));
INSERT INTO metode_pembayaran (nama_metode) VALUES ('Tunai'),('Transfer Bank'),('Kartu Kredit');

-- pembayaran
CREATE TABLE pembayaran (
    id_pembayaran INT AUTO_INCREMENT, reservasi_id INT UNIQUE, metode_id INT,
    total DECIMAL(10,2) NOT NULL, tanggal_bayar TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_pembayaran), FOREIGN KEY (reservasi_id) REFERENCES reservasi(id_reservasi) ON DELETE CASCADE,
    FOREIGN KEY (metode_id) REFERENCES metode_pembayaran(id_metode) ON DELETE SET NULL
);
INSERT INTO pembayaran (reservasi_id, metode_id, total) VALUES
(1,2,950000.00),(2,3,540000.00),(3,1,850000.00),(4,2,850000.00),
(5,3,1200000.00),(6,2,595000.00),(7,1,350000.00),(8,3,540000.00);

-- invoice
CREATE TABLE invoice (id_invoice INT AUTO_INCREMENT, reservasi_id INT UNIQUE, nomor_invoice VARCHAR(50) NOT NULL UNIQUE, tanggal_terbit TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id_invoice), FOREIGN KEY (reservasi_id) REFERENCES reservasi(id_reservasi) ON DELETE CASCADE);
INSERT INTO invoice (reservasi_id, nomor_invoice) VALUES (1,'INV-202601-001'),(2,'INV-202601-002'),(3,'INV-202601-003'),(4,'INV-202601-004'),(5,'INV-202512-001'),(6,'INV-202601-005'),(7,'INV-202601-006'),(8,'INV-202601-007');

-- checkin
CREATE TABLE checkin (id_checkin INT AUTO_INCREMENT, reservasi_id INT UNIQUE, pegawai_id INT, waktu_checkin TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id_checkin), FOREIGN KEY (reservasi_id) REFERENCES reservasi(id_reservasi) ON DELETE CASCADE, FOREIGN KEY (pegawai_id) REFERENCES pegawai(id_pegawai) ON DELETE SET NULL);
INSERT INTO checkin (reservasi_id, pegawai_id) VALUES (1,2),(2,3),(3,4),(4,2),(5,3),(6,4),(7,2),(8,3);

-- checkout
CREATE TABLE checkout (id_checkout INT AUTO_INCREMENT, reservasi_id INT UNIQUE, pegawai_id INT, waktu_checkout TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id_checkout), FOREIGN KEY (reservasi_id) REFERENCES reservasi(id_reservasi) ON DELETE CASCADE, FOREIGN KEY (pegawai_id) REFERENCES pegawai(id_pegawai) ON DELETE SET NULL);
INSERT INTO checkout (reservasi_id, pegawai_id) VALUES (1,2),(3,4),(5,3);

-- ulasan
CREATE TABLE ulasan (
    id_ulasan INT AUTO_INCREMENT, tamu_id INT, kamar_id INT, rating TINYINT CHECK (rating BETWEEN 1 AND 5),
    komentar TEXT, tanggal DATE,
    PRIMARY KEY (id_ulasan), FOREIGN KEY (tamu_id) REFERENCES tamu(id_tamu) ON DELETE CASCADE,
    FOREIGN KEY (kamar_id) REFERENCES kamar(id_kamar) ON DELETE CASCADE
);
INSERT INTO ulasan (tamu_id, kamar_id, rating, komentar, tanggal) VALUES
(1,3,4,'Kamar nyaman, AC dingin','2026-01-12'),(1,5,5,'Deluxe worth it!','2026-01-12'),
(2,10,3,'Superior biasa aja','2026-01-13'),(3,14,5,'Suite mewah banget!','2025-12-28'),
(4,1,4,'Standard sesuai harga','2026-01-16'),(6,7,2,'Kamar berisik, kurang ok','2026-01-20');

-- audit_log
CREATE TABLE audit_log (id_log INT AUTO_INCREMENT, aktivitas VARCHAR(255) NOT NULL, user_id INT, tanggal TIMESTAMP DEFAULT CURRENT_TIMESTAMP, ip_address VARCHAR(45), PRIMARY KEY (id_log), FOREIGN KEY (user_id) REFERENCES users(id_user) ON DELETE SET NULL);
INSERT INTO audit_log (aktivitas, user_id, ip_address) VALUES
('Login admin1',1,'192.168.1.10'),('Tambah reservasi untuk Andi',1,'192.168.1.10'),
('Proses check-in reservasi #1',3,'192.168.1.20'),('Login resepsionis1',3,'192.168.1.20'),
('Proses pembayaran reservasi #2',1,'192.168.1.10'),('Generate invoice INV-202601-001',4,'192.168.1.30'),
('Proses checkout reservasi #1',2,'192.168.1.15'),('Login budi_tamu',7,'10.0.0.5');
