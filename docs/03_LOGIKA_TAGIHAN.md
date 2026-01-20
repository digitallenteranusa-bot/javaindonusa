# Logika Tagihan & Algoritma Invoice
## Billing ISP Java Indonusa

---

## 1. Algoritma Generate Invoice Otomatis

### 1.1 Flowchart Generate Invoice

```
┌──────────────────────────────────────────────────────────────────────┐
│              GENERATE INVOICE OTOMATIS (Tanggal 1)                    │
└──────────────────────────────────────────────────────────────────────┘

    ┌─────────┐
    │  START  │
    │  (Cron) │
    │ Tgl 1   │
    └────┬────┘
         │
         ▼
┌────────────────────────┐
│ Ambil semua Customer   │
│ WHERE status IN        │
│ ('active', 'isolated') │
│ AND deleted_at IS NULL │
└──────────┬─────────────┘
           │
           ▼
┌────────────────────────┐
│ Loop setiap Customer   │
└──────────┬─────────────┘
           │
           ▼
    ┌──────────────┐      Ya
    │ Invoice bulan├─────────────┐
    │ ini sudah    │             │
    │ ada?         │             │
    └──────┬───────┘             │
           │ Tidak               │
           ▼                     │
┌────────────────────────┐       │
│ Hitung Total Tagihan:  │       │
│ - Harga Paket          │       │
│ - Biaya Tambahan       │       │
│ - Diskon (jika ada)    │       │
│ - PPN (jika aktif)     │       │
└──────────┬─────────────┘       │
           │                     │
           ▼                     │
┌────────────────────────┐       │
│ Generate Invoice:      │       │
│ - Nomor: INV-YYYYMM-X  │       │
│ - Periode: Bulan ini   │       │
│ - Due Date: +20 hari   │       │
│ - Status: pending      │       │
└──────────┬─────────────┘       │
           │                     │
           ▼                     │
┌────────────────────────┐       │
│ Update total_debt      │       │
│ Customer += total      │       │
└──────────┬─────────────┘       │
           │                     │
           ▼                     │
┌────────────────────────┐       │
│ Catat ke debt_history: │       │
│ type: invoice_added    │       │
│ amount: +total         │       │
└──────────┬─────────────┘       │
           │                     │
           ▼                     │
┌────────────────────────┐       │
│ Dispatch Event:        │       │
│ InvoiceGenerated       │       │
└──────────┬─────────────┘       │
           │                     │
           ▼                     │
┌────────────────────────┐       │
│ Kirim Notifikasi       │       │
│ Invoice via WA/SMS     │       │
└──────────┬─────────────┘       │
           │◄────────────────────┘
           ▼
    ┌──────────────┐
    │ Next Customer│
    └──────┬───────┘
           │
           ▼
    ┌─────────────┐
    │ Semua selesai│
    │ ? Log hasil  │
    └─────────────┘
```

### 1.2 Service Generate Invoice

```php
// app/Services/InvoiceService.php
namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use App\Events\InvoiceGenerated;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    protected DebtService $debtService;

    public function __construct(DebtService $debtService)
    {
        $this->debtService = $debtService;
    }

    /**
     * Generate invoice untuk semua pelanggan aktif
     * Dijalankan setiap tanggal 1
     */
    public function generateMonthlyInvoices(): array
    {
        $now = Carbon::now();
        $periodMonth = $now->month;
        $periodYear = $now->year;

        // Ambil pelanggan yang perlu dibuatkan invoice
        $customers = Customer::whereIn('status', ['active', 'isolated'])
            ->whereNull('deleted_at')
            ->with('package')
            ->get();

        $results = [
            'total' => $customers->count(),
            'created' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($customers as $customer) {
            try {
                // Cek apakah invoice sudah ada
                $exists = Invoice::where('customer_id', $customer->id)
                    ->where('period_year', $periodYear)
                    ->where('period_month', $periodMonth)
                    ->exists();

                if ($exists) {
                    $results['skipped']++;
                    continue;
                }

                // Generate invoice
                $this->createInvoice($customer, $periodMonth, $periodYear);
                $results['created']++;

            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'customer_id' => $customer->customer_id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Buat invoice untuk satu pelanggan
     */
    public function createInvoice(
        Customer $customer,
        int $periodMonth,
        int $periodYear
    ): Invoice {
        return DB::transaction(function () use ($customer, $periodMonth, $periodYear) {
            $package = $customer->package;

            // Hitung periode
            $periodStart = Carbon::create($periodYear, $periodMonth, 1)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();

            // Hitung tagihan
            $packagePrice = $package->price;
            $additionalFee = 0; // Bisa ditambahkan logika biaya tambahan
            $discount = $this->calculateDiscount($customer);
            $subtotal = $packagePrice + $additionalFee - $discount;

            // Hitung PPN jika aktif
            $ppnEnabled = Setting::getValue('billing', 'ppn_enabled', false);
            $ppn = 0;
            if ($ppnEnabled) {
                $ppnPercentage = Setting::getValue('billing', 'ppn_percentage', 11);
                $ppn = $subtotal * ($ppnPercentage / 100);
            }

            $totalAmount = $subtotal + $ppn;

            // Hitung due date
            $dueDays = Setting::getValue('billing', 'due_days', 20);
            $dueDate = $periodStart->copy()->addDays($dueDays);

            // Generate nomor invoice
            $invoiceNumber = $this->generateInvoiceNumber($periodYear, $periodMonth);

            // Buat invoice
            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $customer->id,
                'period_month' => $periodMonth,
                'period_year' => $periodYear,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'package_name' => $package->name,
                'package_price' => $packagePrice,
                'additional_fee' => $additionalFee,
                'discount' => $discount,
                'ppn' => $ppn,
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'remaining_amount' => $totalAmount,
                'status' => 'pending',
                'due_date' => $dueDate,
            ]);

            // Tambahkan ke hutang pelanggan
            $this->debtService->addDebt(
                $customer,
                $totalAmount,
                'invoice_added',
                'invoice',
                $invoice->id,
                "Invoice {$invoiceNumber} periode {$periodMonth}/{$periodYear}"
            );

            // Dispatch event
            event(new InvoiceGenerated($invoice));

            return $invoice;
        });
    }

    /**
     * Generate nomor invoice unik
     * Format: INV-YYYYMM-XXXXX
     */
    protected function generateInvoiceNumber(int $year, int $month): string
    {
        $prefix = Setting::getValue('billing', 'invoice_prefix', 'INV');
        $periodCode = sprintf('%04d%02d', $year, $month);

        // Ambil nomor terakhir untuk periode ini
        $lastInvoice = Invoice::where('period_year', $year)
            ->where('period_month', $month)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastInvoice) {
            // Ekstrak nomor urut dari invoice terakhir
            preg_match('/(\d+)$/', $lastInvoice->invoice_number, $matches);
            $sequence = intval($matches[1] ?? 0) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s-%s-%05d', $prefix, $periodCode, $sequence);
    }

    /**
     * Hitung diskon (bisa dikustomisasi)
     */
    protected function calculateDiscount(Customer $customer): float
    {
        // Contoh: Diskon untuk pelanggan lama (lebih dari 1 tahun)
        $joinDate = Carbon::parse($customer->join_date);
        $yearsAsCustomer = $joinDate->diffInYears(now());

        if ($yearsAsCustomer >= 2) {
            return $customer->package->price * 0.05; // Diskon 5%
        }

        return 0;
    }
}
```

---

## 2. Algoritma Pembayaran & Pengurangan Hutang

### 2.1 Flowchart Pembayaran

```
┌──────────────────────────────────────────────────────────────────────┐
│                    PROSES PEMBAYARAN PELANGGAN                        │
└──────────────────────────────────────────────────────────────────────┘

    ┌─────────┐
    │  START  │
    └────┬────┘
         │
         ▼
┌────────────────────────┐
│ Input Pembayaran:      │
│ - Customer ID          │
│ - Jumlah bayar         │
│ - Metode pembayaran    │
│ - Invoice (opsional)   │
└──────────┬─────────────┘
           │
           ▼
    ┌──────────────┐
    │ Invoice      │
    │ specified?   │
    └──────┬───────┘
           │
     ┌─────┴─────┐
     │           │
    Ya          Tidak
     │           │
     ▼           ▼
┌──────────┐  ┌────────────────────┐
│ Bayar    │  │ Alokasi Otomatis:  │
│ Invoice  │  │ 1. Invoice tertua  │
│ spesifik │  │    (FIFO)          │
└────┬─────┘  │ 2. Sisa ke hutang  │
     │        └──────────┬─────────┘
     │                   │
     └─────────┬─────────┘
               │
               ▼
┌────────────────────────┐
│ Proses Alokasi:        │
│                        │
│ WHILE sisa_bayar > 0   │
│ AND ada invoice unpaid │
│                        │
│   bayar_invoice()      │
│   update status        │
│                        │
│ ENDWHILE               │
│                        │
│ IF sisa_bayar > 0      │
│   kurangi total_debt   │
│ ENDIF                  │
└──────────┬─────────────┘
           │
           ▼
┌────────────────────────┐
│ Buat record Payment    │
└──────────┬─────────────┘
           │
           ▼
┌────────────────────────┐
│ Catat debt_history:    │
│ type: payment_received │
│ amount: -jumlah_bayar  │
└──────────┬─────────────┘
           │
           ▼
    ┌──────────────┐      Ya
    │ Customer     ├─────────────┐
    │ terisolir?   │             │
    └──────┬───────┘             │
           │ Tidak               │
           │                     ▼
           │        ┌────────────────────────┐
           │        │ Cek apakah semua       │
           │        │ invoice lunas?         │
           │        └──────────┬─────────────┘
           │                   │
           │             ┌─────┴─────┐
           │            Ya          Tidak
           │             │            │
           │             ▼            │
           │   ┌──────────────────┐   │
           │   │ Dispatch Job:    │   │
           │   │ OpenAccessJob    │   │
           │   └────────┬─────────┘   │
           │            │             │
           │            ▼             │
           │   ┌──────────────────┐   │
           │   │ Buka akses di    │   │
           │   │ Mikrotik         │   │
           │   └────────┬─────────┘   │
           │            │             │
           │◄───────────┴─────────────┘
           │
           ▼
┌────────────────────────┐
│ Kirim notifikasi       │
│ pembayaran berhasil    │
└──────────┬─────────────┘
           │
           ▼
    ┌──────────┐
    │   END    │
    └──────────┘
```

### 2.2 Service Pembayaran

```php
// app/Services/PaymentService.php
namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Jobs\OpenAccessJob;
use App\Events\PaymentReceived;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    protected DebtService $debtService;
    protected BillingLogService $logger;

    public function __construct(
        DebtService $debtService,
        BillingLogService $logger
    ) {
        $this->debtService = $debtService;
        $this->logger = $logger;
    }

    /**
     * Proses pembayaran dari pelanggan
     */
    public function processPayment(
        Customer $customer,
        float $amount,
        string $paymentMethod,
        ?string $paymentChannel = null,
        ?int $invoiceId = null,
        ?string $referenceNumber = null,
        ?int $receivedBy = null,
        ?string $notes = null
    ): Payment {
        return DB::transaction(function () use (
            $customer, $amount, $paymentMethod, $paymentChannel,
            $invoiceId, $referenceNumber, $receivedBy, $notes
        ) {
            $allocatedToInvoice = 0;
            $allocatedToDebt = 0;
            $remainingAmount = $amount;

            // Jika invoice spesifik ditentukan
            if ($invoiceId) {
                $invoice = Invoice::findOrFail($invoiceId);
                $paidAmount = min($remainingAmount, $invoice->remaining_amount);

                $this->payInvoice($invoice, $paidAmount);
                $allocatedToInvoice += $paidAmount;
                $remainingAmount -= $paidAmount;
            }

            // Alokasi ke invoice tertua yang belum lunas (FIFO)
            if ($remainingAmount > 0) {
                $unpaidInvoices = Invoice::where('customer_id', $customer->id)
                    ->whereIn('status', ['pending', 'partial', 'overdue'])
                    ->orderBy('period_year', 'asc')
                    ->orderBy('period_month', 'asc')
                    ->get();

                foreach ($unpaidInvoices as $invoice) {
                    if ($remainingAmount <= 0) break;

                    $paidAmount = min($remainingAmount, $invoice->remaining_amount);
                    $this->payInvoice($invoice, $paidAmount);

                    $allocatedToInvoice += $paidAmount;
                    $remainingAmount -= $paidAmount;
                }
            }

            // Sisa pembayaran masuk ke pengurangan hutang langsung
            if ($remainingAmount > 0) {
                $allocatedToDebt = $remainingAmount;
            }

            // Buat record pembayaran
            $payment = Payment::create([
                'payment_number' => $this->generatePaymentNumber(),
                'customer_id' => $customer->id,
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'payment_channel' => $paymentChannel,
                'reference_number' => $referenceNumber,
                'allocated_to_invoice' => $allocatedToInvoice,
                'allocated_to_debt' => $allocatedToDebt,
                'received_by' => $receivedBy,
                'notes' => $notes,
            ]);

            // Kurangi hutang pelanggan
            $this->debtService->reduceDebt(
                $customer,
                $amount,
                'payment_received',
                'payment',
                $payment->id,
                "Pembayaran {$payment->payment_number}"
            );

            // Cek apakah perlu buka akses
            $this->checkAndOpenAccess($customer);

            // Log aktivitas
            $this->logger->log(
                'payment_received',
                $customer,
                "Pembayaran diterima: Rp " . number_format($amount, 0, ',', '.'),
                [
                    'payment_id' => $payment->id,
                    'amount' => $amount,
                    'method' => $paymentMethod,
                ]
            );

            // Dispatch event
            event(new PaymentReceived($payment));

            return $payment;
        });
    }

    /**
     * Bayar invoice (update status dan remaining)
     */
    protected function payInvoice(Invoice $invoice, float $amount): void
    {
        $newPaidAmount = $invoice->paid_amount + $amount;
        $newRemainingAmount = $invoice->total_amount - $newPaidAmount;

        // Tentukan status baru
        $status = 'partial';
        $paidAt = null;

        if ($newRemainingAmount <= 0) {
            $status = 'paid';
            $paidAt = now();
            $newRemainingAmount = 0;
        }

        $invoice->update([
            'paid_amount' => $newPaidAmount,
            'remaining_amount' => $newRemainingAmount,
            'status' => $status,
            'paid_at' => $paidAt,
        ]);
    }

    /**
     * Cek dan buka akses jika semua invoice lunas
     */
    protected function checkAndOpenAccess(Customer $customer): void
    {
        // Hanya proses jika customer terisolir
        if ($customer->status !== 'isolated') {
            return;
        }

        // Cek apakah masih ada invoice belum lunas
        $hasUnpaidInvoices = Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->exists();

        // Jika semua lunas, buka akses
        if (!$hasUnpaidInvoices) {
            OpenAccessJob::dispatch($customer);
        }
    }

    /**
     * Generate nomor pembayaran
     * Format: PAY-YYYYMMDD-XXXXX
     */
    protected function generatePaymentNumber(): string
    {
        $prefix = Setting::getValue('billing', 'payment_prefix', 'PAY');
        $dateCode = now()->format('Ymd');

        $lastPayment = Payment::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        if ($lastPayment) {
            preg_match('/(\d+)$/', $lastPayment->payment_number, $matches);
            $sequence = intval($matches[1] ?? 0) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s-%s-%05d', $prefix, $dateCode, $sequence);
    }
}
```

---

## 3. Service Pengelolaan Hutang

### 3.1 Debt Service

```php
// app/Services/DebtService.php
namespace App\Services;

use App\Models\Customer;
use App\Models\DebtHistory;
use Illuminate\Support\Facades\DB;

class DebtService
{
    /**
     * Tambah hutang pelanggan
     */
    public function addDebt(
        Customer $customer,
        float $amount,
        string $transactionType,
        string $referenceType,
        int $referenceId,
        string $description
    ): DebtHistory {
        return DB::transaction(function () use (
            $customer, $amount, $transactionType, $referenceType, $referenceId, $description
        ) {
            $previousDebt = $customer->total_debt;
            $newDebt = $previousDebt + $amount;

            // Update total_debt pelanggan
            $customer->update(['total_debt' => $newDebt]);

            // Catat history
            return DebtHistory::create([
                'customer_id' => $customer->id,
                'transaction_type' => $transactionType,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'amount' => $amount, // Positif = tambah hutang
                'previous_debt' => $previousDebt,
                'current_debt' => $newDebt,
                'description' => $description,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Kurangi hutang pelanggan (saat pembayaran/cicilan)
     */
    public function reduceDebt(
        Customer $customer,
        float $amount,
        string $transactionType,
        string $referenceType,
        int $referenceId,
        string $description
    ): DebtHistory {
        return DB::transaction(function () use (
            $customer, $amount, $transactionType, $referenceType, $referenceId, $description
        ) {
            $previousDebt = $customer->total_debt;
            $newDebt = max(0, $previousDebt - $amount); // Tidak boleh negatif

            // Update total_debt pelanggan
            $customer->update(['total_debt' => $newDebt]);

            // Catat history
            return DebtHistory::create([
                'customer_id' => $customer->id,
                'transaction_type' => $transactionType,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'amount' => -$amount, // Negatif = kurang hutang
                'previous_debt' => $previousDebt,
                'current_debt' => $newDebt,
                'description' => $description,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Adjustment hutang (koreksi manual)
     */
    public function adjustDebt(
        Customer $customer,
        float $newDebtAmount,
        string $description
    ): DebtHistory {
        return DB::transaction(function () use ($customer, $newDebtAmount, $description) {
            $previousDebt = $customer->total_debt;
            $difference = $newDebtAmount - $previousDebt;

            // Update total_debt pelanggan
            $customer->update(['total_debt' => $newDebtAmount]);

            // Catat history
            return DebtHistory::create([
                'customer_id' => $customer->id,
                'transaction_type' => 'adjustment',
                'reference_type' => 'manual',
                'reference_id' => null,
                'amount' => $difference,
                'previous_debt' => $previousDebt,
                'current_debt' => $newDebtAmount,
                'description' => $description,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Write-off hutang (penghapusan hutang)
     */
    public function writeOffDebt(
        Customer $customer,
        float $amount,
        string $reason
    ): DebtHistory {
        return DB::transaction(function () use ($customer, $amount, $reason) {
            $previousDebt = $customer->total_debt;
            $writeOffAmount = min($amount, $previousDebt); // Tidak melebihi hutang
            $newDebt = $previousDebt - $writeOffAmount;

            // Update total_debt pelanggan
            $customer->update(['total_debt' => $newDebt]);

            // Catat history
            return DebtHistory::create([
                'customer_id' => $customer->id,
                'transaction_type' => 'write_off',
                'reference_type' => 'manual',
                'reference_id' => null,
                'amount' => -$writeOffAmount,
                'previous_debt' => $previousDebt,
                'current_debt' => $newDebt,
                'description' => "Write-off: {$reason}",
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Ambil riwayat hutang pelanggan
     */
    public function getDebtHistory(Customer $customer, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return DebtHistory::where('customer_id', $customer->id)
            ->with('createdBy')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Hitung total hutang dari invoice
     */
    public function recalculateDebt(Customer $customer): float
    {
        $totalUnpaid = $customer->invoices()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->sum('remaining_amount');

        $customer->update(['total_debt' => $totalUnpaid]);

        return $totalUnpaid;
    }
}
```

---

## 4. Contoh Skenario Pembayaran

### Skenario 1: Bayar Lunas 1 Invoice

```
Pelanggan: PLG-00001
Hutang Awal: Rp 400.000 (2 invoice @ Rp 200.000)

Invoice #1: Rp 200.000 (Januari) - pending
Invoice #2: Rp 200.000 (Februari) - pending

Pembayaran: Rp 200.000

HASIL:
- Invoice #1: Rp 0 (PAID)
- Invoice #2: Rp 200.000 (pending)
- Total Hutang: Rp 200.000
```

### Skenario 2: Bayar Sebagian (Cicilan)

```
Pelanggan: PLG-00001
Hutang Awal: Rp 400.000

Invoice #1: Rp 200.000 (Januari) - pending

Pembayaran: Rp 100.000

HASIL:
- Invoice #1: Rp 100.000 remaining (PARTIAL)
- Total Hutang: Rp 300.000
```

### Skenario 3: Bayar Lebih dari Invoice

```
Pelanggan: PLG-00001
Hutang Awal: Rp 400.000

Invoice #1: Rp 200.000 (Januari) - pending
Invoice #2: Rp 200.000 (Februari) - pending

Pembayaran: Rp 350.000

HASIL (FIFO):
- Invoice #1: Rp 0 (PAID) - Alokasi Rp 200.000
- Invoice #2: Rp 50.000 remaining (PARTIAL) - Alokasi Rp 150.000
- Total Hutang: Rp 50.000
```

### Skenario 4: Bayar Semua + Kelebihan

```
Pelanggan: PLG-00001
Hutang Awal: Rp 400.000

Invoice #1: Rp 200.000 (Januari) - pending
Invoice #2: Rp 200.000 (Februari) - pending

Pembayaran: Rp 500.000

HASIL:
- Invoice #1: Rp 0 (PAID)
- Invoice #2: Rp 0 (PAID)
- Total Hutang: Rp 0
- Kelebihan Rp 100.000 dicatat (bisa jadi deposit/dikembalikan)
- Akses DIBUKA OTOMATIS (jika sebelumnya terisolir)
```

---

## 5. Command Artisan

### 5.1 Generate Invoice Bulanan

```php
// app/Console/Commands/GenerateMonthlyInvoices.php
namespace App\Console\Commands;

use App\Services\InvoiceService;
use Illuminate\Console\Command;

class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'billing:generate-invoices
                            {--month= : Bulan spesifik (1-12)}
                            {--year= : Tahun spesifik}';

    protected $description = 'Generate invoice bulanan untuk semua pelanggan';

    public function handle(InvoiceService $invoiceService): int
    {
        $this->info('Memulai generate invoice...');

        $results = $invoiceService->generateMonthlyInvoices();

        $this->info("Total pelanggan: {$results['total']}");
        $this->info("Invoice dibuat: {$results['created']}");
        $this->info("Dilewati (sudah ada): {$results['skipped']}");

        if ($results['failed'] > 0) {
            $this->error("Gagal: {$results['failed']}");
            foreach ($results['errors'] as $error) {
                $this->error("  - {$error['customer_id']}: {$error['error']}");
            }
        }

        return Command::SUCCESS;
    }
}
```

### 5.2 Cek Invoice Overdue

```php
// app/Console/Commands/CheckOverdueInvoices.php
namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Setting;
use App\Jobs\IsolateCustomerJob;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CheckOverdueInvoices extends Command
{
    protected $signature = 'billing:check-overdue';
    protected $description = 'Cek invoice overdue dan isolir pelanggan';

    public function handle(): int
    {
        $autoIsolate = Setting::getValue('billing', 'auto_isolate', true);
        $graceDays = Setting::getValue('billing', 'isolate_grace_days', 7);

        if (!$autoIsolate) {
            $this->info('Auto isolate tidak aktif');
            return Command::SUCCESS;
        }

        $overdueDate = Carbon::now()->subDays($graceDays);

        // Update status invoice yang overdue
        Invoice::where('status', 'pending')
            ->where('due_date', '<', now())
            ->update(['status' => 'overdue']);

        // Cari pelanggan dengan invoice overdue melewati grace period
        $customersToIsolate = Customer::where('status', 'active')
            ->whereHas('invoices', function ($query) use ($overdueDate) {
                $query->where('status', 'overdue')
                    ->where('due_date', '<', $overdueDate);
            })
            ->get();

        $this->info("Ditemukan {$customersToIsolate->count()} pelanggan untuk diisolir");

        foreach ($customersToIsolate as $customer) {
            IsolateCustomerJob::dispatch($customer, 'Tunggakan pembayaran melewati batas');
            $this->info("  - {$customer->customer_id}: {$customer->name}");
        }

        return Command::SUCCESS;
    }
}
```

---

## 6. Model Setting Helper

```php
// app/Models/Setting.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type', 'description'];

    /**
     * Ambil nilai setting dengan caching
     */
    public static function getValue(string $group, string $key, $default = null)
    {
        $cacheKey = "setting.{$group}.{$key}";

        return Cache::remember($cacheKey, 3600, function () use ($group, $key, $default) {
            $setting = static::where('group', $group)
                ->where('key', $key)
                ->first();

            if (!$setting) {
                return $default;
            }

            return static::castValue($setting->value, $setting->type);
        });
    }

    /**
     * Set nilai setting
     */
    public static function setValue(string $group, string $key, $value): void
    {
        static::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => is_array($value) ? json_encode($value) : $value]
        );

        Cache::forget("setting.{$group}.{$key}");
    }

    /**
     * Cast value sesuai type
     */
    protected static function castValue($value, $type)
    {
        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
```
