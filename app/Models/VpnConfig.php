<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VpnConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'router_id',
        'protocol',
        'enabled',
        'settings',
        'generated_script',
        'last_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'settings' => 'array',
            'last_generated_at' => 'datetime',
        ];
    }

    // ================================================================
    // CONSTANTS
    // ================================================================

    const PROTOCOL_L2TP = 'l2tp';
    const PROTOCOL_PPTP = 'pptp';
    const PROTOCOL_SSTP = 'sstp';
    const PROTOCOL_WIREGUARD = 'wireguard';

    public static function getProtocols(): array
    {
        return [
            self::PROTOCOL_L2TP => 'L2TP/IPSec',
            self::PROTOCOL_PPTP => 'PPTP',
            self::PROTOCOL_SSTP => 'SSTP',
            self::PROTOCOL_WIREGUARD => 'WireGuard',
        ];
    }

    public static function getProtocolDescriptions(): array
    {
        return [
            self::PROTOCOL_L2TP => 'L2TP/IPSec - Secure and widely compatible',
            self::PROTOCOL_PPTP => 'PPTP - Fast but less secure, legacy support',
            self::PROTOCOL_SSTP => 'SSTP - SSL-based, good for firewall bypass',
            self::PROTOCOL_WIREGUARD => 'WireGuard - Modern, fast (RouterOS v7+)',
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
    // ACCESSORS & HELPERS
    // ================================================================

    public function getProtocolLabelAttribute(): string
    {
        return self::getProtocols()[$this->protocol] ?? $this->protocol;
    }

    public function getDefaultSettings(): array
    {
        return match ($this->protocol) {
            self::PROTOCOL_L2TP => [
                'local_address' => '10.255.255.1',
                'remote_address_pool' => 'vpn-pool',
                'pool_start' => '10.255.255.10',
                'pool_end' => '10.255.255.250',
                'dns_server' => '8.8.8.8',
                'ipsec_secret' => '',
                'default_profile' => 'default-encryption',
            ],
            self::PROTOCOL_PPTP => [
                'local_address' => '10.254.254.1',
                'remote_address_pool' => 'pptp-pool',
                'pool_start' => '10.254.254.10',
                'pool_end' => '10.254.254.250',
                'dns_server' => '8.8.8.8',
            ],
            self::PROTOCOL_SSTP => [
                'local_address' => '10.253.253.1',
                'remote_address_pool' => 'sstp-pool',
                'pool_start' => '10.253.253.10',
                'pool_end' => '10.253.253.250',
                'dns_server' => '8.8.8.8',
                'certificate' => '',
            ],
            self::PROTOCOL_WIREGUARD => [
                'listen_port' => 13231,
                'mtu' => 1420,
                'interface_address' => '10.252.252.1/24',
            ],
            default => [],
        };
    }

    public function mergeWithDefaults(): array
    {
        return array_merge($this->getDefaultSettings(), $this->settings ?? []);
    }

    public function isRouterV7(): bool
    {
        $router = $this->router;
        if (!$router || !$router->version) {
            return false;
        }

        // Extract major version number
        preg_match('/^(\d+)/', $router->version, $matches);
        return isset($matches[1]) && (int) $matches[1] >= 7;
    }
}
