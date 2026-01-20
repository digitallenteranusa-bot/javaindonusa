-- ============================================
-- BILLING ISP JAVA INDONUSA
-- Skema Database Tambahan - Sistem Penagih
-- ============================================

-- ============================================
-- 1. UPDATE TABEL USERS (Tambah Role Penagih)
-- ============================================
ALTER TABLE users
MODIFY COLUMN role ENUM('superadmin', 'admin', 'teknisi', 'kasir', 'penagih') DEFAULT 'admin';

-- Tambah kolom untuk penagih
ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER email;
ALTER TABLE users ADD COLUMN address TEXT NULL AFTER phone;
ALTER TABLE users ADD COLUMN photo VARCHAR(255) NULL AFTER address;
ALTER TABLE users ADD COLUMN commission_rate DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Persentase komisi penagih' AFTER photo;

-- ============================================
-- 2. UPDATE TABEL CUSTOMERS (Tambah Relasi Penagih)
-- ============================================
ALTER TABLE customers ADD COLUMN collector_id BIGINT UNSIGNED NULL AFTER area_id;
ALTER TABLE customers ADD COLUMN payment_behavior ENUM('regular', 'rapel', 'problematic') DEFAULT 'regular' COMMENT 'Kebiasaan bayar pelanggan';
ALTER TABLE customers ADD COLUMN rapel_months INT DEFAULT 0 COMMENT 'Jumlah bulan rapel yang diizinkan';
ALTER TABLE customers ADD COLUMN last_payment_date DATE NULL COMMENT 'Tanggal pembayaran terakhir';

ALTER TABLE customers ADD FOREIGN KEY (collector_id) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE customers ADD INDEX idx_customer_collector (collector_id);

-- ============================================
-- 3. TABEL COLLECTION_ASSIGNMENTS (Penugasan Penagih)
-- ============================================
CREATE TABLE collection_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    collector_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (collector_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,

    UNIQUE KEY unique_assignment (collector_id, customer_id),
    INDEX idx_assignment_collector (collector_id),
    INDEX idx_assignment_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. TABEL COLLECTION_LOGS (Log Penagihan)
-- ============================================
CREATE TABLE collection_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    collector_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NULL,

    -- Aksi yang dilakukan
    action_type ENUM(
        'visit',           -- Kunjungan
        'payment_cash',    -- Pembayaran tunai
        'payment_transfer', -- Pembayaran transfer
        'reminder_sent',   -- Kirim pengingat WA
        'promise_to_pay',  -- Janji bayar
        'not_home',        -- Tidak di rumah
        'refused',         -- Menolak bayar
        'rescheduled'      -- Jadwal ulang
    ) NOT NULL,

    -- Detail pembayaran (jika ada)
    amount DECIMAL(12,2) DEFAULT 0.00,
    payment_method ENUM('cash', 'transfer') NULL,
    transfer_proof VARCHAR(255) NULL COMMENT 'Path foto bukti transfer',

    -- Lokasi & waktu
    visit_time TIMESTAMP NULL,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,

    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (collector_id) REFERENCES users(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,

    INDEX idx_collection_collector (collector_id),
    INDEX idx_collection_customer (customer_id),
    INDEX idx_collection_date (created_at),
    INDEX idx_collection_action (action_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. TABEL EXPENSES (Pengeluaran/Petty Cash Penagih)
-- ============================================
CREATE TABLE expenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL COMMENT 'ID Penagih',

    amount DECIMAL(12,2) NOT NULL,
    category ENUM('fuel', 'food', 'transport', 'phone_credit', 'parking', 'other') NOT NULL,
    description TEXT NOT NULL COMMENT 'Keperluan belanja',
    receipt_photo VARCHAR(255) NULL COMMENT 'Foto nota/struk',

    -- Status verifikasi
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    verified_by BIGINT UNSIGNED NULL,
    verified_at TIMESTAMP NULL,
    rejection_reason VARCHAR(255) NULL,

    expense_date DATE NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_expense_user (user_id),
    INDEX idx_expense_date (expense_date),
    INDEX idx_expense_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. TABEL SETTLEMENTS (Setoran Penagih ke Kantor)
-- ============================================
CREATE TABLE settlements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    collector_id BIGINT UNSIGNED NOT NULL,
    settlement_number VARCHAR(30) UNIQUE NOT NULL COMMENT 'STL-YYYYMMDD-XXXXX',

    -- Periode setoran
    settlement_date DATE NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,

    -- Kalkulasi
    total_collection DECIMAL(12,2) NOT NULL COMMENT 'Total tagihan masuk',
    total_expense DECIMAL(12,2) NOT NULL COMMENT 'Total pengeluaran',
    commission_amount DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Komisi penagih',
    expected_amount DECIMAL(12,2) NOT NULL COMMENT 'Yang harus disetor',
    actual_amount DECIMAL(12,2) NULL COMMENT 'Yang disetor aktual',
    difference DECIMAL(12,2) NULL COMMENT 'Selisih (+ lebih, - kurang)',

    -- Status
    status ENUM('pending', 'verified', 'discrepancy', 'settled') DEFAULT 'pending',
    received_by BIGINT UNSIGNED NULL,
    verified_at TIMESTAMP NULL,
    notes TEXT NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (collector_id) REFERENCES users(id),
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_settlement_collector (collector_id),
    INDEX idx_settlement_date (settlement_date),
    INDEX idx_settlement_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. TABEL CUSTOMER_TOKENS (Login Pelanggan via HP)
-- ============================================
CREATE TABLE customer_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,

    token VARCHAR(64) UNIQUE NOT NULL,
    otp_code VARCHAR(6) NULL COMMENT 'OTP untuk verifikasi',
    otp_expires_at TIMESTAMP NULL,

    device_info TEXT NULL,
    ip_address VARCHAR(45) NULL,

    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,

    INDEX idx_token_customer (customer_id),
    INDEX idx_token_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. TABEL ISP_INFO (Informasi ISP untuk Pelanggan)
-- ============================================
CREATE TABLE isp_info (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    company_name VARCHAR(255) NOT NULL,
    tagline VARCHAR(255) NULL,

    -- Kontak
    phone_primary VARCHAR(20) NOT NULL,
    phone_secondary VARCHAR(20) NULL,
    whatsapp_number VARCHAR(20) NOT NULL,
    email VARCHAR(255) NULL,

    -- Alamat
    address TEXT NOT NULL,

    -- Rekening Bank
    bank_accounts JSON NOT NULL COMMENT '[{"bank":"BCA","account":"123456","name":"PT Java Indonusa"}]',

    -- E-Wallet
    ewallet_accounts JSON NULL COMMENT '[{"type":"OVO","number":"08123456"}]',

    -- Sosial Media
    social_media JSON NULL,

    -- Jam Operasional
    operational_hours VARCHAR(100) NULL,

    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. UPDATE TABEL PAYMENTS (Tambah Kolom Penagih)
-- ============================================
ALTER TABLE payments ADD COLUMN collector_id BIGINT UNSIGNED NULL AFTER received_by;
ALTER TABLE payments ADD COLUMN transfer_proof VARCHAR(255) NULL AFTER reference_number;
ALTER TABLE payments ADD COLUMN collection_log_id BIGINT UNSIGNED NULL;

ALTER TABLE payments ADD FOREIGN KEY (collector_id) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE payments ADD INDEX idx_payment_collector (collector_id);

-- ============================================
-- DATA AWAL
-- ============================================

-- Info ISP Default
INSERT INTO isp_info (
    company_name, tagline, phone_primary, whatsapp_number, email, address,
    bank_accounts, operational_hours
) VALUES (
    'Java Indonusa',
    'Internet Cepat & Stabil',
    '021-12345678',
    '6281234567890',
    'info@javaindonusa.com',
    'Jl. Contoh No. 123, Jakarta',
    '[{"bank":"BCA","account":"1234567890","name":"PT Java Indonusa"},{"bank":"Mandiri","account":"0987654321","name":"PT Java Indonusa"}]',
    'Senin - Sabtu: 08:00 - 17:00'
);

-- Kategori Pengeluaran Default di Settings
INSERT INTO settings (`group`, `key`, value, type, description) VALUES
('expense', 'categories', '["fuel","food","transport","phone_credit","parking","other"]', 'json', 'Kategori pengeluaran penagih'),
('expense', 'daily_limit', '100000', 'integer', 'Batas pengeluaran harian penagih'),
('collector', 'default_commission', '5', 'integer', 'Persentase komisi default penagih'),
('collector', 'auto_open_on_payment', 'true', 'boolean', 'Otomatis buka isolir saat pembayaran via penagih'),
('isolation', 'overdue_months', '2', 'integer', 'Jumlah bulan tunggakan sebelum isolir'),
('isolation', 'grace_days_after_due', '7', 'integer', 'Hari toleransi setelah jatuh tempo');
