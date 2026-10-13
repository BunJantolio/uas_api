-- ============================================
-- CREATE DATABASE
-- ============================================
CREATE DATABASE IF NOT EXISTS uasdb
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE uasdb;

-- ============================================
-- 1. TABEL USERS
-- ============================================
CREATE TABLE users (
  id_user    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(50)  NOT NULL UNIQUE,
  email      VARCHAR(100) NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  role       ENUM('admin', 'dokter', 'pasien') NOT NULL DEFAULT 'pasien', -- hapus 'petugas'
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- 2. TABEL PASIEN
-- ============================================
CREATE TABLE pasien (
  id_pasien      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_user        INT UNSIGNED NOT NULL,
  no_rekam_medis VARCHAR(20)  NOT NULL UNIQUE,
  nama_lengkap   VARCHAR(100) NOT NULL,
  nik            CHAR(16)     NOT NULL UNIQUE,
  tempat_lahir   VARCHAR(50)  NOT NULL,
  tanggal_lahir  DATE         NOT NULL,
  jenis_kelamin  ENUM('L', 'P') NOT NULL,
  golongan_darah ENUM('A', 'B', 'AB', 'O', '-') DEFAULT '-',
  alamat         TEXT         NOT NULL,
  no_telepon     VARCHAR(15)  NOT NULL,
  asuransi       ENUM('umum', 'bpjs', 'asuransi_swasta') NOT NULL DEFAULT 'umum',
  no_asuransi    VARCHAR(30)  DEFAULT NULL,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pasien_user FOREIGN KEY (id_user)
    REFERENCES users(id_user) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 3. TABEL ADMIN
-- ============================================
CREATE TABLE admin (
  id_admin     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_user      INT UNSIGNED NOT NULL UNIQUE,
  nama_lengkap VARCHAR(100) NOT NULL,
  nip          VARCHAR(20)  NOT NULL UNIQUE,
  no_telepon   VARCHAR(15)  NOT NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_admin_user FOREIGN KEY (id_user)
    REFERENCES users(id_user) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 4. TABEL DOKTER
-- ============================================
CREATE TABLE dokter (
  id_dokter    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_user      INT UNSIGNED NOT NULL UNIQUE,
  nama_lengkap VARCHAR(100) NOT NULL,
  nip          VARCHAR(20)  NOT NULL UNIQUE,
  spesialisasi VARCHAR(100) NOT NULL,
  no_str       VARCHAR(30)  NOT NULL UNIQUE, -- nomor surat tanda registrasi
  no_telepon   VARCHAR(15)  NOT NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_dokter_user FOREIGN KEY (id_user)
    REFERENCES users(id_user) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 5. TABEL PENDAFTARAN
-- input oleh admin, bukan petugas
-- ============================================
CREATE TABLE pendaftaran (
  id_pendaftaran INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_pasien      INT UNSIGNED NOT NULL,
  id_admin       INT UNSIGNED NOT NULL, -- ganti dari id_petugas
  id_dokter      INT UNSIGNED NOT NULL, -- dokter tujuan ditentukan saat daftar
  no_antrian     VARCHAR(10)  NOT NULL,
  poli           VARCHAR(50)  NOT NULL,
  tanggal_daftar DATE         NOT NULL,
  keluhan        TEXT         NOT NULL,
  status         ENUM('menunggu', 'sedang_diperiksa', 'selesai', 'batal') NOT NULL DEFAULT 'menunggu',
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pendaftaran_pasien FOREIGN KEY (id_pasien)
    REFERENCES pasien(id_pasien)  ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_pendaftaran_admin  FOREIGN KEY (id_admin)
    REFERENCES admin(id_admin)    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_pendaftaran_dokter FOREIGN KEY (id_dokter)
    REFERENCES dokter(id_dokter)  ON DELETE RESTRICT ON UPDATE CASCADE,
  UNIQUE KEY uq_antrian (no_antrian, tanggal_daftar, poli) -- cegah duplikat antrian
) ENGINE=InnoDB;

-- ============================================
-- 6. TABEL PEMERIKSAAN
-- ============================================
CREATE TABLE pemeriksaan (
  id_pemeriksaan  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_pendaftaran  INT UNSIGNED NOT NULL UNIQUE, -- 1 pendaftaran = 1 pemeriksaan
  id_dokter       INT UNSIGNED NOT NULL,
  tanggal_periksa DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tekanan_darah   VARCHAR(10)  DEFAULT NULL,
  suhu_tubuh      DECIMAL(4,1) DEFAULT NULL,
  berat_badan     DECIMAL(5,2) DEFAULT NULL,
  tinggi_badan    DECIMAL(5,2) DEFAULT NULL,
  catatan_klinis  TEXT         DEFAULT NULL,
  diagnosa        TEXT         NOT NULL,
  tindakan        TEXT         DEFAULT NULL,
  status          ENUM('proses', 'selesai') NOT NULL DEFAULT 'proses',
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pemeriksaan_pendaftaran FOREIGN KEY (id_pendaftaran)
    REFERENCES pendaftaran(id_pendaftaran) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_pemeriksaan_dokter FOREIGN KEY (id_dokter)
    REFERENCES dokter(id_dokter) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 7. TABEL RESEP OBAT
-- ============================================
CREATE TABLE resep_obat (
  id_resep       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_pemeriksaan INT UNSIGNED NOT NULL,
  nama_obat      VARCHAR(100) NOT NULL,
  dosis          VARCHAR(50)  NOT NULL,
  frekuensi      VARCHAR(50)  NOT NULL,
  jumlah         INT UNSIGNED NOT NULL,
  keterangan     TEXT         DEFAULT NULL,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_resep_pemeriksaan FOREIGN KEY (id_pemeriksaan)
    REFERENCES pemeriksaan(id_pemeriksaan) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 8. TABEL PEMBAYARAN
-- ============================================
CREATE TABLE pembayaran (
  id_pembayaran     INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  id_pendaftaran    INT UNSIGNED  NOT NULL UNIQUE, -- 1 pendaftaran = 1 tagihan
  id_pemeriksaan    INT UNSIGNED  DEFAULT NULL,
  id_admin          INT UNSIGNED  NOT NULL,        -- admin yang proses pembayaran
  no_invoice        VARCHAR(30)   NOT NULL UNIQUE,
  tanggal_bayar     DATETIME      DEFAULT NULL,
  biaya_pendaftaran DECIMAL(12,2) NOT NULL DEFAULT 0,
  biaya_pemeriksaan DECIMAL(12,2) NOT NULL DEFAULT 0,
  biaya_obat        DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_tagihan     DECIMAL(12,2) GENERATED ALWAYS AS  -- computed, anti inkonsistensi
    (biaya_pendaftaran + biaya_pemeriksaan + biaya_obat) STORED,
  jenis_pembayaran  ENUM('umum', 'bpjs', 'asuransi_swasta') NOT NULL DEFAULT 'umum',
  no_klaim          VARCHAR(50)   DEFAULT NULL,
  jumlah_dibayar    DECIMAL(12,2) DEFAULT NULL,
  status            ENUM('belum_bayar', 'lunas', 'ditanggung_asuransi') NOT NULL DEFAULT 'belum_bayar',
  keterangan        TEXT          DEFAULT NULL,
  created_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_bayar_pendaftaran FOREIGN KEY (id_pendaftaran)
    REFERENCES pendaftaran(id_pendaftaran) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_bayar_pemeriksaan FOREIGN KEY (id_pemeriksaan)
    REFERENCES pemeriksaan(id_pemeriksaan) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_bayar_admin FOREIGN KEY (id_admin)
    REFERENCES admin(id_admin) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;