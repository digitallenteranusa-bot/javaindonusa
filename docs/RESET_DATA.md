# Panduan Reset/Hapus Data

Panduan ini menjelaskan cara menghapus data contoh (dummy) sebelum aplikasi digunakan dengan data produksi.

## Command Reset Data

```bash
php artisan data:reset
```

### Opsi yang Tersedia

| Opsi | Deskripsi |
|------|-----------|
| `--customers` | Hapus data pelanggan, invoice, pembayaran, devices |
| `--transactions` | Hapus transaksi saja (invoice, pembayaran, expense, settlement) |
| `--master` | Hapus data master (area, paket, router, ODP, OLT) |
| `--genieacs` | Hapus data GenieACS/perangkat CPE saja |
| `--all` | Hapus SEMUA data (kecuali user admin) |
| `--force` | Skip konfirmasi (untuk script otomatis) |

---

## Skenario Reset

### 1. Hapus Data Pelanggan Saja

Gunakan jika ingin menghapus pelanggan contoh tapi **mempertahankan data master** (area, paket, router).

```bash
php artisan data:reset --customers
```

**Data yang dihapus:**
- Pelanggan (customers)
- Invoice (invoices)
- Pembayaran (payments)
- Riwayat Hutang (debt_histories)
- Log Penagihan (collection_logs)
- Perangkat Pelanggan (customer_devices)

### 2. Hapus Transaksi Saja

Gunakan jika ingin **mempertahankan data pelanggan** tapi menghapus semua transaksi.

```bash
php artisan data:reset --transactions
```

**Data yang dihapus:**
- Invoice (invoices)
- Pembayaran (payments)
- Riwayat Hutang (debt_histories)
- Pengeluaran (expenses)
- Setoran (settlements)
- Log Penagihan (collection_logs)

### 3. Hapus Data Master

Gunakan jika ingin **reset semua konfigurasi** (akan menghapus pelanggan juga karena relasi).

```bash
php artisan data:reset --master
```

**Data yang dihapus:**
- Area
- Paket Internet
- Router
- ODP
- OLT
- Radius Server
- (+ semua data pelanggan karena relasi foreign key)

### 4. Hapus Data GenieACS Saja

Gunakan jika ingin **reset perangkat CPE** dari sistem TR-069.

```bash
php artisan data:reset --genieacs
```

**Data yang dihapus:**
- Perangkat Pelanggan (customer_devices)
- Token Pelanggan (customer_tokens)

### 5. Hapus SEMUA Data (Fresh Start)

Gunakan untuk **reset total** dan mulai dari awal. Hanya user admin yang dipertahankan.

```bash
php artisan data:reset --all
```

**Data yang dihapus:**
- Semua pelanggan
- Semua transaksi
- Semua data master
- Semua perangkat GenieACS
- Semua user non-admin

---

## Langkah Reset untuk Produksi

### Persiapan Sebelum Reset

1. **Backup database** (jika ada data penting):
   ```bash
   mysqldump -u root -p billing_db > backup_sebelum_reset.sql
   ```

2. **Stop queue worker**:
   ```bash
   sudo systemctl stop billing-worker
   ```

### Eksekusi Reset

```bash
# Masuk ke direktori aplikasi
cd /var/www/billing

# Reset semua data contoh
php artisan data:reset --all

# Atau jika sudah yakin (skip konfirmasi)
php artisan data:reset --all --force
```

### Setelah Reset

1. **Clear cache**:
   ```bash
   php artisan optimize:clear
   php artisan optimize
   ```

2. **Start queue worker**:
   ```bash
   sudo systemctl start billing-worker
   ```

3. **Masukkan data produksi** melalui Admin Panel:
   - Tambah Area di menu **Admin > Master Data > Area**
   - Tambah Paket di menu **Admin > Master Data > Paket**
   - Tambah Router di menu **Admin > Master Data > Router**
   - Tambah User Penagih di menu **Admin > Users**
   - Import atau tambah Pelanggan

---

## Akun Default Setelah Reset

| Email | Password | Role |
|-------|----------|------|
| admin@javaindonusa.net | password | Admin |

> **Catatan:** Ubah password admin setelah reset untuk keamanan!

---

## FAQ

### Q: Apakah data bisa dikembalikan setelah dihapus?
**A:** Tidak. Data yang sudah dihapus tidak bisa dikembalikan. Pastikan backup dulu jika diperlukan.

### Q: Apakah aman menjalankan reset di server produksi?
**A:** Ya, aman. Command akan meminta konfirmasi dua kali untuk opsi `--all`. Pastikan queue worker sudah di-stop sebelum reset.

### Q: Bagaimana jika reset gagal?
**A:** Jalankan ulang dengan opsi `--force`. Jika masih gagal, cek error message dan pastikan database connection benar.

### Q: Apakah perlu migrasi ulang setelah reset?
**A:** Tidak perlu. Reset hanya menghapus data, tidak mengubah struktur tabel.

---

## Reset via Menu Interaktif

Jika tidak menggunakan opsi, command akan menampilkan menu:

```bash
php artisan data:reset
```

Output:
```
╔════════════════════════════════════════════════════════════╗
║           RESET DATA - ISP BILLING SYSTEM                  ║
╚════════════════════════════════════════════════════════════╝

 Pilih data yang akan direset:
  [1] Pelanggan saja (customers, invoices, payments, devices)
  [2] Transaksi saja (invoices, payments, expenses, settlements)
  [3] Data Master (areas, packages, routers, ODPs, OLTs)
  [4] GenieACS/Perangkat CPE saja
  [5] SEMUA DATA (kecuali admin user)
  [6] Batal
```

---

## Contoh Output Reset

```
╔════════════════════════════════════════════════════════════╗
║           RESET DATA - ISP BILLING SYSTEM                  ║
╚════════════════════════════════════════════════════════════╝

⚠️  PERINGATAN: Data yang dihapus TIDAK DAPAT dikembalikan!

 Apakah Anda yakin ingin melanjutkan? (yes/no) [no]: yes

Memulai proses reset...

🗑️  Menghapus data transaksi...
   ✓ Riwayat Hutang: 150 data dihapus
   ✓ Pembayaran: 45 data dihapus
   ✓ Invoice: 120 data dihapus
   ✓ Pengeluaran: 30 data dihapus
   ✓ Setoran: 5 data dihapus
   ✓ Log Penagihan: 200 data dihapus

🗑️  Menghapus data pelanggan...
   ✓ Pelanggan: 10 data dihapus

🗑️  Menghapus data master...
   ✓ ODP: 15 data dihapus
   ✓ OLT: 3 data dihapus
   ✓ Router: 5 data dihapus
   ✓ Area: 17 data dihapus
   ✓ Paket: 7 data dihapus
   ✓ Radius Server: 2 data dihapus

🗑️  Menghapus data GenieACS/Perangkat CPE...
   ✓ Perangkat Pelanggan (CPE): 8 data dihapus
   ✓ Token Pelanggan: 3 data dihapus

🗑️  Menghapus data lainnya...
   ✓ Audit Log: 500 data dihapus
   ✓ Notifikasi: 50 data dihapus
   ✓ Users (non-admin): 8 data dihapus

╔════════════════════════════════════════════════════════════╗
║              ✅ RESET SELESAI                              ║
╚════════════════════════════════════════════════════════════╝

+----------------------------------------------------------+
| Langkah Selanjutnya                                      |
+----------------------------------------------------------+
| 1. Tambah data Area di menu Admin > Master Data > Area   |
| 2. Tambah data Paket di menu Admin > Master Data > Paket |
| 3. Tambah data Router di menu Admin > Master Data > Router|
| 4. Tambah data Penagih di menu Admin > Users             |
| 5. Import atau tambah Pelanggan                          |
+----------------------------------------------------------+
```
