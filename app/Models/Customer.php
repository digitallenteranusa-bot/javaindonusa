<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'name',
        'address',
        'rt_rw',
        'kelurahan',
        'kecamatan',
        'phone',
        'phone_alt',
        'email',
        'nik',
        'package_id',
        'area_id',
        'router_id',
        'odp_id',
        'collector_id',
        'pppoe_username',
        'pppoe_password',
        'ip_address',
        'mac_address',
        'onu_serial',
        'status',
        'total_debt',
        'join_date',
        'isolation_date',
        'isolation_reason',
        'termination_date',
        'termination_reason',
        'billing_type',
        'billing_date',
        'is_rapel',
        'rapel_amount',
        'rapel_months',
        'payment_behavior',
        'last_payment_date',
        'connection_type',
        'static_ip',
        'notes',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'isolation_date' => 'datetime',
            'termination_date' => 'date',
            'billing_date' => 'integer',
            'total_debt' => 'decimal:2',
            'rapel_amount' => 'decimal:2',
            'rapel_months' => 'integer',
            'is_rapel' => 'boolean',
            'last_payment_date' => 'datetime',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    // ================================================================
    // ACCESSORS & MUTATORS FOR ENCRYPTION
    // ================================================================

    /**
     * Encrypt PPPoE password when setting
     */
    public function setPppoePasswordAttribute($value): void
    {
        if (!empty($value)) {
            // Check if already encrypted (to avoid double encryption)
            try {
                Crypt::decryptString($value);
                // If decryption succeeds, it's already encrypted
                $this->attributes['pppoe_password'] = $value;
            } catch (\Exception $e) {
                // Not encrypted, encrypt it
                $this->attributes['pppoe_password'] = Crypt::encryptString($value);
            }
        } else {
            $this->attributes['pppoe_password'] = null;
        }
    }

    /**
     * Decrypt PPPoE password when getting
     */
    public function getPppoePasswordAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            // Return as-is if decryption fails (legacy unencrypted data)
            return $value;
        }
    }

    // ================================================================
    // CONSTANTS
    // ================================================================

    const STATUS_ACTIVE = 'active';
    const STATUS_ISOLATED = 'isolated';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_TERMINATED = 'terminated';

    const BILLING_TYPE_PREPAID = 'prepaid';
    const BILLING_TYPE_POSTPAID = 'postpaid';

    // ================================================================
    // RELATIONSHIPS
    // ================================================================

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function debtHistories(): HasMany
    {
        return $this->hasMany(DebtHistory::class);
    }

    public function collectionLogs(): HasMany
    {
        return $this->hasMany(CollectionLog::class);
    }

    public function token(): HasOne
    {
        return $this->hasOne(CustomerToken::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(CustomerDevice::class);
    }

    public function device(): HasOne
    {
        return $this->hasOne(CustomerDevice::class)->latestOfMany();
    }

    public function odp(): BelongsTo
    {
        return $this->belongsTo(Odp::class);
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeIsolated($query)
    {
        return $query->where('status', self::STATUS_ISOLATED);
    }

    public function scopeHasDebt($query)
    {
        return $query->where('total_debt', '>', 0);
    }

    public function scopeInArea($query, int $areaId)
    {
        return $query->where('area_id', $areaId);
    }

    public function scopeAssignedTo($query, int $collectorId)
    {
        return $query->where('collector_id', $collectorId);
    }

    // ================================================================
    // ACCESSORS & HELPERS
    // ================================================================

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isIsolated(): bool
    {
        return $this->status === self::STATUS_ISOLATED;
    }

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->rt_rw ? "RT/RW {$this->rt_rw}" : null,
            $this->kelurahan,
            $this->kecamatan,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Cek apakah pelanggan bisa diisolir
     */
    public function canBeIsolated(): bool
    {
        // Tidak bisa isolir jika sudah terisolir
        if ($this->isIsolated()) {
            return false;
        }

        // Tidak bisa isolir jika rapel dan masih dalam periode rapel
        if ($this->is_rapel && $this->rapel_months > 0) {
            return false;
        }

        return true;
    }

    /**
     * Hitung jumlah bulan menunggak
     */
    public function getOverdueMonthsAttribute(): int
    {
        return $this->invoices()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->count();
    }

    /**
     * Update total hutang dari invoice
     */
    public function recalculateTotalDebt(): void
    {
        $totalDebt = $this->invoices()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->sum('remaining_amount');

        $this->update(['total_debt' => $totalDebt]);
    }
}
