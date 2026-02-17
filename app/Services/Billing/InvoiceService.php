<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\BillingLog;
use App\Models\DebtHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class InvoiceService
{
    protected DebtService $debtService;

    public function __construct(DebtService $debtService)
    {
        $this->debtService = $debtService;
    }

    /**
     * Generate invoices for all active customers
     */
    public function generateMonthlyInvoices(?int $month = null, ?int $year = null): array
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        $customers = Customer::where('status', 'active')
            ->with('package')
            ->get();

        $generated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($customers as $customer) {
            try {
                $result = $this->generateInvoiceForCustomer($customer, $month, $year);
                if ($result) {
                    $generated++;
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Log the batch generation
        BillingLog::create([
            'type' => 'invoice_generation',
            'description' => "Generated invoices for {$month}/{$year}",
            'data' => [
                'month' => $month,
                'year' => $year,
                'generated' => $generated,
                'skipped' => $skipped,
                'errors' => $errors,
            ],
        ]);

        return [
            'generated' => $generated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Generate invoice for a specific customer
     */
    public function generateInvoiceForCustomer(Customer $customer, int $month, int $year): ?Invoice
    {
        // Check if invoice already exists (exclude cancelled)
        $existingInvoice = Invoice::where('customer_id', $customer->id)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->where('status', '!=', 'cancelled')
            ->first();

        if ($existingInvoice) {
            return null;
        }

        // Skip if customer has no package
        if (!$customer->package) {
            return null;
        }

        // Skip if billing hasn't started yet
        if ($customer->billing_start_date) {
            $billingStart = Carbon::parse($customer->billing_start_date)->startOfMonth();
            $invoicePeriod = Carbon::create($year, $month, 1);
            if ($invoicePeriod->lt($billingStart)) {
                return null;
            }
        }

        return DB::transaction(function () use ($customer, $month, $year) {
            $package = $customer->package;
            $dueDate = Carbon::create($year, $month, config('billing.due_days', 20));

            // Calculate period
            $periodStart = Carbon::create($year, $month, 1);
            $periodEnd = $periodStart->copy()->endOfMonth();

            $packagePrice = $package->price;

            // Hitung diskon dari data pelanggan
            $discount = 0;
            $discountReason = null;
            if ($customer->discount_type === 'nominal' && $customer->discount_value > 0) {
                $discount = $customer->discount_value;
                $discountReason = $customer->discount_reason;
            } elseif ($customer->discount_type === 'percentage' && $customer->discount_value > 0) {
                $discount = round($packagePrice * $customer->discount_value / 100, 2);
                $discountReason = $customer->discount_reason
                    ? "{$customer->discount_reason} ({$customer->discount_value}%)"
                    : "Diskon {$customer->discount_value}%";
            }

            $subtotal = $packagePrice - $discount;

            // Hitung PPN 11% jika pelanggan kena pajak
            $ppn = 0;
            if ($customer->is_taxed) {
                $ppn = round($subtotal * 0.11, 2);
            }

            $totalAmount = $subtotal + $ppn;

            $invoice = Invoice::create([
                'customer_id' => $customer->id,
                'invoice_number' => $this->generateInvoiceNumber($year, $month),
                'period_month' => $month,
                'period_year' => $year,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'package_name' => $package->name,
                'package_price' => $packagePrice,
                'additional_charges' => $ppn,
                'discount' => $discount,
                'discount_reason' => $discountReason,
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'remaining_amount' => $totalAmount,
                'due_date' => $dueDate,
                'status' => 'pending',
            ]);

            // Add to debt
            $this->debtService->addDebt(
                $customer,
                $invoice->total_amount,
                'invoice_added',
                'invoice',
                $invoice->id,
                "Invoice #{$invoice->invoice_number} - Periode {$month}/{$year}"
            );

            return $invoice;
        });
    }

    /**
     * Generate unique invoice number
     */
    protected function generateInvoiceNumber(int $year, int $month): string
    {
        $prefix = 'INV';
        $dateCode = sprintf('%04d%02d', $year, $month);

        $lastInvoice = Invoice::where('invoice_number', 'like', "{$prefix}{$dateCode}%")
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->invoice_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s%s%04d', $prefix, $dateCode, $newNumber);
    }

    /**
     * Create historical invoice for past period (hutang lama)
     */
    public function createHistoricalInvoice(
        Customer $customer,
        int $month,
        int $year,
        float $amount,
        ?string $description = null
    ): Invoice {
        // Check if invoice already exists for this period (exclude cancelled)
        $existingInvoice = Invoice::where('customer_id', $customer->id)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->where('status', '!=', 'cancelled')
            ->first();

        if ($existingInvoice) {
            throw new \Exception("Invoice untuk periode {$month}/{$year} sudah ada (#{$existingInvoice->invoice_number})");
        }

        return DB::transaction(function () use ($customer, $month, $year, $amount, $description) {
            $dueDate = Carbon::create($year, $month, config('billing.due_days', 20));
            $periodStart = Carbon::create($year, $month, 1);
            $periodEnd = $periodStart->copy()->endOfMonth();

            $packageName = $customer->package?->name ?? 'Paket tidak diketahui';

            $invoice = Invoice::create([
                'customer_id' => $customer->id,
                'invoice_number' => $this->generateInvoiceNumber($year, $month),
                'period_month' => $month,
                'period_year' => $year,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'package_name' => $packageName,
                'package_price' => $amount,
                'additional_charges' => 0,
                'discount' => 0,
                'total_amount' => $amount,
                'paid_amount' => 0,
                'remaining_amount' => $amount,
                'due_date' => $dueDate,
                'status' => 'overdue',
                'notes' => $description ?? "Invoice hutang lama periode {$month}/{$year}",
            ]);

            // Add to debt
            $this->debtService->addDebt(
                $customer,
                $amount,
                'invoice_added',
                'invoice',
                $invoice->id,
                "Hutang lama - Invoice #{$invoice->invoice_number} - Periode {$month}/{$year}"
            );

            return $invoice;
        });
    }

    /**
     * Update overdue status for all invoices
     */
    public function updateOverdueStatus(): array
    {
        $today = now()->startOfDay();
        $graceDays = config('billing.grace_days', 7);

        $invoices = Invoice::whereIn('status', ['pending', 'partial'])
            ->where('due_date', '<', $today->subDays($graceDays))
            ->get();

        $updated = 0;
        foreach ($invoices as $invoice) {
            $invoice->update(['status' => 'overdue']);
            $updated++;
        }

        return ['updated' => $updated];
    }

    /**
     * Mark invoice as paid (manual)
     */
    public function markAsPaid(Invoice $invoice, ?string $notes = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $notes) {
            $remainingAmount = $invoice->remaining_amount;

            $invoice->update([
                'paid_amount' => $invoice->total_amount,
                'remaining_amount' => 0,
                'status' => 'paid',
                'paid_at' => now(),
                'notes' => $notes,
            ]);

            // Reduce debt
            if ($remainingAmount > 0) {
                $this->debtService->reduceDebt(
                    $invoice->customer,
                    $remainingAmount,
                    "Manual payment - Invoice #{$invoice->invoice_number}"
                );
            }

            return $invoice->fresh();
        });
    }

    /**
     * Cancel invoice
     */
    public function cancelInvoice(Invoice $invoice, string $reason): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason) {
            // Only pending invoices can be cancelled
            if (!in_array($invoice->status, ['pending'])) {
                throw new \Exception('Hanya invoice pending yang dapat dibatalkan');
            }

            // Reduce debt for cancelled invoice
            $this->debtService->reduceDebt(
                $invoice->customer,
                $invoice->remaining_amount,
                "Invoice cancelled - #{$invoice->invoice_number}: {$reason}"
            );

            $invoice->update([
                'status' => 'cancelled',
                'notes' => $reason,
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * Get invoice statistics
     */
    public function getStatistics(?int $month = null, ?int $year = null): array
    {
        $query = Invoice::query();

        if ($month && $year) {
            $query->where('period_month', $month)->where('period_year', $year);
        }

        $invoices = $query->get();

        return [
            'total' => $invoices->count(),
            'pending' => $invoices->where('status', 'pending')->count(),
            'partial' => $invoices->where('status', 'partial')->count(),
            'paid' => $invoices->where('status', 'paid')->count(),
            'overdue' => $invoices->where('status', 'overdue')->count(),
            'cancelled' => $invoices->where('status', 'cancelled')->count(),
            'total_billed' => $invoices->whereNotIn('status', ['cancelled'])->sum('total_amount'),
            'total_paid' => $invoices->sum('paid_amount'),
            'total_outstanding' => $invoices->whereNotIn('status', ['paid', 'cancelled'])->sum('remaining_amount'),
        ];
    }

    /**
     * Get overdue invoices for a customer
     */
    public function getOverdueInvoices(Customer $customer): \Illuminate\Database\Eloquent\Collection
    {
        return Invoice::where('customer_id', $customer->id)
            ->where('status', 'overdue')
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->get();
    }

    /**
     * Get unpaid invoices count for a customer
     */
    public function getUnpaidMonthsCount(Customer $customer): int
    {
        return Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->count();
    }
}
