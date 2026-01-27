<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Settlement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'collector_id',
        'settlement_number',
        'settlement_date',
        'period_start',
        'period_end',
        'total_collection',
        'cash_collection',
        'transfer_collection',
        'total_expense',
        'approved_expense',
        'commission_rate',
        'commission_amount',
        'expected_amount',
        'actual_amount',
        'difference',
        'status',
        'received_by',
        'verified_by',
        'verified_at',
        'notes',
        'verification_notes',
    ];

    protected function casts(): array
    {
        return [
            'settlement_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'total_collection' => 'decimal:2',
            'cash_collection' => 'decimal:2',
            'transfer_collection' => 'decimal:2',
            'total_expense' => 'decimal:2',
            'approved_expense' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'difference' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }

    // ================================================================
    // CONSTANTS
    // ================================================================

    const STATUS_PENDING = 'pending';
    const STATUS_SETTLED = 'settled';
    const STATUS_DISCREPANCY = 'discrepancy';
    const STATUS_VERIFIED = 'verified';
    const STATUS_REJECTED = 'rejected';

    // ================================================================
    // RELATIONSHIPS
    // ================================================================

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSettled($query)
    {
        return $query->where('status', self::STATUS_SETTLED);
    }

    public function scopeDiscrepancy($query)
    {
        return $query->where('status', self::STATUS_DISCREPANCY);
    }

    public function scopeByCollector($query, int $collectorId)
    {
        return $query->where('collector_id', $collectorId);
    }

    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('settlement_date', [$startDate, $endDate]);
    }

    // ================================================================
    // ACCESSORS & HELPERS
    // ================================================================

    /**
     * Format expected amount
     */
    public function getFormattedExpectedAttribute(): string
    {
        return 'Rp ' . number_format($this->expected_amount, 0, ',', '.');
    }

    /**
     * Format actual amount
     */
    public function getFormattedActualAttribute(): string
    {
        return 'Rp ' . number_format($this->actual_amount, 0, ',', '.');
    }

    /**
     * Format difference
     */
    public function getFormattedDifferenceAttribute(): string
    {
        $prefix = $this->difference >= 0 ? '+' : '';
        return $prefix . 'Rp ' . number_format($this->difference, 0, ',', '.');
    }

    /**
     * Get status label in Indonesian
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Menunggu',
            self::STATUS_SETTLED => 'Selesai',
            self::STATUS_DISCREPANCY => 'Selisih',
            self::STATUS_VERIFIED => 'Terverifikasi',
            self::STATUS_REJECTED => 'Ditolak',
            default => $this->status,
        };
    }

    /**
     * Get period label
     */
    public function getPeriodLabelAttribute(): string
    {
        if ($this->period_start->eq($this->period_end)) {
            return $this->period_start->format('d M Y');
        }

        return $this->period_start->format('d M Y') . ' - ' . $this->period_end->format('d M Y');
    }

    /**
     * Check if settlement has discrepancy
     */
    public function hasDiscrepancy(): bool
    {
        return abs($this->difference) >= 1;
    }

    /**
     * Verify this settlement
     */
    public function verify(User $admin, float $actualAmount, ?string $notes = null): void
    {
        $difference = $actualAmount - $this->expected_amount;
        $status = abs($difference) < 1 ? self::STATUS_SETTLED : self::STATUS_DISCREPANCY;

        $this->update([
            'actual_amount' => $actualAmount,
            'difference' => $difference,
            'status' => $status,
            'received_by' => $admin->id,
            'verified_at' => now(),
            'notes' => $notes,
        ]);
    }
}
