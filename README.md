# Billing ISP - Java Indonusa

Sistem Billing ISP lengkap dengan integrasi Mikrotik API, GenieACS (TR-069), Sistem Penagih, dan Portal Pelanggan.

## Dokumentasi

| File | Deskripsi |
|------|-----------|
| [01_INSTALASI.md](docs/01_INSTALASI.md) | **Panduan instalasi Linux (Production)** |
| [02_INSTALASI_WINDOWS.md](docs/02_INSTALASI_WINDOWS.md) | **Panduan instalasi Windows (Development)** |
| [02_ALUR_INTEGRASI.md](docs/02_ALUR_INTEGRASI.md) | Alur integrasi Mikrotik API & GenieACS |
| [03_LOGIKA_TAGIHAN.md](docs/03_LOGIKA_TAGIHAN.md) | Algoritma invoice & pembayaran |
| [04_STRUKTUR_FOLDER.md](docs/04_STRUKTUR_FOLDER.md) | Struktur folder Laravel project |
| [06_FITUR_PENAGIH_PELANGGAN.md](docs/06_FITUR_PENAGIH_PELANGGAN.md) | Fitur penagih & portal pelanggan |
| [09_VPN_SETUP.md](docs/09_VPN_SETUP.md) | Setup VPN untuk deployment cloud |

## Scripts

| File | Deskripsi |
|------|-----------|
| [scripts/install.sh](scripts/install.sh) | Script instalasi pertama kali |
| [scripts/deploy.sh](scripts/deploy.sh) | Script deployment/update |
| [scripts/status.sh](scripts/status.sh) | Script cek status sistem |
| [scripts/supervisor.conf](scripts/supervisor.conf) | Konfigurasi Supervisor |

## Fitur Utama

### 1. Manajemen Pelanggan
- Pelanggan PPPoE dan Static IP
- Multi-router support (Mikrotik, Huawei, ZTE, dll)
- Wilayah/Area coverage
- Paket internet dengan berbagai kecepatan
- Kebiasaan bayar (regular/rapel/problematic)

### 2. Billing & Invoice
- Generate invoice otomatis setiap tanggal 1
- Sistem cicilan hutang dengan alokasi FIFO
- Riwayat hutang lengkap (debt_history)
- Multiple payment method (Cash, Transfer, QRIS, E-Wallet)
- Payment gateway online: **Tripay** dan **Xendit** (bisa aktif bersamaan)

### 3. Logika Isolir Pintar
- Isolir otomatis jika hutang 2 bulan berturut-turut
- **Pengecualian Rapel**: Pelanggan yang biasa bayar rapel tidak langsung diisolir
- **Pengecualian Pembayaran Baru**: Tidak isolir jika ada pembayaran dalam 30 hari
- Buka akses otomatis saat pembayaran

### 4. Sistem Penagih (Collector)
- Dashboard mobile-first untuk penagih
- Pemisahan data per penagih (penagih A tidak bisa akses pelanggan penagih B)
- Statistik: Total pelanggan, sudah bayar, belum bayar, terisolir
- Tombol cepat: WhatsApp reminder & Bayar
- Filter berdasarkan nama/status isolir
- Pembayaran tunai dan transfer dengan bukti

### 5. Manajemen Kas (Petty Cash)
- Input pengeluaran dengan foto nota
- Field: Jumlah, Keterangan, Bukti Foto
- Verifikasi admin sebelum potong setoran
- Kalkulasi otomatis: Tagihan Masuk - Belanja = Harus Setor
- Laporan harian dan bulanan (PDF)

### 6. Portal Pelanggan
- Login via nomor HP (tanpa password, OTP via WhatsApp)
- Lihat histori tagihan dan pembayaran
- **Bayar online** via QRIS, Virtual Account, E-Wallet (Tripay/Xendit)
- Info rekening bank untuk transfer
- Tombol kirim bukti transfer via WhatsApp
- Halaman isolir (public) dengan info cara bayar

### 7. Integrasi Router
- Mikrotik API (isolir/buka akses otomatis)
- Address List 'ISOLIR' untuk pelanggan tunggak
- Profile PPPoE sync dengan paket
- Queue command system

### 8. Integrasi GenieACS (TR-069)
- Sinkronisasi perangkat pelanggan (ONT/Router)
- Remote management (reboot, factory reset)
- Firmware upgrade
- Konfigurasi WiFi remote

### 9. Notifikasi
- WhatsApp (reminder, isolir, buka akses)
- Konfigurasi WhatsApp via UI (Settings > WhatsApp)
- Support driver: Fonnte, WaBlas, WAHA, Custom

### 10. Manajemen FTTH (Fiber To The Home) - BARU
- **ODP** (Optical Distribution Point) - Manajemen titik distribusi fiber
- **OLT** (Optical Line Terminal) - Manajemen perangkat OLT
- **Mapping** - Peta interaktif pelanggan & ODP dengan Leaflet.js
- Radius Server (placeholder)

### 11. VPN Script Generator - BARU
- Generate script VPN untuk Mikrotik router
- Protokol: L2TP/IPSec, PPTP, SSTP
- Support RouterOS v6 dan v7

### 12. Roles & Permissions (RBAC) - BARU
- Manajemen hak akses per role
- Assign/revoke permission
- Reset ke default permissions

### 13. Customizable Branding
- Upload logo ISP
- Logo tampil di invoice PDF, receipt, dan portal

### 14. Payment Gateway Online
- **Tripay** - QRIS, Virtual Account, E-Wallet, Minimarket (via API Tripay)
- **Xendit** - QRIS, VA (BCA/BNI/BRI/Mandiri/Permata), E-Wallet (DANA/OVO/ShopeePay/LinkAja), Minimarket (Alfamart/Indomaret)
- Admin bisa aktifkan salah satu atau keduanya di Pengaturan
- Jika dua-duanya aktif, Xendit digunakan sebagai gateway utama
- Webhook callback otomatis memproses pembayaran + kirim notifikasi WA
- Sandbox mode untuk testing (Tripay: toggle di UI, Xendit: otomatis dari prefix key)

## Tech Stack

- **Backend**: Laravel 11
- **Frontend**: Vue 3 + Inertia.js
- **CSS**: Tailwind CSS
- **Database**: MySQL 8
- **Queue**: Redis
- **Router API**: RouterOS API (Mikrotik)
- **TR-069**: GenieACS
- **PDF**: DomPDF

## Quick Start

```bash
# Clone repository
git clone https://github.com/xxx/billing-isp.git
cd billing-isp

# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Edit .env sesuai kebutuhan (lihat bagian Konfigurasi di bawah)

# Database
php artisan migrate --seed

# Build assets
npm run build

# Start server
php artisan serve
```

## Konfigurasi Environment

Semua konfigurasi ada dalam satu file `.env.example`. Copy menjadi `.env` dan sesuaikan:

### Bagian-bagian Konfigurasi

| Bagian | Wajib | Keterangan |
|--------|-------|------------|
| **APLIKASI** | Ya | APP_NAME, APP_URL, APP_KEY |
| **DATABASE** | Ya | Koneksi MySQL |
| **REDIS** | Ya | Untuk cache, session, queue |
| **BILLING** | Ya | Logika bisnis (jatuh tempo, grace period, isolir) |
| **MIKROTIK** | Jika pakai | Koneksi router untuk isolir/buka akses |
| **GENIEACS** | Jika pakai | Manajemen device TR-069 (ONU/ONT) |
| **NOTIFIKASI** | Opsional | WhatsApp, SMS, Email |
| **TRIPAY** | Opsional | Payment gateway Tripay (QRIS, VA, E-Wallet) |
| **XENDIT** | Opsional | Payment gateway Xendit (QRIS, VA, E-Wallet) |

### Instalasi Lokal vs Cloud

**Instalasi Lokal:**
- Mikrotik dan GenieACS dapat diakses langsung via IP lokal
- Contoh: `MIKROTIK_HOST=192.168.88.1`

**Instalasi Cloud (VPS):**
- Perlu setup VPN ke jaringan lokal ISP
- Setelah VPN aktif, gunakan IP lokal yang sama
- Lihat: `docs/09_VPN_SETUP.md`

### Contoh Konfigurasi Minimal

```env
# Wajib diisi
APP_NAME="Nama ISP Anda"
APP_URL=https://billing.isp-anda.com
DB_DATABASE=billing_db
DB_USERNAME=user
DB_PASSWORD=password

# Mikrotik (jika digunakan)
MIKROTIK_HOST=192.168.88.1
MIKROTIK_USER=admin
MIKROTIK_PASS=password_mikrotik

# WhatsApp (jika digunakan)
WHATSAPP_ENABLED=true
WHATSAPP_DRIVER=fonnte
WHATSAPP_API_KEY=your_api_key

# Payment Gateway - Tripay (opsional)
TRIPAY_ENABLED=true
TRIPAY_SANDBOX=true
TRIPAY_API_KEY=your_tripay_api_key
TRIPAY_PRIVATE_KEY=your_tripay_private_key
TRIPAY_MERCHANT_CODE=T12345

# Payment Gateway - Xendit (opsional)
XENDIT_ENABLED=true
XENDIT_SECRET_KEY=xnd_development_xxx
XENDIT_WEBHOOK_TOKEN=your_webhook_token
```

## Scheduler (Cron)

Tambahkan ke crontab:
```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

Jadwal otomatis:
- **Tanggal 1, 00:01** - Generate invoice bulanan & tambah hutang
- **Setiap hari 06:00** - Cek invoice overdue & isolir pelanggan
- **Setiap hari 09:00** - Kirim reminder tagihan
- **Setiap 15 menit** - Sinkronisasi GenieACS
- **Setiap hari 02:00** - Sinkronisasi profile Mikrotik

## Role & Akses

| Role | Akses |
|------|-------|
| `superadmin` | Semua fitur |
| `admin` | Manajemen pelanggan, invoice, pembayaran |
| `teknisi` | Manajemen router, troubleshooting |
| `kasir` | Pembayaran, invoice |
| `penagih` | Dashboard penagih, penagihan lapangan |

## Struktur URL

### Admin
- `/admin` - Dashboard admin
- `/admin/customers` - Manajemen pelanggan
- `/admin/invoices` - Manajemen invoice
- `/admin/payments` - Manajemen pembayaran
- `/admin/routers` - Manajemen router
- `/admin/routers/{id}/vpn` - VPN script generator
- `/admin/odps` - Manajemen ODP (FTTH)
- `/admin/olts` - Manajemen OLT (FTTH)
- `/admin/radius-servers` - Manajemen Radius Server
- `/admin/mapping` - Peta pelanggan & ODP
- `/admin/reports` - Laporan
- `/admin/audit-logs` - Audit log
- `/admin/roles` - Roles & Permissions
- `/admin/settings` - Pengaturan (ISP Info, Logo, WhatsApp, Mikrotik, GenieACS, Payment Gateway)
- `/admin/system` - System Info & Backup

### Penagih
- `/collector` - Dashboard penagih
- `/collector/customers` - Daftar pelanggan
- `/collector/expenses` - Pengeluaran
- `/collector/settlement` - Setoran
- `/collector/reports/*` - Laporan PDF

### Portal Pelanggan
- `/portal/login` - Login pelanggan
- `/portal` - Dashboard pelanggan
- `/portal/pay` - Bayar online (Tripay/Xendit)
- `/portal/invoices` - Histori tagihan
- `/portal/payments` - Histori pembayaran
- `/portal/isolation/{id}` - Halaman isolir (public)

## License

Proprietary - Java Indonusa TRENGGALEK KONOHA
