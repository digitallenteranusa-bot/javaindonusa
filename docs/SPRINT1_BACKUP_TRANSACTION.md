# Sprint 1: Backup Otomatis & DB Transaction Wrapping

---

## 1. Panduan Update Server (Step by Step)

### Tahap 1 — Masuk ke Server

```bash
ssh root@IP_SERVER
```

### Tahap 2 — Pindah ke Direktori Aplikasi

```bash
cd /var/www/billing
```

### Tahap 3 — Simpan Perubahan Lokal (jika ada)

```bash
git stash
```

### Tahap 4 — Tarik Kode Terbaru dari GitHub

```bash
git pull origin main
```

> Jika error `untracked working tree files would be overwritten`:
> ```bash
> rm file-yang-disebutkan-di-error
> git pull origin main
> ```

### Tahap 5 — Install/Update Dependency PHP

```bash
composer update --no-dev --optimize-autoloader --ignore-platform-reqs
```

> Jika muncul `Continue as root/super user [yes]?`, ketik `yes` lalu Enter.

### Tahap 6 — Jalankan Migrasi Database

```bash
php artisan migrate --force
```

### Tahap 7 — Pastikan Folder yang Dibutuhkan Ada

```bash
mkdir -p storage/framework/{views,cache,sessions,testing}
```

### Tahap 8 — Bersihkan Cache Lama & Buat Cache Baru

```bash
php artisan optimize:clear
```

```bash
php artisan optimize
```

### Tahap 9 — Restart Queue Worker

```bash
php artisan queue:restart
```

### Tahap 10 — Verifikasi Aplikasi Jalan Normal

```bash
php artisan about
```

> Pastikan tidak ada error. Harusnya muncul info Laravel Version, PHP Version, dll.

---

## 2. Panduan Setup Backup (Pertama Kali)

> Backup sudah otomatis aktif setelah update kode. Bagian ini hanya perlu dilakukan **sekali**.

### Tahap 1 — Cek Extension PHP zip

```bash
php -m | grep zip
```

> Jika tidak muncul `zip`, install dulu:
> ```bash
> apt install php8.2-zip -y
> systemctl restart php8.2-fpm
> ```

### Tahap 2 — Cek mysqldump Tersedia

```bash
which mysqldump
```

> Harusnya muncul `/usr/bin/mysqldump`. Jika tidak:
> ```bash
> apt install mysql-client -y
> ```

### Tahap 3 — Setup Email Notifikasi

Edit file `.env`:

```bash
nano .env
```

Tambahkan/edit bagian ini:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=infojavaindonusa@gmail.com
MAIL_PASSWORD="vgsk bnhz plot msal"
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="infojavaindonusa@gmail.com"
MAIL_FROM_NAME="${APP_NAME}"

BACKUP_NOTIFICATION_EMAIL=infojavaindonusa@gmail.com
```

> **PENTING:**
> - Password yang ada spasi HARUS dibungkus tanda kutip `"..."`
> - `MAIL_FROM_ADDRESS` HARUS sama dengan `MAIL_USERNAME` untuk Gmail
> - App Password Gmail dibuat di: https://myaccount.google.com/apppasswords

Simpan: `Ctrl+O` → Enter → `Ctrl+X`

### Tahap 4 — Clear Cache Setelah Edit .env

```bash
php artisan optimize:clear
```

```bash
php artisan optimize
```

### Tahap 5 — Pastikan Crontab Scheduler Aktif

```bash
crontab -e
```

Pastikan ada baris ini (jika belum, tambahkan):

```
* * * * * cd /var/www/billing && php artisan schedule:run >> /dev/null 2>&1
```

Simpan dan keluar.

### Tahap 6 — Test Backup Manual

```bash
php artisan backup:run --only-db
```

> Harusnya muncul:
> ```
> Starting backup...
> Dumping database billing_javaindonusa...
> ...
> Backup completed!
> ```

### Tahap 7 — Verifikasi Backup Tersimpan

```bash
php artisan backup:list
```

> Harusnya muncul tabel dengan Healthy ✅ dan jumlah backup > 0.

### Tahap 8 — Cek Email Notifikasi

Buka inbox email (`infojavaindonusa@gmail.com`). Harusnya ada email "Successful backup".

---

## 3. Lokasi File Backup di Server

```
/var/www/billing/storage/app/private/ISP Billing - Java Indonusa/
```

### Lihat Daftar File Backup

```bash
find storage/app -name "*.zip" -type f -exec ls -lh {} \;
```

### Download Backup ke PC

**Dari terminal PC lokal (bukan server):**

```bash
scp root@IP_SERVER:"/var/www/billing/storage/app/private/ISP Billing - Java Indonusa/*.zip" ~/Downloads/
```

**Atau download file tertentu:**

```bash
scp root@IP_SERVER:"/var/www/billing/storage/app/private/ISP Billing - Java Indonusa/2026-03-16-22-06-50.zip" ~/Downloads/
```

---

## 4. Jadwal Backup Otomatis

| Jadwal | Apa yang Dilakukan | Isi |
|--------|-------------------|-----|
| Setiap hari 02:00 WIB | Backup database | Semua tabel (~185 KB compressed) |
| Minggu 03:00 WIB | Full backup | Database + .env + config + migrations + views |
| Minggu 04:00 WIB | Cleanup | Hapus backup lama otomatis |
| Setiap hari 08:00 WIB | Health check | Cek & email jika backup gagal |

### Berapa Lama Backup Disimpan

- 7 hari: simpan semua
- 30 hari: 1 per hari
- 8 minggu: 1 per minggu
- 6 bulan: 1 per bulan
- 2 tahun: 1 per tahun
- Max total: 5 GB

### Lokasi Penyimpanan

| Lokasi | Keterangan |
|--------|------------|
| Server lokal | `storage/app/private/ISP Billing - Java Indonusa/` |
| Google Drive | Folder "ISP Billing Backup" (otomatis jika `GOOGLE_DRIVE_FOLDER_ID` diisi) |

---

## 5. Setup Google Drive Backup (Opsional)

> Backup tambahan ke Google Drive agar aman jika server rusak. **Gratis 15 GB**.

### Tahap 1 — Buat Project di Google Cloud Console

1. Buka https://console.cloud.google.com
2. Login pakai akun Gmail
3. Klik **Select a project** → **New Project**
4. Nama: `isp-billing-backup` → **Create**

### Tahap 2 — Aktifkan Google Drive API

1. Menu kiri → **APIs & Services** → **Library**
2. Cari **"Google Drive API"** → klik → **Enable**

### Tahap 3 — Buat Service Account

1. **APIs & Services** → **Credentials** → **+ Create Credentials** → **Service Account**
2. Nama: `backup-uploader` → **Create and Continue** → skip role → **Done**
3. Klik service account yang baru dibuat
4. Tab **Keys** → **Add Key** → **Create new key** → **JSON** → **Create**
5. File JSON ter-download — simpan

### Tahap 4 — Buat Folder di Google Drive & Share

1. Buka https://drive.google.com → buat folder **"ISP Billing Backup"**
2. Klik kanan folder → **Share**
3. Paste email service account (dari file JSON, field `client_email`)
4. Set **Editor** → **Send**
5. Copy **Folder ID** dari URL: `https://drive.google.com/drive/folders/FOLDER_ID_DISINI`

### Tahap 5 — Upload Credentials ke Server

```bash
nano /var/www/billing/storage/app/google-drive-credentials.json
```

Paste isi file JSON → simpan (`Ctrl+O` → Enter → `Ctrl+X`)

### Tahap 6 — Edit .env di Server

```bash
nano /var/www/billing/.env
```

Tambahkan:

```env
GOOGLE_DRIVE_FOLDER_ID=folder_id_dari_tahap_4
```

### Tahap 7 — Clear Cache

```bash
cd /var/www/billing && php artisan optimize:clear && php artisan optimize
```

### Tahap 8 — Test Backup ke Google Drive

```bash
php artisan backup:run --only-db
```

Cek Google Drive — seharusnya muncul file backup baru di folder "ISP Billing Backup".

---

## 6. Command Backup Manual

```bash
# Backup database saja
php artisan backup:run --only-db

# Full backup (database + config)
php artisan backup:run

# Lihat daftar backup
php artisan backup:list

# Hapus backup lama
php artisan backup:clean

# Cek kesehatan backup
php artisan backup:monitor
```

---

## 6. Troubleshooting

| Masalah | Solusi |
|---------|--------|
| `git pull` gagal file conflict | Jalankan `git stash` dulu, lalu `git pull` ulang |
| `untracked working tree files would be overwritten` | `rm file-yang-disebutkan` lalu `git pull` ulang |
| `artisan: command not found` | Jangan enter di tengah command, paste utuh satu kali |
| `The environment file is invalid` | Value di `.env` yang ada spasi harus dibungkus `"..."` |
| `Disk [xxx] does not have a configured driver` | `php artisan optimize:clear && php artisan optimize` |
| `Sending notification failed` | Cek SMTP config di `.env`, atau kosongkan `BACKUP_NOTIFICATION_EMAIL` |
| `Class "ZipArchive" not found` | `apt install php8.2-zip -y && systemctl restart php8.2-fpm` |
| `mysqldump not found` | `apt install mysql-client -y` |
| `Continue as root/super user [yes]?` | Ketik `yes` lalu Enter |

---

## 7. Perubahan Teknis (DB Transaction Wrapping)

### Apa yang Diperbaiki

7 method di 3 file service diperbaiki agar lebih aman untuk data:

| File | Method | Perbaikan |
|------|--------|-----------|
| `DebtService.php` | `addCredit()` | Dibungkus `DB::transaction()` |
| `DebtService.php` | `useCredit()` | Dibungkus `DB::transaction()` |
| `DebtService.php` | `recalculateDebt()` | Dibungkus `DB::transaction()` + `lockForUpdate()` |
| `InvoiceService.php` | `updateOverdueStatus()` | Dibungkus `DB::transaction()` |
| `InvoiceService.php` | `generateInvoiceNumber()` | Tambah `lockForUpdate()` (cegah nomor duplikat) |
| `DebtIsolationService.php` | `generateInvoiceNumber()` | Tambah `lockForUpdate()` (cegah nomor duplikat) |
| `DebtIsolationService.php` | `generatePaymentNumber()` | Tambah `lockForUpdate()` (cegah nomor duplikat) |

### Apakah Mengubah Data yang Sudah Ada?

**TIDAK.** Semua perubahan hanya:
- Membungkus operasi yang sudah ada dengan safety net (transaction)
- Menambah lock untuk mencegah race condition
- Menambah fitur backup (baca-saja, tidak menulis ke database)

---

## 8. File yang Diubah

| File | Perubahan |
|------|-----------|
| `composer.json` | Tambah `spatie/laravel-backup:^8.0` |
| `config/backup.php` | Baru — konfigurasi backup |
| `config/database.php` | Tambah config MySQL dump |
| `routes/console.php` | Jadwal backup otomatis |
| `.env.example` | Tambah section BACKUP |
| `app/Services/Billing/DebtService.php` | Transaction wrap 3 method |
| `app/Services/Billing/InvoiceService.php` | Transaction wrap + lock 2 method |
| `app/Services/Billing/DebtIsolationService.php` | Lock fix 2 method |
