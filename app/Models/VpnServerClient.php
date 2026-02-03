<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VpnServerClient extends Model
{
    use HasFactory, SoftDeletes;

    const PROTOCOL_OPENVPN = 'openvpn';
    const PROTOCOL_WIREGUARD = 'wireguard';

    protected $fillable = [
        'router_id',
        'name',
        'description',
        'protocol',
        'common_name',
        'public_key',
        'private_key',
        'preshared_key',
        'client_vpn_ip',
        'mikrotik_lan_subnet',
        'is_enabled',
        'connected_at',
        'disconnected_at',
        'remote_ip',
        'bytes_received',
        'bytes_sent',
        'generated_config',
        'generated_script',
        'last_generated_at',
    ];

    protected $hidden = [
        'generated_config',
        'private_key',
        'preshared_key',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'connected_at' => 'datetime',
            'disconnected_at' => 'datetime',
            'last_generated_at' => 'datetime',
            'bytes_received' => 'integer',
            'bytes_sent' => 'integer',
        ];
    }

    // ================================================================
    // RELATIONSHIPS
    // ================================================================

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    // ================================================================
    // ACCESSORS
    // ================================================================

    public function getIsConnectedAttribute(): bool
    {
        if (!$this->connected_at) {
            return false;
        }

        if ($this->disconnected_at && $this->disconnected_at->gt($this->connected_at)) {
            return false;
        }

        return true;
    }

    public function getStatusAttribute(): string
    {
        if (!$this->is_enabled) {
            return 'disabled';
        }

        return $this->is_connected ? 'connected' : 'disconnected';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'connected' => 'green',
            'disconnected' => 'gray',
            'disabled' => 'red',
            default => 'gray',
        };
    }

    public function getBytesReceivedFormattedAttribute(): string
    {
        return $this->formatBytes($this->bytes_received);
    }

    public function getBytesSentFormattedAttribute(): string
    {
        return $this->formatBytes($this->bytes_sent);
    }

    // ================================================================
    // HELPERS
    // ================================================================

    public function isOpenVpn(): bool
    {
        return $this->protocol === self::PROTOCOL_OPENVPN;
    }

    public function isWireGuard(): bool
    {
        return $this->protocol === self::PROTOCOL_WIREGUARD;
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = floor(log($bytes) / log(1024));
        $pow = min($pow, count($units) - 1);

        return round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeOpenVpn($query)
    {
        return $query->where('protocol', self::PROTOCOL_OPENVPN);
    }

    public function scopeWireGuard($query)
    {
        return $query->where('protocol', self::PROTOCOL_WIREGUARD);
    }
}
