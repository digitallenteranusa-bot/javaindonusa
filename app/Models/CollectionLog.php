<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'collector_id',
        'customer_id',
        'payment_id',
        'action_type',
        'amount',
        'payment_method',
        'transfer_proof',
        'visit_time',
        'latitude',
        'longitude',
        'notes',
        'device_info',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'visit_time' => 'datetime',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'device_info' => 'array',
        ];
    }

    // ================================================================
    // CONSTANTS
    // ================================================================

    const ACTION_VISIT = 'visit';
    const ACTION_PAYMENT_CASH = 'payment_cash';
    const ACTION_PAYMENT_TRANSFER = 'payment_transfer';
    const ACTION_NOT_HOME = 'not_home';
    const ACTION_REFUSED = 'refused';
    const ACTION_PROMISE_TO_PAY = 'promise_to_pay';
    const ACTION_RESCHEDULED = 'rescheduled';
    const ACTION_REMINDER_SENT = 'reminder_sent';

    // ================================================================
    // RELATIONSHIPS
    // ================================================================

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopeByCollector($query, int $collectorId)
    {
        return $query->where('collector_id', $collectorId);
    }

    public function scopeByCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByActionType($query, string $actionType)
    {
        return $query->where('action_type', $actionType);
    }

    public function scopePayments($query)
    {
        return $query->whereIn('action_type', [
            self::ACTION_PAYMENT_CASH,
            self::ACTION_PAYMENT_TRANSFER,
        ]);
    }

    public function scopeVisits($query)
    {
        return $query->whereIn('action_type', [
            self::ACTION_VISIT,
            self::ACTION_NOT_HOME,
            self::ACTION_REFUSED,
            self::ACTION_PROMISE_TO_PAY,
            self::ACTION_RESCHEDULED,
        ]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // ================================================================
    // ACCESSORS & HELPERS
    // ================================================================

    /**
     * Get action type label in Indonesian
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action_type) {
            self::ACTION_VISIT => 'Kunjungan',
            self::ACTION_PAYMENT_CASH => 'Pembayaran Tunai',
            self::ACTION_PAYMENT_TRANSFER => 'Pembayaran Transfer',
            self::ACTION_NOT_HOME => 'Tidak di Rumah',
            self::ACTION_REFUSED => 'Menolak',
            self::ACTION_PROMISE_TO_PAY => 'Janji Bayar',
            self::ACTION_RESCHEDULED => 'Dijadwalkan Ulang',
            self::ACTION_REMINDER_SENT => 'Reminder Terkirim',
            default => $this->action_type,
        };
    }

    /**
     * Format amount
     */
    public function getFormattedAmountAttribute(): ?string
    {
        if (!$this->amount) {
            return null;
        }

        return 'Rp ' . number_format($this->amount, 0, ',', '.');
    }

    /**
     * Check if log has GPS coordinates
     */
    public function hasLocation(): bool
    {
        return $this->latitude && $this->longitude;
    }

    /**
     * Get Google Maps URL for location
     */
    public function getGoogleMapsUrlAttribute(): ?string
    {
        if (!$this->hasLocation()) {
            return null;
        }

        return "https://www.google.com/maps?q={$this->latitude},{$this->longitude}";
    }

    /**
     * Get all action types as array
     */
    public static function getActionTypes(): array
    {
        return [
            self::ACTION_VISIT => 'Kunjungan',
            self::ACTION_PAYMENT_CASH => 'Pembayaran Tunai',
            self::ACTION_PAYMENT_TRANSFER => 'Pembayaran Transfer',
            self::ACTION_NOT_HOME => 'Tidak di Rumah',
            self::ACTION_REFUSED => 'Menolak',
            self::ACTION_PROMISE_TO_PAY => 'Janji Bayar',
            self::ACTION_RESCHEDULED => 'Dijadwalkan Ulang',
            self::ACTION_REMINDER_SENT => 'Reminder Terkirim',
        ];
    }
}
