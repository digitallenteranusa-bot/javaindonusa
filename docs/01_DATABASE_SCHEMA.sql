-- ============================================
-- BILLING ISP JAVA INDONUSA
-- Skema Database - Laravel Migration Ready
-- ============================================

-- ============================================
-- 1. TABEL USERS (Admin/Staff)
-- ============================================
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('superadmin', 'admin', 'teknisi', 'kasir') DEFAULT 'admin',
    phone VARCHAR(20) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. TABEL AREAS (Wilayah/Coverage)
-- ============================================
CREATE TABLE areas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. TABEL PACKAGES (Paket Internet)
-- ============================================
CREATE TABLE packages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    speed_download INT NOT NULL COMMENT 'dalam Kbps',
    speed_upload INT NOT NULL COMMENT 'dalam Kbps',
    price DECIMAL(12,2) NOT NULL,
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. TABEL ROUTERS (Mikrotik & OLT)
-- ============================================
CREATE TABLE routers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    brand ENUM('mikrotik', 'cisco', 'huawei', 'zte', 'fiberhome', 'other') NOT NULL,
    model VARCHAR(100) NULL,
    ip_address VARCHAR(45) NOT NULL,
    api_port INT DEFAULT 8728 COMMENT 'Port API Mikrotik',
    api_username VARCHAR(100) NOT NULL,
    api_password VARCHAR(255) NOT NULL,
    ssh_port INT DEFAULT 22,
    area_id BIGINT UNSIGNED NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_sync_at TIMESTAMP NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. TABEL CUSTOMERS (Pelanggan)
-- ============================================
CREATE TABLE customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id VARCHAR(20) UNIQUE NOT NULL COMMENT 'ID Pelanggan: PLG-XXXXX',
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(20) NOT NULL,
    phone_alt VARCHAR(20) NULL,
    nik VARCHAR(20) NULL,

    -- Alamat
    address TEXT NOT NULL,
    rt VARCHAR(5) NULL,
    rw VARCHAR(5) NULL,
    kelurahan VARCHAR(100) NULL,
    kecamatan VARCHAR(100) NULL,
    kota VARCHAR(100) NULL,
    provinsi VARCHAR(100) NULL,
    kode_pos VARCHAR(10) NULL,
    coordinates VARCHAR(50) NULL COMMENT 'lat,lng',

    -- Koneksi
    connection_type ENUM('pppoe', 'static', 'hotspot') NOT NULL DEFAULT 'pppoe',
    pppoe_username VARCHAR(100) NULL,
    pppoe_password VARCHAR(255) NULL,
    static_ip VARCHAR(45) NULL,
    mac_address VARCHAR(17) NULL,

    -- Relasi
    package_id BIGINT UNSIGNED NOT NULL,
    router_id BIGINT UNSIGNED NOT NULL,
    area_id BIGINT UNSIGNED NULL,

    -- Billing
    billing_date INT DEFAULT 1 COMMENT 'Tanggal tagihan (1-28)',
    billing_cycle ENUM('monthly', 'quarterly', 'yearly') DEFAULT 'monthly',
    join_date DATE NOT NULL,

    -- Status & Keuangan
    status ENUM('active', 'isolated', 'suspended', 'terminated') DEFAULT 'active',
    isolation_reason VARCHAR(255) NULL,
    total_debt DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Total hutang akumulasi',

    -- Perangkat Pelanggan
    ont_serial VARCHAR(50) NULL COMMENT 'Serial Number ONT/ONU',
    router_serial VARCHAR(50) NULL COMMENT 'Serial Router Pelanggan',

    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    FOREIGN KEY (package_id) REFERENCES packages(id),
    FOREIGN KEY (router_id) REFERENCES routers(id),
    FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE SET NULL,

    INDEX idx_customer_status (status),
    INDEX idx_customer_pppoe (pppoe_username),
    INDEX idx_customer_billing_date (billing_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. TABEL INVOICES (Tagihan)
-- ============================================
CREATE TABLE invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(30) UNIQUE NOT NULL COMMENT 'INV-YYYYMM-XXXXX',
    customer_id BIGINT UNSIGNED NOT NULL,

    -- Periode
    period_month INT NOT NULL COMMENT '1-12',
    period_year INT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,

    -- Detail Tagihan
    package_name VARCHAR(100) NOT NULL,
    package_price DECIMAL(12,2) NOT NULL,
    additional_fee DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Biaya tambahan',
    discount DECIMAL(12,2) DEFAULT 0.00,
    ppn DECIMAL(12,2) DEFAULT 0.00 COMMENT 'PPN 11%',
    total_amount DECIMAL(12,2) NOT NULL,

    -- Pembayaran
    paid_amount DECIMAL(12,2) DEFAULT 0.00,
    remaining_amount DECIMAL(12,2) NOT NULL,

    -- Status
    status ENUM('pending', 'partial', 'paid', 'overdue', 'cancelled') DEFAULT 'pending',
    due_date DATE NOT NULL,
    paid_at TIMESTAMP NULL,

    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (customer_id) REFERENCES customers(id),

    INDEX idx_invoice_status (status),
    INDEX idx_invoice_period (period_year, period_month),
    INDEX idx_invoice_due_date (due_date),
    UNIQUE KEY unique_customer_period (customer_id, period_year, period_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. TABEL PAYMENTS (Pembayaran)
-- ============================================
CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_number VARCHAR(30) UNIQUE NOT NULL COMMENT 'PAY-YYYYMMDD-XXXXX',
    customer_id BIGINT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NULL COMMENT 'NULL jika bayar hutang langsung',

    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash', 'transfer', 'qris', 'ewallet', 'va', 'other') NOT NULL,
    payment_channel VARCHAR(50) NULL COMMENT 'BCA, Mandiri, OVO, dll',
    reference_number VARCHAR(100) NULL,

    -- Alokasi Pembayaran
    allocated_to_invoice DECIMAL(12,2) DEFAULT 0.00,
    allocated_to_debt DECIMAL(12,2) DEFAULT 0.00,

    received_by BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_payment_date (created_at),
    INDEX idx_payment_method (payment_method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. TABEL DEBT_HISTORY (Riwayat Hutang)
-- ============================================
CREATE TABLE debt_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,

    -- Tipe Transaksi
    transaction_type ENUM('invoice_added', 'payment_received', 'adjustment', 'write_off') NOT NULL,
    reference_type VARCHAR(50) NULL COMMENT 'invoice, payment, manual',
    reference_id BIGINT UNSIGNED NULL,

    -- Nominal
    amount DECIMAL(12,2) NOT NULL COMMENT 'Positif = tambah hutang, Negatif = kurang hutang',
    previous_debt DECIMAL(12,2) NOT NULL,
    current_debt DECIMAL(12,2) NOT NULL,

    description TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_debt_customer (customer_id),
    INDEX idx_debt_date (created_at),
    INDEX idx_debt_type (transaction_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. TABEL BILLING_LOGS (Log Aktivitas Billing)
-- ============================================
CREATE TABLE billing_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NULL,

    log_type ENUM(
        'invoice_generated',
        'payment_received',
        'isolation_executed',
        'isolation_opened',
        'reminder_sent',
        'router_sync',
        'genieacs_sync',
        'system_error',
        'manual_action'
    ) NOT NULL,

    status ENUM('success', 'failed', 'pending') DEFAULT 'success',

    -- Detail Log
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    metadata JSON NULL COMMENT 'Data tambahan dalam format JSON',

    -- Pelaku
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    performed_by BIGINT UNSIGNED NULL,

    created_at TIMESTAMP NULL,

    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_log_type (log_type),
    INDEX idx_log_customer (customer_id),
    INDEX idx_log_date (created_at),
    INDEX idx_log_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 10. TABEL CUSTOMER_DEVICES (GenieACS Integration)
-- ============================================
CREATE TABLE customer_devices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,

    device_id VARCHAR(255) NOT NULL COMMENT 'GenieACS Device ID',
    serial_number VARCHAR(100) NULL,
    manufacturer VARCHAR(100) NULL,
    model VARCHAR(100) NULL,
    firmware_version VARCHAR(50) NULL,

    -- Status dari GenieACS
    is_online BOOLEAN DEFAULT FALSE,
    last_inform TIMESTAMP NULL,
    last_boot TIMESTAMP NULL,
    uptime INT NULL COMMENT 'dalam detik',

    -- Parameter TR-069
    parameters JSON NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (customer_id) REFERENCES customers(id),
    INDEX idx_device_customer (customer_id),
    INDEX idx_device_id (device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 11. TABEL ROUTER_COMMANDS (Antrian Perintah Router)
-- ============================================
CREATE TABLE router_commands (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    router_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NULL,

    command_type ENUM('isolate', 'open_access', 'change_speed', 'add_user', 'remove_user', 'custom') NOT NULL,
    command_data JSON NOT NULL,

    status ENUM('pending', 'processing', 'success', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,

    result TEXT NULL,
    error_message TEXT NULL,

    scheduled_at TIMESTAMP NULL,
    executed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (router_id) REFERENCES routers(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,

    INDEX idx_command_status (status),
    INDEX idx_command_scheduled (scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 12. TABEL SETTINGS (Pengaturan Sistem)
-- ============================================
CREATE TABLE settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `group` VARCHAR(50) NOT NULL,
    `key` VARCHAR(100) NOT NULL,
    value TEXT NULL,
    type ENUM('string', 'integer', 'boolean', 'json', 'text') DEFAULT 'string',
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    UNIQUE KEY unique_group_key (`group`, `key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 13. TABEL NOTIFICATIONS (Notifikasi)
-- ============================================
CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NULL,

    type ENUM('sms', 'whatsapp', 'email', 'push') NOT NULL,
    template_code VARCHAR(50) NULL,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NULL,
    message TEXT NOT NULL,

    status ENUM('pending', 'sent', 'failed', 'delivered') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    error_message TEXT NULL,

    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,

    INDEX idx_notification_status (status),
    INDEX idx_notification_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DATA AWAL (SEEDER)
-- ============================================

-- Settings Default
INSERT INTO settings (`group`, `key`, value, type, description) VALUES
('billing', 'invoice_prefix', 'INV', 'string', 'Prefix nomor invoice'),
('billing', 'payment_prefix', 'PAY', 'string', 'Prefix nomor pembayaran'),
('billing', 'due_days', '20', 'integer', 'Jumlah hari jatuh tempo dari tanggal invoice'),
('billing', 'ppn_enabled', 'false', 'boolean', 'Aktifkan PPN'),
('billing', 'ppn_percentage', '11', 'integer', 'Persentase PPN'),
('billing', 'auto_isolate', 'true', 'boolean', 'Otomatis isolir jika lewat jatuh tempo'),
('billing', 'isolate_grace_days', '7', 'integer', 'Masa tenggang sebelum isolir'),
('company', 'name', 'Java Indonusa', 'string', 'Nama perusahaan'),
('company', 'address', '', 'text', 'Alamat perusahaan'),
('company', 'phone', '', 'string', 'Telepon perusahaan'),
('company', 'email', '', 'string', 'Email perusahaan'),
('mikrotik', 'default_profile', 'default', 'string', 'Profile PPPoE default'),
('mikrotik', 'isolated_profile', 'isolated', 'string', 'Profile untuk pelanggan terisolir'),
('genieacs', 'api_url', 'http://localhost:7557', 'string', 'URL GenieACS NBI'),
('notification', 'whatsapp_enabled', 'true', 'boolean', 'Aktifkan notifikasi WhatsApp'),
('notification', 'reminder_days', '[7, 3, 1]', 'json', 'Hari pengingat sebelum jatuh tempo');

-- Paket Default
INSERT INTO packages (name, code, speed_download, speed_upload, price, description) VALUES
('Paket Hemat 10 Mbps', 'PKT-10', 10240, 5120, 150000.00, 'Paket internet 10 Mbps'),
('Paket Standar 20 Mbps', 'PKT-20', 20480, 10240, 200000.00, 'Paket internet 20 Mbps'),
('Paket Premium 50 Mbps', 'PKT-50', 51200, 25600, 350000.00, 'Paket internet 50 Mbps'),
('Paket Bisnis 100 Mbps', 'PKT-100', 102400, 51200, 500000.00, 'Paket internet 100 Mbps');
