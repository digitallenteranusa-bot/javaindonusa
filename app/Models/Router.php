<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Router extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'ip_address',
        'api_port',
        'username',
        'password',
        'identity',
        'version',
        'model',
        'serial_number',
        'is_active',
        'last_connected_at',
        'uptime',
        'cpu_load',
        'memory_usage',
        'notes',
        'radius_server_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'api_port' => 'integer',
            'is_active' => 'boolean',
            'last_connected_at' => 'datetime',
            'cpu_load' => 'integer',
            'memory_usage' => 'integer',
        ];
    }

    // ================================================================
    // RELATIONSHIPS
    // ================================================================

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function radiusServer(): BelongsTo
    {
        return $this->belongsTo(RadiusServer::class);
    }

    public function vpnConfigs(): HasMany
    {
        return $this->hasMany(VpnConfig::class);
    }

    public function vpnServerClient(): HasOne
    {
        return $this->hasOne(VpnServerClient::class);
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ================================================================
    // ACCESSORS & HELPERS
    // ================================================================

    /**
     * Get API connection URL
     */
    public function getApiUrlAttribute(): string
    {
        return "http://{$this->ip_address}:{$this->api_port}";
    }

    /**
     * Get customer count
     */
    public function getCustomerCountAttribute(): int
    {
        return $this->customers()->count();
    }

    /**
     * Get active customer count
     */
    public function getActiveCustomerCountAttribute(): int
    {
        return $this->customers()->active()->count();
    }

    /**
     * Check if router is online (connected within last 5 minutes)
     */
    public function isOnline(): bool
    {
        if (!$this->last_connected_at) {
            return false;
        }

        return $this->last_connected_at->gt(now()->subMinutes(5));
    }
}
