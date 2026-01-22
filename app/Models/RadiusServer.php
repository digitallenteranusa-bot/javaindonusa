<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class RadiusServer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'ip_address',
        'auth_port',
        'acct_port',
        'secret',
        'status',
        'notes',
    ];

    protected $hidden = [
        'secret',
    ];

    protected function casts(): array
    {
        return [
            'auth_port' => 'integer',
            'acct_port' => 'integer',
        ];
    }

    // ================================================================
    // CONSTANTS
    // ================================================================

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_TESTING = 'testing';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Aktif',
            self::STATUS_INACTIVE => 'Nonaktif',
            self::STATUS_TESTING => 'Testing',
        ];
    }

    // ================================================================
    // RELATIONSHIPS
    // ================================================================

    public function routers(): HasMany
    {
        return $this->hasMany(Router::class);
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    // ================================================================
    // ACCESSORS & MUTATORS
    // ================================================================

    public function setSecretAttribute($value): void
    {
        $this->attributes['secret'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getDecryptedSecretAttribute(): ?string
    {
        try {
            return $this->secret ? Crypt::decryptString($this->attributes['secret']) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'green',
            self::STATUS_INACTIVE => 'gray',
            self::STATUS_TESTING => 'yellow',
            default => 'gray',
        };
    }

    // ================================================================
    // HELPERS
    // ================================================================

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getRouterCountAttribute(): int
    {
        return $this->routers()->count();
    }
}
