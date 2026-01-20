<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BillingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'loggable_type',
        'loggable_id',
        'user_id',
        'action',
        'description',
        'old_data',
        'new_data',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_data' => 'array',
            'new_data' => 'array',
        ];
    }

    // ================================================================
    // CONSTANTS - ACTIONS
    // ================================================================

    // Invoice actions
    const ACTION_INVOICE_CREATED = 'invoice_created';
    const ACTION_INVOICE_UPDATED = 'invoice_updated';
    const ACTION_INVOICE_CANCELLED = 'invoice_cancelled';
    const ACTION_INVOICE_PAID = 'invoice_paid';

    // Payment actions
    const ACTION_PAYMENT_RECEIVED = 'payment_received';
    const ACTION_PAYMENT_VERIFIED = 'payment_verified';
    const ACTION_PAYMENT_REJECTED = 'payment_rejected';

    // Customer actions
    const ACTION_CUSTOMER_CREATED = 'customer_created';
    const ACTION_CUSTOMER_UPDATED = 'customer_updated';
    const ACTION_CUSTOMER_ISOLATED = 'customer_isolated';
    const ACTION_CUSTOMER_REOPENED = 'customer_reopened';
    const ACTION_CUSTOMER_TERMINATED = 'customer_terminated';

    // System actions
    const ACTION_BILLING_RUN = 'billing_run';
    const ACTION_ISOLATION_RUN = 'isolation_run';
    const ACTION_REMINDER_SENT = 'reminder_sent';

    // ================================================================
    // RELATIONSHIPS
    // ================================================================

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Alias for user relationship (performed by)
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopeForModel($query, string $type, int $id)
    {
        return $query->where('loggable_type', $type)
            ->where('loggable_id', $id);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ================================================================
    // STATIC METHODS
    // ================================================================

    /**
     * Log an action
     */
    public static function log(
        string $action,
        ?Model $model = null,
        ?string $description = null,
        ?array $oldData = null,
        ?array $newData = null,
        ?int $userId = null
    ): self {
        return self::create([
            'loggable_type' => $model ? get_class($model) : null,
            'loggable_id' => $model?->id,
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'description' => $description,
            'old_data' => $oldData,
            'new_data' => $newData,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Log invoice action
     */
    public static function logInvoice(Invoice $invoice, string $action, ?string $description = null): self
    {
        return self::log($action, $invoice, $description, null, $invoice->toArray());
    }

    /**
     * Log payment action
     */
    public static function logPayment(Payment $payment, string $action, ?string $description = null): self
    {
        return self::log($action, $payment, $description, null, $payment->toArray());
    }

    /**
     * Log customer action
     */
    public static function logCustomer(Customer $customer, string $action, ?string $description = null, ?array $oldData = null): self
    {
        return self::log($action, $customer, $description, $oldData, $customer->toArray());
    }

    /**
     * Log system action
     */
    public static function logSystem(string $action, ?string $description = null, ?array $data = null): self
    {
        return self::log($action, null, $description, null, $data);
    }

    // ================================================================
    // ACCESSORS
    // ================================================================

    /**
     * Get action label in Indonesian
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_INVOICE_CREATED => 'Invoice Dibuat',
            self::ACTION_INVOICE_UPDATED => 'Invoice Diupdate',
            self::ACTION_INVOICE_CANCELLED => 'Invoice Dibatalkan',
            self::ACTION_INVOICE_PAID => 'Invoice Lunas',
            self::ACTION_PAYMENT_RECEIVED => 'Pembayaran Diterima',
            self::ACTION_PAYMENT_VERIFIED => 'Pembayaran Diverifikasi',
            self::ACTION_PAYMENT_REJECTED => 'Pembayaran Ditolak',
            self::ACTION_CUSTOMER_CREATED => 'Pelanggan Dibuat',
            self::ACTION_CUSTOMER_UPDATED => 'Pelanggan Diupdate',
            self::ACTION_CUSTOMER_ISOLATED => 'Pelanggan Diisolir',
            self::ACTION_CUSTOMER_REOPENED => 'Akses Dibuka',
            self::ACTION_CUSTOMER_TERMINATED => 'Pelanggan Diputus',
            self::ACTION_BILLING_RUN => 'Generate Tagihan',
            self::ACTION_ISOLATION_RUN => 'Proses Isolir',
            self::ACTION_REMINDER_SENT => 'Reminder Terkirim',
            default => $this->action,
        };
    }
}
