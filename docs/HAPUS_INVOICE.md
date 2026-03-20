# Panduan Hapus Invoice

Panduan ini menjelaskan cara menghapus invoice secara manual jika terjadi kesalahan (duplikat, salah input, dll).

## Langkah-langkah

### 1. Masuk ke Tinker

```bash
php artisan tinker
```

### 2. Cari Invoice

```php
$invoice = \App\Models\Invoice::where('invoice_number', 'INV-XXXXXX-XXXXX')->first();
```

Ganti `INV-XXXXXX-XXXXX` dengan nomor invoice yang ingin dihapus.

### 3. Verifikasi Data

```php
$invoice;
```

Pastikan invoice yang ditemukan adalah invoice yang benar sebelum menghapus.

### 4. Hapus Invoice dan Data Terkait

Paste semua code berikut **sekaligus** dalam satu session tinker:

```php
// Hapus payments terkait
$invoice->payments()->delete();

// Hapus debt history terkait
\App\Models\DebtHistory::where('invoice_id', $invoice->id)->delete();

// Simpan customer_id sebelum hapus
$customerId = $invoice->customer_id;

// Hapus invoice
$invoice->delete();

// Update total_debt customer
$customer = \App\Models\Customer::find($customerId);
$customer->total_debt = $customer->invoices()->where('status', '!=', 'paid')->sum('total_amount') - $customer->invoices()->where('status', '!=', 'paid')->sum('paid_amount');
$customer->save();

// Verifikasi
echo "Total debt sekarang: Rp " . number_format($customer->total_debt, 0, ',', '.') . "\n";
```

## Urutan Penghapusan (Penting!)

Data harus dihapus dalam urutan berikut karena ada relasi foreign key:

1. **Payments** - pembayaran yang terkait invoice
2. **DebtHistory** - riwayat hutang yang mereferensikan invoice
3. **Invoice** - invoice itu sendiri
4. **Update total_debt** - sinkronkan ulang total hutang customer

## Catatan

- **Selalu verifikasi** data invoice sebelum menghapus
- **Paste sekaligus** semua code dalam satu session tinker, karena variabel akan hilang jika session tinker ditutup
- Jika invoice sudah ada pembayaran (`paid_amount > 0`), pertimbangkan apakah pembayaran juga perlu dihapus atau dialokasikan ke invoice lain
- Penghapusan ini bersifat **soft delete** (data masih ada di database dengan kolom `deleted_at` terisi). Untuk menghapus permanen, gunakan `->forceDelete()` sebagai pengganti `->delete()`

## Contoh Kasus

### Hapus Invoice Duplikat

```php
// Cari semua invoice dengan nomor yang sama
\App\Models\Invoice::where('invoice_number', 'INV-202512-00009')->get();

// Jika ada duplikat, hapus yang lebih baru (id lebih besar)
$invoice = \App\Models\Invoice::where('invoice_number', 'INV-202512-00009')
    ->orderBy('id', 'desc')
    ->first();
```

### Cari Invoice Berdasarkan Customer

```php
// Cari semua invoice overdue untuk customer tertentu
\App\Models\Invoice::where('customer_id', 540)
    ->where('status', 'overdue')
    ->get(['id', 'invoice_number', 'period_month', 'period_year', 'total_amount', 'status']);
```
