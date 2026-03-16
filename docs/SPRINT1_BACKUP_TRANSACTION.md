# Sprint 1: Backup Otomatis & DB Transaction Wrapping

## 1. Backup Otomatis (spatie/laravel-backup v8.8)

### Apa yang Dipasang
- Package `spatie/laravel-backup:^8.0` dengan Gzip compression untuk DB dump
- Disk `backup` di `config/filesystems.php` → `storage/app/backups/`
- Config lengkap di `config/backup.php`
- Env variable baru di `.env.example`

### Jadwal Backup (Otomatis via Scheduler)

| Jadwal | Command | Keterangan |
|--------|---------|------------|
| **Setiap hari 02:00 WIB** | `backup:run --only-db` | Backup database saja (cepat, ~1-5 MB compressed) |
| **Minggu 03:00 WIB** | `backup:run` | Full backup (DB + config + migrations + views) |
| **Minggu 04:00 WIB** | `backup:clean` | Hapus backup lama sesuai retention policy |
| **Setiap hari 08:00 WIB** | `backup:monitor` | Cek kesehatan backup, kirim notifikasi jika gagal |

### Retention Policy
- 7 hari: simpan semua backup
- 30 hari: simpan 1 backup per hari
- 8 minggu: simpan 1 backup per minggu
- 6 bulan: simpan 1 backup per bulan
- 2 tahun: simpan 1 backup per tahun
- Max storage: 5 GB

### Command Manual
```bash
php artisan backup:run --only-db    # Backup database saja
php artisan backup:run              # Full backup (DB + config)
php artisan backup:list             # Lihat daftar backup
php artisan backup:clean            # Hapus backup lama
php artisan backup:monitor          # Cek kesehatan backup
```

### Konfigurasi .env
```env
# Password enkripsi arsip (opsional, kosongkan jika tidak perlu)
BACKUP_ARCHIVE_PASSWORD=

# Email notifikasi backup gagal/berhasil
BACKUP_NOTIFICATION_EMAIL=admin@javaindonusa.com

# Slack webhook (opsional)
BACKUP_SLACK_WEBHOOK=
```

### Catatan Deploy
- Extension `ext-zip` harus aktif di PHP (sudah di-enable)
- MySQL dump menggunakan `useSingleTransaction` agar tidak lock tabel saat backup
- Pastikan `mysqldump` binary tersedia di server (biasanya sudah ada)
- Backup disimpan di `storage/app/backups/` — pastikan ada cukup disk space
- Log backup di `storage/logs/backup.log`

---

## 2. DB Transaction Wrapping

### Masalah yang Ditemukan

Beberapa operasi kritis melakukan **multiple writes tanpa transaction**, berisiko data inkonsisten jika terjadi error di tengah proses:

#### A. DebtService — 3 method tanpa transaction

| Method | Masalah | Risiko |
|--------|---------|--------|
| `addCredit()` | `increment()` + `create()` terpisah | Credit balance naik tapi DebtHistory tidak tercatat |
| `useCredit()` | `decrement()` + `update()` + `decrement()` + `create()` terpisah | Invoice ter-update tapi hutang tidak berkurang, atau sebaliknya |
| `recalculateDebt()` | `update()` + `create()` terpisah | Total debt berubah tanpa audit trail |

#### B. InvoiceService — 2 masalah

| Method | Masalah | Risiko |
|--------|---------|--------|
| `updateOverdueStatus()` | Loop update tanpa transaction | Sebagian invoice ter-update, sebagian tidak |
| `generateInvoiceNumber()` | Tidak pakai `lockForUpdate()` | Race condition: 2 invoice dapat nomor yang sama |

#### C. DebtIsolationService — 2 masalah

| Method | Masalah | Risiko |
|--------|---------|--------|
| `generateInvoiceNumber()` | Tidak pakai `lockForUpdate()` | Race condition pada concurrent invoice generation |
| `generatePaymentNumber()` | Tidak pakai `lockForUpdate()` | Race condition pada concurrent payment creation |

### Perbaikan yang Dilakukan

#### DebtService (`app/Services/Billing/DebtService.php`)

1. **`addCredit()`** — Dibungkus `DB::transaction()`
   - `increment('credit_balance')` + `DebtHistory::create()` sekarang atomic

2. **`useCredit()`** — Dibungkus `DB::transaction()`
   - `decrement('credit_balance')` + `invoice->update()` + `decrement('total_debt')` + `DebtHistory::create()` sekarang atomic

3. **`recalculateDebt()`** — Dibungkus `DB::transaction()` + `lockForUpdate()`
   - Query invoice sum pakai `lockForUpdate()` untuk prevent concurrent recalculation
   - `update()` + `DebtHistory::create()` sekarang atomic

#### InvoiceService (`app/Services/Billing/InvoiceService.php`)

4. **`updateOverdueStatus()`** — Diganti dari loop ke single `DB::transaction()` + bulk update
   - Sebelum: Loop per-invoice `$invoice->update()` (N queries, tanpa transaction)
   - Sesudah: Single `Invoice::where()->update()` dalam transaction (1 query, atomic)

5. **`generateInvoiceNumber()`** — Pakai `lockForUpdate()`
   - Sebelum: `count()` + loop `exists()` check (race condition possible)
   - Sesudah: `lockForUpdate()->orderBy('desc')->first()` (pessimistic lock, no race condition)

#### DebtIsolationService (`app/Services/Billing/DebtIsolationService.php`)

6. **`generateInvoiceNumber()`** — Pakai `lockForUpdate()`
   - Sama seperti perbaikan di InvoiceService

7. **`generatePaymentNumber()`** — Pakai `lockForUpdate()`
   - Sebelum: `whereDate()->orderBy()->first()` tanpa lock
   - Sesudah: `where('like')->lockForUpdate()->orderBy()->first()` (pessimistic lock)

### Method yang Sudah Benar (Tidak Perlu Diubah)

| Service | Method | Status |
|---------|--------|--------|
| PaymentService | `processPayment()` | Sudah pakai `DB::transaction()` |
| PaymentService | `cancelPayment()` | Sudah pakai `DB::transaction()` |
| PaymentService | `generatePaymentNumber()` | Sudah pakai `lockForUpdate()` |
| InvoiceService | `generateInvoiceForCustomer()` | Sudah pakai `DB::transaction()` |
| InvoiceService | `createHistoricalInvoice()` | Sudah pakai `DB::transaction()` |
| InvoiceService | `markAsPaid()` | Sudah pakai `DB::transaction()` |
| InvoiceService | `cancelInvoice()` | Sudah pakai `DB::transaction()` |
| DebtService | `addDebt()` | Sudah pakai `DB::transaction()` |
| DebtService | `reduceDebt()` | Sudah pakai `DB::transaction()` |
| DebtIsolationService | `processPayment()` | Sudah pakai `DB::transaction()` |
| DebtIsolationService | `addMonthlyDebtForCustomer()` | Sudah pakai `DB::transaction()` |

---

## File yang Diubah

| File | Perubahan |
|------|-----------|
| `composer.json` | Tambah `spatie/laravel-backup:^8.0` |
| `config/backup.php` | Baru — konfigurasi backup (Gzip, retention, notifikasi) |
| `config/filesystems.php` | Tambah disk `backup` |
| `config/database.php` | Tambah `dump` config untuk MySQL (useSingleTransaction) |
| `routes/console.php` | Ganti backup manual dengan `backup:run`, `backup:clean`, `backup:monitor` |
| `.env.example` | Tambah section BACKUP |
| `app/Services/Billing/DebtService.php` | Transaction wrapping: `addCredit()`, `useCredit()`, `recalculateDebt()` |
| `app/Services/Billing/InvoiceService.php` | Transaction: `updateOverdueStatus()`. Lock: `generateInvoiceNumber()` |
| `app/Services/Billing/DebtIsolationService.php` | Lock: `generateInvoiceNumber()`, `generatePaymentNumber()` |
