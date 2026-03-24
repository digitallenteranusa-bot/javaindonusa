<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'total_debt_amount',
        'installment_count',
        'installment_amount',
        'paid_amount',
        'remaining_amount',
        'start_date',
        'end_date',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_debt_amount' => 'decimal:2',
            'installment_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_DEFAULTED = 'defaulted';

    // ================================================================
    // RELATIONSHIPS
    // ================================================================

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(PaymentPlanInstallment::class)->orderBy('installment_number');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    // ================================================================
    // ACCESSORS
    // ================================================================

    public function getFormattedTotalAttribute(): string
    {
        return 'Rp ' . number_format($this->total_debt_amount, 0, ',', '.');
    }

    public function getFormattedInstallmentAttribute(): string
    {
        return 'Rp ' . number_format($this->installment_amount, 0, ',', '.');
    }

    public function getProgressPercentAttribute(): float
    {
        if ($this->total_debt_amount <= 0) return 100;
        return round(($this->paid_amount / $this->total_debt_amount) * 100, 1);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'Berjalan',
            self::STATUS_COMPLETED => 'Lunas',
            self::STATUS_CANCELLED => 'Dibatalkan',
            self::STATUS_DEFAULTED => 'Gagal Bayar',
            default => $this->status,
        };
    }
}
