<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripayTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'invoice_id',
        'payment_id',
        'reference',
        'merchant_ref',
        'method',
        'amount',
        'fee_merchant',
        'fee_customer',
        'total_amount',
        'status',
        'checkout_url',
        'qr_url',
        'pay_url',
        'paid_at',
        'expired_at',
        'callback_data',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee_merchant' => 'decimal:2',
            'fee_customer' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'expired_at' => 'datetime',
            'callback_data' => 'array',
        ];
    }

    // ================================================================
    // CONSTANTS
    // ================================================================

    const STATUS_UNPAID = 'UNPAID';
    const STATUS_PAID = 'PAID';
    const STATUS_EXPIRED = 'EXPIRED';
    const STATUS_FAILED = 'FAILED';
    const STATUS_REFUND = 'REFUND';

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

    public function scopeUnpaid($query)
    {
        return $query->where('status', self::STATUS_UNPAID);
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

    public function getFormattedTotalAttribute(): string
    {
        return 'Rp ' . number_format($this->total_amount, 0, ',', '.');
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->status === self::STATUS_UNPAID
            && $this->expired_at
            && $this->expired_at->isPast();
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_UNPAID => 'Belum Bayar',
            self::STATUS_PAID => 'Lunas',
            self::STATUS_EXPIRED => 'Kadaluarsa',
            self::STATUS_FAILED => 'Gagal',
            self::STATUS_REFUND => 'Refund',
            default => $this->status,
        };
    }
}
