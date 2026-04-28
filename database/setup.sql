-- Create Database
CREATE DATABASE IF NOT EXISTS arsip_surat;
USE arsip_surat;

-- Table for Bidang
CREATE TABLE IF NOT EXISTS bidang (
    id_bidang INT(11) AUTO_INCREMENT PRIMARY KEY,
    nama_bidang VARCHAR(255) NOT NULL
);

-- Table for Seksi
CREATE TABLE IF NOT EXISTS seksi (
    id_seksi INT(11) AUTO_INCREMENT PRIMARY KEY,
    nama_seksi VARCHAR(255) NOT NULL,
    id_bidang INT(11),
    FOREIGN KEY (id_bidang) REFERENCES bidang(id_bidang) ON DELETE CASCADE
);

-- Table for Users
CREATE TABLE IF NOT EXISTS users (
    nip VARCHAR(20) PRIMARY KEY,
    nama VARCHAR(255) NOT NULL,
    no_hp VARCHAR(20),
    asal_instansi VARCHAR(255),
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    jabatan VARCHAR(255),
    id_bidang INT(11),
    id_seksi INT(11),
    role ENUM('sekretariat', 'kepala_dinas', 'admin_bidang', 'bagian_perencanaan', 'bagian_keuangan', 'staff', 'user') NOT NULL,
    foto VARCHAR(255) DEFAULT 'default.png',
    FOREIGN KEY (id_bidang) REFERENCES bidang(id_bidang) ON DELETE SET NULL,
    FOREIGN KEY (id_seksi) REFERENCES seksi(id_seksi) ON DELETE SET NULL
);

-- Table for Surat Masuk
CREATE TABLE IF NOT EXISTS surat_masuk (
    id_surat_masuk BIGINT AUTO_INCREMENT PRIMARY KEY,
    nomor_agenda VARCHAR(30) UNIQUE NOT NULL,
    nomor_surat VARCHAR(50) NOT NULL,
    tanggal_surat DATE NOT NULL,
    tanggal_terima DATE NOT NULL,
    pengirim VARCHAR(150) NOT NULL,
    perihal VARCHAR(255) NOT NULL,
    sifat_surat ENUM('biasa', 'penting', 'segera', 'rahasia') DEFAULT 'biasa',
    lampiran INT DEFAULT 0,
    file_path VARCHAR(255),
    input_by VARCHAR(20),
    status ENUM('tercatat', 'didispokan', 'diteruskan', 'selesai', 'diarsipkan') DEFAULT 'tercatat',
    id_bidang INT(11),
    id_seksi INT(11),
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (input_by) REFERENCES users(nip) ON DELETE SET NULL,
    FOREIGN KEY (id_bidang) REFERENCES bidang(id_bidang) ON DELETE SET NULL,
    FOREIGN KEY (id_seksi) REFERENCES seksi(id_seksi) ON DELETE SET NULL
);

-- Table for Disposisi
CREATE TABLE IF NOT EXISTS disposisi (
    id_disposisi BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_surat_masuk BIGINT NOT NULL,
    nip_pemberi VARCHAR(20),
    tanggal_disposisi DATETIME DEFAULT CURRENT_TIMESTAMP,
    id_bidang INT(11),
    id_seksi INT(11),
    isi_disposisi TEXT,
    sifat_disposisi ENUM('biasa', 'penting', 'segera', 'rahasia') DEFAULT 'biasa',
    status_disposisi ENUM('baru', 'diterima', 'ditindaklanjuti') DEFAULT 'baru',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_surat_masuk) REFERENCES surat_masuk(id_surat_masuk) ON DELETE CASCADE,
    FOREIGN KEY (nip_pemberi) REFERENCES users(nip) ON DELETE SET NULL,
    FOREIGN KEY (id_bidang) REFERENCES bidang(id_bidang) ON DELETE SET NULL,
    FOREIGN KEY (id_seksi) REFERENCES seksi(id_seksi) ON DELETE SET NULL
);

-- Table for Surat Keluar
CREATE TABLE IF NOT EXISTS surat_keluar (
    id_surat_keluar BIGINT AUTO_INCREMENT PRIMARY KEY,
    nomor_surat_keluar VARCHAR(50) UNIQUE NOT NULL,
    tanggal_surat DATE NOT NULL,
    perihal VARCHAR(255) NOT NULL,
    id_surat_masuk BIGINT,
    tujuan VARCHAR(150) NOT NULL,
    file_path VARCHAR(255),
    uploaded_by VARCHAR(20),
    status ENUM('draft', 'disetujui', 'diarsipkan') DEFAULT 'draft',
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_surat_masuk) REFERENCES surat_masuk(id_surat_masuk) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(nip) ON DELETE SET NULL
);

-- SEEDING CLEANUP & RESTRUCTURE
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE seksi;
TRUNCATE TABLE bidang;
SET FOREIGN_KEY_CHECKS = 1;

-- Seed Data for Bidang (Exact 7 Categories)
INSERT INTO bidang (id_bidang, nama_bidang) VALUES
(1, 'Bidang Pembinaan SD'),
(2, 'Bidang Pembinaan SMP'),
(3, 'Bidang Pembinaan PAUD dan Pendidikan Non Formal'),
(4, 'Bidang Pembinaan Ketenagaan'),
(5, 'Bidang Kebudayaan'),
(6, 'Bagian Analis Keuangan Pusat dan Daerah Muda Sub- Koordinator Keuangan'),
(7, 'Bagian Perencanan Muda Sub-Koordinator Perencanaan');

-- Seed Data for Seksi (Mapping to Exact IDs)
INSERT INTO seksi (nama_seksi, id_bidang) VALUES
-- Bidang Pembinaan PAUD dan Pendidikan Non Formal (ID 3)
('Seksi Kelembagaan dan Sarana Prasarana', 3),
('Seksi Peserta Didik dan Pembangunan Karakter', 3),
('Pengembang Teknologi Pembelajaran Ahli Muda', 3),
-- Bidang Pembinaan SD (ID 1)
('Seksi Kelembagaan dan Sarana Prasarana (Plt)', 1),
('Seksi Peserta Didik dan Pembangunan Karakter', 1),
('Pengembang Penilaian Pendidikan', 1),
-- Bidang Pembinaan SMP (ID 2)
('Seksi Kelembagaan dan Sarana Prasarana', 2),
('Seksi Peserta Didik dan Pembangunan Karakter', 2),
('Pranata Laboratorium Pendidikan Ahli Muda', 2),
-- Bidang Kebudayaan (ID 5)
('Pamong Budaya Ahli Muda', 5),
-- Bidang Pembinaan Ketenagaan (ID 4)
('Seksi PTK PAUD & Pendidikan Nonformal serta Tenaga Kebudayaan', 4),
('Seksi Penyelenggaraan Tugas Pembantuan', 4),
('Pengembang Kurikulum Ahli Muda', 4);

-- Seed Data for Users (Admin Sekretariat)
INSERT INTO users (nip, nama, email, password, role) VALUES
('12345678', 'Admin Sekretariat', 'sekretariat@kemendikbud.go.id', '$2y$10$8k9F7p9.gRz07p8S.G2H5u8d0f1g2h3j4k5l6m7n8o9p0q1r2s3t4', 'sekretariat')
ON DUPLICATE KEY UPDATE role = 'sekretariat';
