<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'area_id',
        'commission_rate',
        'is_active',
        'last_login_at',
        'profile_photo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'commission_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // ================================================================
    // CONSTANTS
    // ================================================================

    const ROLE_ADMIN = 'admin';
    const ROLE_COLLECTOR = 'collector';
    const ROLE_TECHNICIAN = 'technician';
    const ROLE_FINANCE = 'finance';

    // ================================================================
    // RELATIONSHIPS
    // ================================================================

    /**
     * Area yang ditugaskan (untuk collector)
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Pelanggan yang ditugaskan ke collector ini
     */
    public function assignedCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'collector_id');
    }

    /**
     * Pembayaran yang diterima oleh collector ini
     */
    public function collectedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'collector_id');
    }

    /**
     * Pengeluaran yang dibuat user ini
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Settlement/setoran yang dibuat user ini
     */
    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class, 'collector_id');
    }

    /**
     * Log penagihan yang dibuat collector ini
     */
    public function collectionLogs(): HasMany
    {
        return $this->hasMany(CollectionLog::class, 'collector_id');
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCollectors($query)
    {
        return $query->where('role', self::ROLE_COLLECTOR);
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', self::ROLE_ADMIN);
    }

    // ================================================================
    // ACCESSORS & HELPERS
    // ================================================================

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isCollector(): bool
    {
        return $this->role === self::ROLE_COLLECTOR;
    }

    public function isTechnician(): bool
    {
        return $this->role === self::ROLE_TECHNICIAN;
    }

    public function isFinance(): bool
    {
        return $this->role === self::ROLE_FINANCE;
    }
}
