<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'device_id',
        'serial_number',
        'manufacturer',
        'model',
        'firmware_version',
        'hardware_version',
        'wan_ip',
        'wan_mac',
        'pon_serial',
        'rx_power',
        'tx_power',
        'wifi_ssid',
        'wifi_enabled',
        'is_online',
        'last_inform',
        'last_boot',
        'uptime',
        'tags',
        'raw_data',
        'notes',
    ];

    protected $casts = [
        'wifi_enabled' => 'boolean',
        'is_online' => 'boolean',
        'last_inform' => 'datetime',
        'last_boot' => 'datetime',
        'uptime' => 'integer',
        'rx_power' => 'decimal:2',
        'tx_power' => 'decimal:2',
        'tags' => 'array',
        'raw_data' => 'array',
    ];

    // ================================================================
    // RELATIONSHIPS
    // ================================================================

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopeOnline($query)
    {
        return $query->where('is_online', true);
    }

    public function scopeOffline($query)
    {
        return $query->where('is_online', false);
    }

    public function scopeByManufacturer($query, string $manufacturer)
    {
        return $query->where('manufacturer', $manufacturer);
    }

    public function scopeWithLowSignal($query)
    {
        $minPower = config('genieacs.thresholds.rx_power_min', -28);
        return $query->where('rx_power', '<', $minPower);
    }

    // ================================================================
    // ACCESSORS
    // ================================================================

    /**
     * Get formatted uptime
     */
    public function getUptimeFormattedAttribute(): string
    {
        if (!$this->uptime) {
            return '-';
        }

        $days = floor($this->uptime / 86400);
        $hours = floor(($this->uptime % 86400) / 3600);
        $minutes = floor(($this->uptime % 3600) / 60);

        if ($days > 0) {
            return "{$days}d {$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }

    /**
     * Get signal status based on RX power
     */
    public function getSignalStatusAttribute(): string
    {
        if ($this->rx_power === null) {
            return 'unknown';
        }

        $min = config('genieacs.thresholds.rx_power_min', -28);
        $max = config('genieacs.thresholds.rx_power_max', -8);

        if ($this->rx_power < $min) {
            return 'weak';
        } elseif ($this->rx_power > $max) {
            return 'too_strong';
        }

        return 'good';
    }

    /**
     * Get signal badge class
     */
    public function getSignalBadgeAttribute(): array
    {
        return match ($this->signal_status) {
            'good' => ['class' => 'bg-green-100 text-green-600', 'text' => 'Baik'],
            'weak' => ['class' => 'bg-red-100 text-red-600', 'text' => 'Lemah'],
            'too_strong' => ['class' => 'bg-yellow-100 text-yellow-600', 'text' => 'Terlalu Kuat'],
            default => ['class' => 'bg-gray-100 text-gray-600', 'text' => 'N/A'],
        };
    }

    /**
     * Get online status badge
     */
    public function getStatusBadgeAttribute(): array
    {
        return $this->is_online
            ? ['class' => 'bg-green-100 text-green-600', 'text' => 'Online']
            : ['class' => 'bg-red-100 text-red-600', 'text' => 'Offline'];
    }

    /**
     * Get device display name
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->manufacturer && $this->model) {
            return "{$this->manufacturer} {$this->model}";
        }

        return $this->serial_number ?? $this->device_id;
    }

    // ================================================================
    // METHODS
    // ================================================================

    /**
     * Check if device is considered offline based on last inform time
     */
    public function checkOnlineStatus(): bool
    {
        if (!$this->last_inform) {
            return false;
        }

        $threshold = config('genieacs.thresholds.offline_minutes', 30);
        return $this->last_inform->diffInMinutes(now()) <= $threshold;
    }

    /**
     * Update online status based on last inform
     */
    public function updateOnlineStatus(): void
    {
        $this->update(['is_online' => $this->checkOnlineStatus()]);
    }
}
