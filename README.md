# Hotelku_DB — Sistem Reservasi Hotel

Project akhir mata kuliah **Sistem Basis Data** — Program Studi Sistem Informasi.

---

## Anggota Kelompok

| Nama | Tugas |
|---|---|
| **Sardenggan** | Database, Backend API, Table Locking, Backup & Restore |
| **Intan** | Frontend HTML/CSS/JS |
| **Halwa** | Laporan BAB I–III, BAB VI, ERD, Normalisasi |
| **Raffa** | Laporan BAB VII–VIII, Slide Presentasi |
| **Raditya** | Laporan BAB IV, Video Demonstrasi |

---

## Teknologi

| Komponen | Teknologi |
|---|---|
| DBMS | MariaDB (MySQL) |
| Backend | PHP 8+ PDO |
| Server | Apache + PHP-FPM / PHP Built-in |
| Frontend | HTML, CSS, JavaScript (fetch API) |
| Tunnel | Cloudflare Tunnel (cloudflared) |

---

## Alur Sistem (Flowchart)

```
                          ┌──────────┐
                          │  TAMU    │
                          └────┬─────┘
                               │
                               ▼
                    ┌──────────────────┐
                    │  Pilih kamar &   │
                    │  tanggal checkin │
                    └────────┬─────────┘
                             │
                             ▼
                    ┌──────────────────┐
                    │ Cek ketersediaan │
                    │   kamar          │
                    └────────┬─────────┘
                             │
              ┌──────────────┴──────────────┐
              │                             │
              ▼                             ▼
     ┌────────────────┐           ┌─────────────────┐
     │   TERSEDIA     │           │     PENUH       │
     │                │           │                 │
     │ Input data     │           │ Cari kamar lain │
     │ tamu + promo   │           │   atau batal    │
     └────────┬───────┘           └────────┬────────┘
              │                            │
              ▼                            │
     ┌────────────────┐                    │
     │  Proses bayar  │                    │
     │  + invoice     │                    │
     └────────┬───────┘                    │
              │                            │
              ▼                            │
     ┌────────────────┐                    │
     │   CHECK-IN     │                    │
     │ (update status │                    │
     │  kamar→terisi) │                    │
     └────────┬───────┘                    │
              │                            │
              ▼                            │
     ┌────────────────┐                    │
     │   MENGINAP     │                    │
     └────────┬───────┘                    │
              │                            │
              ▼                            │
     ┌────────────────┐                    │
     │   CHECK-OUT    │                    │
     │ (update status │                    │
     │  kamar→kosong) │                    │
     └────────┬───────┘                    │
              │                            │
              ▼                            │
     ┌────────────────┐                    │
     │  Beri ulasan   │                    │
     │  & rating      │                    │
     └────────┬───────┘                    │
              │                            │
              ▼                            ▼
          ┌──────────────────────────────────┐
          │           SELESAI                │
          └──────────────────────────────────┘
```

---

## Struktur Project

```
hotelku_db/
│
├── database/
│   ├── Hotelku_DB.sql          ← DDL + DML (21 tabel, ~155 data)
│   └── fitur_lanjutan.sql      ← Trigger, Function, SP, Cursor, TCL, Laporan
│
├── backend/
│   ├── config/
│   │   └── database.php        ← Koneksi PDO ke MySQL
│   ├── helpers/
│   │   └── functions.php       ← Helper + auth token middleware
│   ├── api/
│   │   ├── auth.php            ← Login / Register (return token)
│   │   ├── dashboard.php       ← Ringkasan hotel
│   │   ├── rooms.php           ← CRUD kamar
│   │   ├── bookings.php        ← Reservasi + checkin/checkout
│   │   ├── guests.php          ← Data tamu
│   │   ├── payments.php        ← Pembayaran + invoice
│   │   ├── report.php          ← 5 laporan (okupansi, pendapatan, dll)
│   │   ├── promo.php           ─
│   │   ├── messages.php        ─ (endpoint tambahan)
│   │   ├── housekeeping.php    ─
│   │   ├── employees.php       ─
│   │   ├── reports.php         ─
│   │   ├── jabatan.php         ─
│   │   └── invoice.php         ─
│   └── tests/
│       └── locking_test.php    ← Demo LOCK READ / WRITE
│
├── frontend/
│   ├── index.html              ← Halaman utama
│   ├── sign-in.html            ← Login
│   ├── sign-up.html            ← Register
│   ├── admin-dashboard.html    ← Dashboard admin
│   ├── receptionist-dashboard.html  ← Dashboard resepsionis
│   ├── housekeeping-dashboard.html  ← Dashboard housekeeping
│   ├── booking.html            ← Reservasi kamar
│   ├── payment.html            ← Pembayaran
│   ├── invoice.html            ← Invoice
│   ├── profile.html            ← Profil user
│   ├── *.html                  ← Halaman lainnya
│   ├── assets/                 ← CSS, JS, gambar
│   └── uploads/                ← File upload
│
├── database_export.sql         ← Hasil backup database
├── hotelku_db_backup.sql       ← Backup alternatif
├── hotelku_db_schema.sql       ← Schema saja (tanpa data)
└── README.md
```

---

## 21 Tabel Database

### Master

| No | Tabel | Keterangan |
|---|---|---|
| 1 | `roles` | Role pengguna (admin, resepsionis, tamu) |
| 2 | `users` | Akun login pengguna |
| 3 | `jabatan` | Jabatan pegawai |
| 4 | `pegawai` | Data pegawai hotel |
| 5 | `tamu` | Data tamu hotel |
| 6 | `tipe_kamar` | Tipe kamar (Standard, Deluxe, Superior, Suite) |
| 7 | `status_kamar` | Status kamar (Tersedia, Terisi, Maintenance) |
| 8 | `kamar` | Data kamar hotel |
| 9 | `fasilitas` | Daftar fasilitas |
| 10 | `kamar_fasilitas` | Relasi kamar ↔ fasilitas |
| 11 | `status_reservasi` | Status reservasi (Baru, Check-in, Check-out, Dibatalkan) |
| 12 | `promo` | Kode promo & diskon |
| 13 | `metode_pembayaran` | Metode bayar (Tunai, Transfer, Kartu Kredit) |

### Transaksi

| No | Tabel | Keterangan |
|---|---|---|
| 14 | `reservasi` | Data reservasi/booking |
| 15 | `detail_reservasi` | Detail kamar & tanggal reservasi |
| 16 | `pembayaran` | Data pembayaran |
| 17 | `invoice` | Data invoice |
| 18 | `checkin` | Log check-in |
| 19 | `checkout` | Log check-out |
| 20 | `ulasan` | Ulasan & rating dari tamu |

### Lainnya

| No | Tabel | Keterangan |
|---|---|---|
| 21 | `audit_log` | Log aktivitas sistem |

> **Total: 21 tabel** — memenuhi syarat minimal 20 tabel.

---

## Cara Install & Jalanin

### 1. Import Database

```bash
# Login MySQL
mysql -u root -p

# Di dalam MySQL:
CREATE DATABASE IF NOT EXISTS Hotelku_DB;
USE Hotelku_DB;
SOURCE /path/to/hotelku_db/database/Hotelku_DB.sql;
SOURCE /path/to/hotelku_db/database/fitur_lanjutan.sql;
EXIT;
```

Atau langsung dari terminal:

```bash
mysql -u root -p < database/Hotelku_DB.sql
mysql -u root -p < database/fitur_lanjutan.sql
```

### 2. Jalankan Server

#### Opsi A — PHP Built-in Server (recommended, gampang)

```bash
cd /path/to/hotelku_db
php -S 0.0.0.0:8080 -t .
```

Akses: **http://localhost:8080/frontend/index.html**

#### Opsi B — Apache (via symlink)

```bash
sudo ln -s /path/to/hotelku_db /var/www/html/hotelku_db
```

Akses: **http://localhost/hotelku_db/frontend/index.html**

### 3. Tunnel (biar bisa diakses dari luar)

```bash
cloudflared tunnel --url http://localhost:8080
```

Nanti dapet URL: **https://xxx.trycloudflare.com**

Akses via tunnel: **https://xxx.trycloudflare.com/frontend/index.html**

> **Catatan:** Jangan tutup terminal selama tunnel masih dipake. URL berubah setiap restart.

---

## Fitur Database & Cara Ngetes

### Trigger (3)

| Trigger | Fungsi |
|---|---|
| `after_insert_reservasi` | Catat log otomatis saat reservasi baru |
| `before_insert_detail_reservasi` | Validasi tanggal checkout > checkin |
| `after_update_status_reservasi` | Update status kamar saat reservasi berubah |

```sql
-- Test trigger 1: INSERT reservasi → audit_log terisi otomatis
INSERT INTO reservasi (tamu_id, status_reservasi_id) VALUES (1, 1);
SELECT * FROM audit_log;

-- Test trigger 2: checkout <= checkin → error
INSERT INTO detail_reservasi (reservasi_id, kamar_id, tanggal_checkin, tanggal_checkout, harga)
VALUES (1, 1, '2026-02-05', '2026-02-03', 350000);

-- Test trigger 3: update status reservasi → status kamar berubah
UPDATE reservasi SET status_reservasi_id = 2 WHERE id_reservasi = 11;
SELECT nomor_kamar, status_id FROM kamar WHERE id_kamar IN (SELECT kamar_id FROM detail_reservasi WHERE reservasi_id = 11);
```

### Function (2)

| Function | Fungsi |
|---|---|
| `hitung_total_harga(p_reservasi_id)` | Hitung total harga reservasi |
| `cek_ketersediaan_kamar(p_tipe_id, p_checkin, p_checkout)` | Cek jumlah kamar tersedia |

```sql
SELECT hitung_total_harga(1) AS total_harga;
SELECT cek_ketersediaan_kamar(1, '2026-02-01', '2026-02-05') AS kamar_tersedia;
```

### Aggregate Function (5)

```sql
-- 1. SUM — Total pendapatan
SELECT SUM(total) AS total_pendapatan FROM pembayaran;

-- 2. AVG — Rata-rata rating kamar
SELECT AVG(rating) AS rata_rata_rating FROM ulasan;

-- 3. MAX — Harga kamar termahal
SELECT MAX(harga) AS harga_termahal FROM tipe_kamar;

-- 4. MIN — Harga kamar termurah
SELECT MIN(harga) AS harga_termurah FROM tipe_kamar;

-- 5. COUNT — Jumlah reservasi per status
SELECT sr.status, COUNT(r.id_reservasi) AS jumlah
FROM status_reservasi sr LEFT JOIN reservasi r
    ON sr.id_status_reservasi = r.status_reservasi_id
GROUP BY sr.id_status_reservasi, sr.status;
```

### TCL (COMMIT / ROLLBACK)

```sql
-- COMMIT — Data tersimpan permanen
START TRANSACTION;
INSERT INTO reservasi (tamu_id, status_reservasi_id) VALUES (9, 1);
SET @id_baru = LAST_INSERT_ID();
INSERT INTO detail_reservasi (reservasi_id, kamar_id, tanggal_checkin, tanggal_checkout, harga)
    VALUES (@id_baru, 12, '2026-02-01', '2026-02-03', 850000);
COMMIT;
SELECT 'Data berhasil disimpan' AS hasil;

-- ROLLBACK — Data dibatalkan
START TRANSACTION;
INSERT INTO reservasi (tamu_id, status_reservasi_id) VALUES (10, 1);
SET @id_gagal = LAST_INSERT_ID();
INSERT INTO detail_reservasi (reservasi_id, kamar_id, tanggal_checkin, tanggal_checkout, harga)
    VALUES (@id_gagal, 3, '2026-02-01', '2026-02-03', 350000);
ROLLBACK;
SELECT 'Data tidak jadi disimpan' AS hasil;
```

### Table Locking

```bash
php backend/tests/locking_test.php
```

Output:
```
=== HOTELKU - Table Locking Demo ===

[1] Melakukan LOCK TABLES reservasi WRITE...
    ✅ Tabel reservasi berhasil dikunci (WRITE lock)

[2] Mencoba INSERT ke tabel yang dikunci...
    ✅ INSERT berhasil!

[3] Melepaskan kunci (UNLOCK TABLES)...
    ✅ Kunci dilepaskan.

[4] Demonstrasi READ LOCK...
    ✅ Tabel kamar dikunci dengan READ lock
    ✅ Membaca data kamar: 16 kamar ditemukan
    ⚠️  WRITE ke tabel yang di-LOCK READ akan ditolak
```

### Stored Procedure (3)

| SP | Fungsi |
|---|---|
| `tambah_reservasi` | Tambah reservasi baru (dengan transaksi) |
| `proses_checkout` | Proses check-out tamu |
| `generate_laporan_harian` | Generate laporan harian (dengan cursor) |

```sql
-- SP 1: Tambah reservasi
CALL tambah_reservasi(1, 1, '2026-02-01', '2026-02-03', NULL);

-- SP 2: Proses checkout
CALL proses_checkout(1, 1);

-- SP 3: Generate laporan harian
CALL generate_laporan_harian('2026-01-12');
```

### Cursor

Cursor digunakan di dalam SP `generate_laporan_harian` untuk iterasi data pembayaran per hari.

### Backup & Restore

```bash
# Backup database
mysqldump -u root -p Hotelku_DB > backup_hotelku.sql

# Restore database
mysql -u root -p Hotelku_DB < backup_hotelku.sql
```

---

## 5 Laporan Manajemen

| No | Laporan | Query |
|---|---|---|
| 1 | **Okupansi Kamar** | Persentase kamar terisi per bulan |
| 2 | **Pendapatan Bulanan** | Total pendapatan per bulan |
| 3 | **5 Tamu Paling Aktif** | Tamu dengan booking terbanyak |
| 4 | **Rating per Tipe Kamar** | Rata-rata rating per tipe kamar |
| 5 | **Efektivitas Promo** | Jumlah penggunaan kode promo |

Jalanin semua laporan:

```bash
mysql -u root -p < database/fitur_lanjutan.sql
```

Atau satu per satu dari baris 228–257 file `fitur_lanjutan.sql`.

---

## API Endpoint

| Method | Endpoint | Deskripsi |
|---|---|---|
| `POST` | `/backend/api/auth.php?action=login` | Login user |
| `POST` | `/backend/api/auth.php?action=register` | Register user |
| `GET` | `/backend/api/dashboard.php` | Ringkasan hotel |
| `GET` | `/backend/api/rooms.php` | Data kamar |
| `GET` | `/backend/api/bookings.php` | Data reservasi |
| `GET` | `/backend/api/guests.php` | Data tamu |
| `GET` | `/backend/api/payments.php` | Data pembayaran |
| `GET` | `/backend/api/report.php` | Laporan manajemen |

**Contoh login:**

```bash
curl -X POST http://localhost:8080/backend/api/auth.php?action=login \
  -H "Content-Type: application/json" \
  -d '{"username": "admin1", "password": "password123"}'
```

**Contoh akses endpoint (pake token):**

```bash
curl http://localhost:8080/backend/api/dashboard.php \
  -H "Authorization: Bearer <token_dari_login>"
```

---

## Akun Test

| Username | Password | Role |
|---|---|---|
| `admin1` | `password123` | Admin |
| `admin2` | `password123` | Admin |
| `resepsionis1` | `password123` | Resepsionis |
| `resepsionis2` | `password123` | Resepsionis |
| `resepsionis3` | `password123` | Resepsionis |
| `andi_tamu` | `password123` | Tamu |
| `budi_tamu` | `password123` | Tamu |
| `citra_tamu` | `password123` | Tamu |

---

## Lisensi

Dibuat untuk keperluan akademik — Mata Kuliah Sistem Basis Data, Program Studi Sistem Informasi.
