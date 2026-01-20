<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'period_month',
        'period_year',
        'package_id',
        'package_name',
        'package_price',
        'additional_charges',
        'discount',
        'discount_reason',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'status',
        'due_date',
        'paid_at',
        'generated_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'integer',
            'period_year' => 'integer',
            'package_price' => 'decimal:2',
            'additional_charges' => 'decimal:2',
            'discount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'generated_at' => 'datetime',
        ];
    }

    // ================================================================
    // CONSTANTS
    // ================================================================

    const STATUS_PENDING = 'pending';
    const STATUS_PARTIAL = 'partial';
    const STATUS_PAID = 'paid';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_CANCELLED = 'cancelled';

    // ================================================================
    // RELATIONSHIPS
    // ================================================================

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Direct relationship (single invoice_id on payment)
     */
    public function directPayments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Many-to-many relationship with payments for allocation tracking
     */
    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'invoice_payment')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function debtHistories(): HasMany
    {
        return $this->hasMany(DebtHistory::class);
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePartial($query)
    {
        return $query->where('status', self::STATUS_PARTIAL);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_OVERDUE);
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_PARTIAL, self::STATUS_OVERDUE]);
    }

    public function scopeForPeriod($query, int $month, int $year)
    {
        return $query->where('period_month', $month)
            ->where('period_year', $year);
    }

    public function scopeCurrentMonth($query)
    {
        return $query->forPeriod(now()->month, now()->year);
    }

    // ================================================================
    // ACCESSORS & HELPERS
    // ================================================================

    /**
     * Format periode dalam Bahasa Indonesia
     */
    public function getPeriodLabelAttribute(): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $months[$this->period_month] . ' ' . $this->period_year;
    }

    /**
     * Format total amount
     */
    public function getFormattedTotalAttribute(): string
    {
        return 'Rp ' . number_format($this->total_amount, 0, ',', '.');
    }

    /**
     * Format remaining amount
     */
    public function getFormattedRemainingAttribute(): string
    {
        return 'Rp ' . number_format($this->remaining_amount, 0, ',', '.');
    }

    /**
     * Check if invoice is overdue
     */
    public function isOverdue(): bool
    {
        if ($this->status === self::STATUS_PAID) {
            return false;
        }

        return $this->due_date && $this->due_date->lt(Carbon::today());
    }

    /**
     * Calculate days overdue
     */
    public function getDaysOverdueAttribute(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }

        return $this->due_date->diffInDays(Carbon::today());
    }

    /**
     * Record payment to this invoice
     */
    public function recordPayment(float $amount): void
    {
        $this->paid_amount += $amount;
        $this->remaining_amount = max(0, $this->total_amount - $this->paid_amount);

        if ($this->remaining_amount <= 0) {
            $this->status = self::STATUS_PAID;
            $this->paid_at = now();
        } elseif ($this->paid_amount > 0) {
            $this->status = self::STATUS_PARTIAL;
        }

        $this->save();
    }

    /**
     * Generate invoice number
     */
    public static function generateInvoiceNumber(int $customerId, int $month, int $year): string
    {
        return sprintf('INV-%04d-%02d%d', $customerId, $month, $year);
    }

    /**
     * Check status and update if overdue
     */
    public function checkAndUpdateOverdueStatus(): void
    {
        if ($this->isOverdue() && $this->status !== self::STATUS_OVERDUE) {
            $this->update(['status' => self::STATUS_OVERDUE]);
        }
    }
}
