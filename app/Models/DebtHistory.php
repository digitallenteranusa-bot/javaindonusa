<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'invoice_id',
        'payment_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'reference_number',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    // ================================================================
    // CONSTANTS
    // ================================================================

    const TYPE_CHARGE = 'charge';
    const TYPE_PAYMENT = 'payment';
    const TYPE_ADJUSTMENT_ADD = 'adjustment_add';
    const TYPE_ADJUSTMENT_SUBTRACT = 'adjustment_subtract';
    const TYPE_DISCOUNT = 'discount';
    const TYPE_LATE_FEE = 'late_fee';
    const TYPE_WRITEOFF = 'writeoff';
    const TYPE_CREDIT_ADDED = 'credit_added';
    const TYPE_CREDIT_USED = 'credit_used';

    // ================================================================
    // RELATIONSHIPS
    // ================================================================

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopeCharges($query)
    {
        return $query->where('type', self::TYPE_CHARGE);
    }

    public function scopePayments($query)
    {
        return $query->where('type', self::TYPE_PAYMENT);
    }

    public function scopeAdjustments($query)
    {
        return $query->whereIn('type', [
            self::TYPE_ADJUSTMENT_ADD,
            self::TYPE_ADJUSTMENT_SUBTRACT,
        ]);
    }

    public function scopeByCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // ================================================================
    // STATIC METHODS
    // ================================================================

    /**
     * Record a charge (tagihan)
     */
    public static function recordCharge(
        Customer $customer,
        Invoice $invoice,
        float $amount,
        ?string $description = null
    ): self {
        $balanceBefore = $customer->total_debt;
        $balanceAfter = $balanceBefore + $amount;

        return self::create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'type' => self::TYPE_CHARGE,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $description ?? "Tagihan {$invoice->period_label}",
            'reference_number' => $invoice->invoice_number,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Record a payment
     */
    public static function recordPayment(
        Customer $customer,
        Payment $payment,
        float $amount,
        ?Invoice $invoice = null,
        ?string $description = null
    ): self {
        $balanceBefore = $customer->total_debt;
        $balanceAfter = max(0, $balanceBefore - $amount);

        return self::create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice?->id,
            'payment_id' => $payment->id,
            'type' => self::TYPE_PAYMENT,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $description ?? "Pembayaran via {$payment->method_label}",
            'reference_number' => $payment->payment_number,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Record an adjustment
     */
    public static function recordAdjustment(
        Customer $customer,
        float $amount,
        string $description,
        ?int $createdBy = null
    ): self {
        $balanceBefore = $customer->total_debt;
        $type = $amount >= 0 ? self::TYPE_ADJUSTMENT_ADD : self::TYPE_ADJUSTMENT_SUBTRACT;
        $balanceAfter = max(0, $balanceBefore + $amount);

        return self::create([
            'customer_id' => $customer->id,
            'type' => $type,
            'amount' => abs($amount),
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $description,
            'created_by' => $createdBy ?? auth()->id(),
        ]);
    }

    /**
     * Record a discount
     */
    public static function recordDiscount(
        Customer $customer,
        float $amount,
        string $reason,
        ?Invoice $invoice = null
    ): self {
        $balanceBefore = $customer->total_debt;
        $balanceAfter = max(0, $balanceBefore - $amount);

        return self::create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice?->id,
            'type' => self::TYPE_DISCOUNT,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => "Diskon: {$reason}",
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Record late fee
     */
    public static function recordLateFee(
        Customer $customer,
        Invoice $invoice,
        float $amount
    ): self {
        $balanceBefore = $customer->total_debt;
        $balanceAfter = $balanceBefore + $amount;

        return self::create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'type' => self::TYPE_LATE_FEE,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => "Denda keterlambatan {$invoice->period_label}",
            'reference_number' => $invoice->invoice_number,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Record write-off
     */
    public static function recordWriteoff(
        Customer $customer,
        float $amount,
        string $reason
    ): self {
        $balanceBefore = $customer->total_debt;
        $balanceAfter = max(0, $balanceBefore - $amount);

        return self::create([
            'customer_id' => $customer->id,
            'type' => self::TYPE_WRITEOFF,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => "Write-off: {$reason}",
            'created_by' => auth()->id(),
        ]);
    }

    // ================================================================
    // ACCESSORS
    // ================================================================

    /**
     * Get type label in Indonesian
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_CHARGE => 'Tagihan',
            self::TYPE_PAYMENT => 'Pembayaran',
            self::TYPE_ADJUSTMENT_ADD => 'Penambahan',
            self::TYPE_ADJUSTMENT_SUBTRACT => 'Pengurangan',
            self::TYPE_DISCOUNT => 'Diskon',
            self::TYPE_LATE_FEE => 'Denda',
            self::TYPE_WRITEOFF => 'Write-off',
            self::TYPE_CREDIT_ADDED => 'Kredit Ditambahkan',
            self::TYPE_CREDIT_USED => 'Kredit Digunakan',
            default => $this->type,
        };
    }

    /**
     * Format amount
     */
    public function getFormattedAmountAttribute(): string
    {
        $prefix = in_array($this->type, [self::TYPE_PAYMENT, self::TYPE_DISCOUNT, self::TYPE_ADJUSTMENT_SUBTRACT, self::TYPE_WRITEOFF, self::TYPE_CREDIT_USED])
            ? '-'
            : '+';

        return $prefix . 'Rp ' . number_format($this->amount, 0, ',', '.');
    }

    /**
     * Check if this is a debit transaction (increases debt)
     */
    public function isDebit(): bool
    {
        return in_array($this->type, [
            self::TYPE_CHARGE,
            self::TYPE_ADJUSTMENT_ADD,
            self::TYPE_LATE_FEE,
        ]);
    }

    /**
     * Check if this is a credit transaction (decreases debt)
     */
    public function isCredit(): bool
    {
        return in_array($this->type, [
            self::TYPE_PAYMENT,
            self::TYPE_ADJUSTMENT_SUBTRACT,
            self::TYPE_DISCOUNT,
            self::TYPE_WRITEOFF,
            self::TYPE_CREDIT_USED,
        ]);
    }
}
