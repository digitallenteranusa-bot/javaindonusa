<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XenditTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'invoice_id',
        'payment_id',
        'xendit_id',
        'external_id',
        'method',
        'amount',
        'fee',
        'status',
        'invoice_url',
        'paid_at',
        'expired_at',
        'callback_data',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'paid_at' => 'datetime',
            'expired_at' => 'datetime',
            'callback_data' => 'array',
        ];
    }

    // ================================================================
    // CONSTANTS
    // ================================================================

    const STATUS_PENDING = 'PENDING';
    const STATUS_PAID = 'PAID';
    const STATUS_EXPIRED = 'EXPIRED';
    const STATUS_FAILED = 'FAILED';

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

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    public function scopeByCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    // ================================================================
    // ACCESSORS
    // ================================================================

    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->amount, 0, ',', '.');
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->expired_at
            && $this->expired_at->isPast();
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Menunggu Pembayaran',
            self::STATUS_PAID => 'Lunas',
            self::STATUS_EXPIRED => 'Kadaluarsa',
            self::STATUS_FAILED => 'Gagal',
            default => $this->status,
        };
    }
}
