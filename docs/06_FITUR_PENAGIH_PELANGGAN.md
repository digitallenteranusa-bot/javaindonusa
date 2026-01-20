# Dokumentasi Fitur Penagih & Portal Pelanggan
## Billing ISP Java Indonusa

---

## 1. Logika Hutang & Isolir

### 1.1 Penambahan Hutang Otomatis (Setiap Tanggal 1)

```
┌─────────────────────────────────────────────────────────────┐
│                PROSES PENAMBAHAN HUTANG BULANAN              │
└─────────────────────────────────────────────────────────────┘

Scheduler (Tanggal 1, 00:01)
         │
         ▼
┌────────────────────┐
│ Ambil semua        │
│ pelanggan aktif    │
│ & terisolir        │
└────────┬───────────┘
         │
         ▼
┌────────────────────┐     Invoice
│ Cek invoice bulan  ├────►sudah ada?────► SKIP
│ ini sudah ada?     │         │
└────────┬───────────┘         │
         │ Belum ada           │
         ▼                     │
┌────────────────────┐         │
│ Generate Invoice   │         │
│ Tambah ke Hutang   │         │
│ Catat debt_history │         │
└────────┬───────────┘         │
         │                     │
         ▼                     │
    Kirim Notifikasi◄──────────┘
         │
         ▼
       SELESAI
```

### 1.2 Logika Isolir dengan Pengecualian Rapel

```php
// Algoritma penentuan isolir dengan pengecualian

function shouldIsolateCustomer($customer, $overdueMonths = 2, $graceDays = 7) {

    // PENGECUALIAN 1: Pelanggan Rapel
    if ($customer->payment_behavior === 'rapel') {
        $rapelMonths = $customer->rapel_months ?: 3;
        $unpaidCount = hitungInvoiceBelumBayar($customer);

        if ($unpaidCount <= $rapelMonths) {
            return false; // Tidak isolir, masih dalam batas rapel
        }
    }

    // PENGECUALIAN 2: Ada pembayaran dalam 30 hari terakhir
    if ($customer->last_payment_date) {
        $daysSincePayment = hitungHariSejakPembayaran($customer);

        if ($daysSincePayment <= 30) {
            return false; // Tidak isolir, baru bayar
        }
    }

    // Cek invoice overdue berturut-turut
    $consecutiveOverdue = hitungBulanOverdueBerturut($customer);

    return $consecutiveOverdue >= $overdueMonths;
}
```

### 1.3 Tipe Pelanggan berdasarkan Kebiasaan Bayar

| Tipe | Deskripsi | Batas Toleransi |
|------|-----------|-----------------|
| `regular` | Bayar bulanan normal | 2 bulan + 7 hari grace |
| `rapel` | Biasa bayar beberapa bulan sekaligus | Sesuai `rapel_months` (default 3) |
| `problematic` | Sering bermasalah | Tidak ada toleransi khusus |

---

## 2. Sistem Penagih (Collector)

### 2.1 Struktur Relasi Database

```
┌──────────────┐       ┌──────────────┐       ┌──────────────┐
│    users     │       │  customers   │       │   payments   │
│  (penagih)   │◄──────│              │◄──────│              │
│              │       │ collector_id │       │ collector_id │
└──────────────┘       └──────────────┘       └──────────────┘
       │                      │
       │                      ▼
       │               ┌──────────────┐
       │               │collection_   │
       └──────────────►│logs          │
                       └──────────────┘
```

### 2.2 Akses Data Terpisah per Penagih

```php
// Penagih A hanya bisa melihat pelanggannya
$customerIds = Customer::where('collector_id', $penagihA->id)
    ->pluck('id');

// Validasi akses saat pembayaran
if ($customer->collector_id !== auth()->user()->id) {
    throw new Exception('Anda tidak memiliki akses ke pelanggan ini');
}
```

### 2.3 Dashboard Penagih (Mobile First)

**Statistik yang Ditampilkan:**
1. Total Pelanggan yang ditugaskan
2. Sudah Bayar bulan ini
3. Belum Bayar bulan ini
4. Pelanggan Terisolir

**Statistik Pendapatan:**
1. Total Tagihan yang harus ditagih
2. Total yang sudah ditagih
3. Total Hutang Pelanggan
4. Tingkat Penagihan (%)

**Fitur Tombol Cepat:**
- WhatsApp: Buka WA dengan pesan tagihan otomatis
- Bayar: Modal pembayaran tunai/transfer

---

## 3. Manajemen Kas (Petty Cash)

### 3.1 Alur Kerja Pengeluaran

```
┌─────────────────────────────────────────────────────────────┐
│                   ALUR PETTY CASH PENAGIH                    │
└─────────────────────────────────────────────────────────────┘

  Penagih di Lapangan
         │
         ▼
┌────────────────────┐
│ Input Pengeluaran: │
│ - Kategori         │
│ - Nominal          │
│ - Foto Nota        │
└────────┬───────────┘
         │
         ▼
┌────────────────────┐
│ Validasi Batas     │
│ Harian (100K)      │
└────────┬───────────┘
         │
         ▼
┌────────────────────┐
│ Status: PENDING    │
│ Simpan ke expenses │
└────────┬───────────┘
         │
         ▼
    ┌─────────────────┐
    │ Admin Verifikasi│
    │ (Lihat foto nota)│
    └────────┬────────┘
             │
     ┌───────┴───────┐
     │               │
     ▼               ▼
 APPROVED         REJECTED
     │               │
     ▼               ▼
 Potong dari    Tidak dipotong
 Saldo Setoran  (dengan alasan)
```

### 3.2 Kategori Pengeluaran

| Kode | Label | Contoh |
|------|-------|--------|
| `fuel` | Bensin | BBM motor |
| `food` | Makan | Makan siang |
| `transport` | Transport | Ojek, parkir |
| `phone_credit` | Pulsa | Pulsa telepon |
| `parking` | Parkir | Parkir motor |
| `other` | Lainnya | Keperluan lain |

### 3.3 Kalkulasi Setoran

```php
function getFinalSettlement($penagih, $tanggal) {
    // Total pembayaran TUNAI yang diterima
    $cashCollection = Payment::where('collector_id', $penagih->id)
        ->whereDate('created_at', $tanggal)
        ->where('payment_method', 'cash')
        ->sum('amount');

    // Total pengeluaran yang APPROVED
    $approvedExpense = Expense::where('user_id', $penagih->id)
        ->whereDate('expense_date', $tanggal)
        ->where('status', 'approved')
        ->sum('amount');

    // Komisi penagih (jika ada)
    $commission = $cashCollection * ($penagih->commission_rate / 100);

    // Saldo yang harus disetor
    $mustSettle = $cashCollection - $approvedExpense - $commission;

    return [
        'cash_collection' => $cashCollection,
        'approved_expense' => $approvedExpense,
        'commission_amount' => $commission,
        'must_settle' => max(0, $mustSettle),
    ];
}
```

### 3.4 Tampilan Dashboard Setoran

```
┌──────────────────────────────────────┐
│        SETORAN HARI INI              │
├──────────────────────────────────────┤
│ Tagihan Masuk (Tunai) : Rp 1.000.000 │
│ Total Belanja         : Rp    50.000 │
│ Komisi (5%)           : Rp    50.000 │
│──────────────────────────────────────│
│ HARUS DISETOR         : Rp   900.000 │
└──────────────────────────────────────┘
```

---

## 4. Portal Pelanggan

### 4.1 Login via Nomor HP (Tanpa Password)

**Alur Login:**
```
Pelanggan input nomor HP
         │
         ▼
┌────────────────────┐
│ Cari customer      │
│ berdasarkan phone  │
└────────┬───────────┘
         │
    Ditemukan?
    │      │
   Ya     Tidak
    │      │
    ▼      ▼
Generate  Error:
OTP 6    "Nomor tidak
digit    terdaftar"
    │
    ▼
┌────────────────────┐
│ Kirim OTP via      │
│ WhatsApp           │
└────────┬───────────┘
         │
         ▼
┌────────────────────┐
│ Pelanggan input    │
│ OTP (5 menit)      │
└────────┬───────────┘
         │
    Valid?
    │      │
   Ya     Tidak
    │      │
    ▼      ▼
Login    Error:
Berhasil "OTP salah/
         expired"
```

### 4.2 Fitur Dashboard Pelanggan

1. **Info Pelanggan**
   - Nama, ID, Alamat
   - Paket & Kecepatan
   - Status Layanan

2. **Ringkasan Tagihan**
   - Total Hutang
   - Tagihan Bulan Ini
   - Status Pembayaran
   - Jatuh Tempo

3. **Informasi Pembayaran**
   - Daftar Rekening Bank
   - Tombol Salin Nomor Rekening
   - Tombol Kirim Bukti Transfer via WA

4. **Histori Tagihan**
   - 12 bulan terakhir
   - Status per bulan

5. **Histori Pembayaran**
   - 10 pembayaran terakhir
   - Tanggal & metode

### 4.3 Halaman Isolir (Public)

Pelanggan yang terisolir bisa mengakses halaman info isolir tanpa login:
- URL: `/portal/isolation/{customer_id}`
- Menampilkan: Total Hutang, Alasan Isolir
- Info Rekening untuk Pembayaran
- Tombol Kirim Bukti Transfer via WA

---

## 5. Laporan Penagih

### 5.1 Laporan Harian

```
┌────────────────────────────────────────────────────────────┐
│                  LAPORAN HARIAN PENAGIH                     │
├────────────────────────────────────────────────────────────┤
│ Nama Penagih  : Budi Santoso                               │
│ Tanggal       : 15 Januari 2026                            │
├────────────────────────────────────────────────────────────┤
│ PEMBAYARAN DITERIMA                                        │
├────┬────────┬─────────────────┬────────┬──────────────────┤
│ No │ Waktu  │ Pelanggan       │ Metode │ Jumlah           │
├────┼────────┼─────────────────┼────────┼──────────────────┤
│ 1  │ 09:30  │ Ahmad Fauzi     │ Tunai  │ Rp 200.000       │
│ 2  │ 10:15  │ Siti Rahayu     │ Tunai  │ Rp 350.000       │
│ 3  │ 11:00  │ Budi Prakoso    │Transfer│ Rp 200.000       │
├────┴────────┴─────────────────┴────────┴──────────────────┤
│ Total Tunai    : Rp 550.000                                │
│ Total Transfer : Rp 200.000                                │
├────────────────────────────────────────────────────────────┤
│ PENGELUARAN                                                │
├────┬───────────┬──────────────────────────┬───────────────┤
│ No │ Kategori  │ Keterangan               │ Jumlah        │
├────┼───────────┼──────────────────────────┼───────────────┤
│ 1  │ Bensin    │ BBM motor                │ Rp 20.000     │
│ 2  │ Makan     │ Makan siang              │ Rp 15.000     │
├────┴───────────┴──────────────────────────┴───────────────┤
│ Total Pengeluaran : Rp 35.000                              │
├────────────────────────────────────────────────────────────┤
│ RINGKASAN SETORAN                                          │
│ Tagihan Tunai        : Rp 550.000                          │
│ Pengeluaran          : Rp  35.000 (-)                      │
│ ──────────────────────────────────                         │
│ HARUS DISETOR        : Rp 515.000                          │
└────────────────────────────────────────────────────────────┘
```

### 5.2 Format Export

- **PDF**: Untuk print/arsip
- **HTML**: Untuk preview dan print browser
- **Print Thermal**: Opsional via Bluetooth (mobile)

---

## 6. Routes & Endpoints

### 6.1 Routes Penagih

```php
// routes/collector.php

Route::prefix('collector')->name('collector.')->group(function () {
    // Dashboard
    GET  /collector                     -> dashboard

    // Pelanggan
    GET  /collector/customers           -> daftar pelanggan
    GET  /collector/customers/{id}      -> detail pelanggan

    // Pembayaran
    POST /collector/customers/{id}/payment/cash      -> bayar tunai
    POST /collector/customers/{id}/payment/transfer  -> bayar transfer

    // Kunjungan
    POST /collector/customers/{id}/visit    -> log kunjungan
    POST /collector/customers/{id}/whatsapp -> kirim WA reminder

    // Pengeluaran
    GET  /collector/expenses            -> daftar pengeluaran
    POST /collector/expenses            -> tambah pengeluaran

    // Setoran
    GET  /collector/settlement          -> info setoran
    POST /collector/settlement          -> request setoran

    // Laporan
    GET  /collector/reports/daily       -> PDF harian
    GET  /collector/reports/monthly     -> PDF bulanan
    GET  /collector/reports/settlement  -> PDF setoran
});
```

### 6.2 Routes Portal Pelanggan

```php
// routes/customer.php

Route::prefix('portal')->name('customer.')->group(function () {
    // Login (Public)
    GET  /portal/login              -> form login
    POST /portal/login              -> request OTP
    POST /portal/verify-otp         -> verifikasi OTP
    GET  /portal/auth/{token}       -> login via link

    // Halaman Isolir (Public)
    GET  /portal/isolation/{id}     -> info isolir

    // Dashboard (Auth Required)
    GET  /portal                    -> dashboard
    GET  /portal/invoices           -> histori tagihan
    GET  /portal/payments           -> histori pembayaran
    GET  /portal/payment-info       -> info rekening
    POST /portal/logout             -> logout
});
```

---

## 7. Middleware

### 7.1 Customer Auth Middleware

```php
// app/Http/Middleware/CustomerAuth.php

public function handle(Request $request, Closure $next)
{
    $token = session('customer_token');
    $customerId = session('customer_id');

    if (!$token || !$customerId) {
        return redirect()->route('customer.login');
    }

    // Verifikasi token masih valid
    $customerToken = CustomerToken::where('token', $token)
        ->where('customer_id', $customerId)
        ->where('expires_at', '>', now())
        ->first();

    if (!$customerToken) {
        session()->forget(['customer_token', 'customer_id']);
        return redirect()->route('customer.login')
            ->with('error', 'Sesi telah berakhir');
    }

    return $next($request);
}
```

---

## 8. File yang Dibuat

| File | Deskripsi |
|------|-----------|
| `docs/05_DATABASE_PENAGIH.sql` | Skema database tambahan |
| `app/Services/Billing/DebtIsolationService.php` | Logika hutang & isolir |
| `app/Services/Collector/CollectorService.php` | Service penagih |
| `app/Services/Collector/ExpenseService.php` | Manajemen petty cash |
| `app/Services/Customer/CustomerPortalService.php` | Portal pelanggan |
| `app/Http/Controllers/Collector/*` | Controller penagih |
| `app/Http/Controllers/Customer/*` | Controller portal |
| `resources/js/Pages/Collector/Dashboard.vue` | UI Dashboard penagih |
| `resources/js/Pages/Customer/*.vue` | UI Portal pelanggan |
| `resources/views/pdf/collector/*` | Template PDF laporan |
| `routes/collector.php` | Routes penagih |
| `routes/customer.php` | Routes portal |
| `app/Http/Middleware/CustomerAuth.php` | Middleware auth pelanggan |
