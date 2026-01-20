<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payment_number',
        'customer_id',
        'invoice_id',
        'collector_id',
        'received_by',
        'amount',
        'payment_method',
        'payment_channel',
        'bank_name',
        'bank_account',
        'reference_number',
        'transfer_proof',
        'status',
        'verified_by',
        'verified_at',
        'notes',
        'allocated_invoices',
        'allocated_to_invoice',
        'allocated_to_debt',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'allocated_to_invoice' => 'decimal:2',
            'allocated_to_debt' => 'decimal:2',
            'verified_at' => 'datetime',
            'allocated_invoices' => 'array',
        ];
    }

    // ================================================================
    // CONSTANTS
    // ================================================================

    const METHOD_CASH = 'cash';
    const METHOD_TRANSFER = 'transfer';
    const METHOD_QRIS = 'qris';
    const METHOD_EWALLET = 'ewallet';

    const CHANNEL_COLLECTOR = 'collector';
    const CHANNEL_OFFICE = 'office';
    const CHANNEL_BANK = 'bank';
    const CHANNEL_ONLINE = 'online';

    const STATUS_PENDING = 'pending';
    const STATUS_VERIFIED = 'verified';
    const STATUS_REJECTED = 'rejected';

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

    /**
     * Many-to-many relationship with invoices for payment allocation
     */
    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'invoice_payment')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function debtHistories(): HasMany
    {
        return $this->hasMany(DebtHistory::class);
    }

    public function collectionLog(): BelongsTo
    {
        return $this->belongsTo(CollectionLog::class);
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopeVerified($query)
    {
        return $query->where('status', self::STATUS_VERIFIED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCash($query)
    {
        return $query->where('payment_method', self::METHOD_CASH);
    }

    public function scopeTransfer($query)
    {
        return $query->where('payment_method', self::METHOD_TRANSFER);
    }

    public function scopeByCollector($query, int $collectorId)
    {
        return $query->where('collector_id', $collectorId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    // ================================================================
    // ACCESSORS & HELPERS
    // ================================================================

    /**
     * Format amount
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->amount, 0, ',', '.');
    }

    /**
     * Get payment method label
     */
    public function getMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            self::METHOD_CASH => 'Tunai',
            self::METHOD_TRANSFER => 'Transfer',
            self::METHOD_QRIS => 'QRIS',
            self::METHOD_EWALLET => 'E-Wallet',
            default => $this->payment_method,
        };
    }

    /**
     * Get payment channel label
     */
    public function getChannelLabelAttribute(): string
    {
        return match ($this->payment_channel) {
            self::CHANNEL_COLLECTOR => 'Penagih',
            self::CHANNEL_OFFICE => 'Kantor',
            self::CHANNEL_BANK => 'Bank',
            self::CHANNEL_ONLINE => 'Online',
            default => $this->payment_channel,
        };
    }

    /**
     * Generate payment number
     */
    public static function generatePaymentNumber(): string
    {
        $dateCode = now()->format('Ymd');

        $lastPayment = self::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($lastPayment) {
            preg_match('/(\d+)$/', $lastPayment->payment_number, $matches);
            $sequence = intval($matches[1] ?? 0) + 1;
        }

        return sprintf('PAY-%s-%05d', $dateCode, $sequence);
    }

    /**
     * Verify this payment
     */
    public function verify(User $admin): void
    {
        $this->update([
            'status' => self::STATUS_VERIFIED,
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);
    }

    /**
     * Reject this payment
     */
    public function reject(User $admin, string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'verified_by' => $admin->id,
            'verified_at' => now(),
            'notes' => $reason,
        ]);
    }
}
