<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Olt extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'ip_address',
        'type',
        'pon_ports',
        'username',
        'password',
        'telnet_port',
        'ssh_port',
        'snmp_community',
        'status',
        'notes',
        'firmware_version',
        'last_checked_at',
    ];

    protected $hidden = [
        'password',
        'snmp_community',
    ];

    protected function casts(): array
    {
        return [
            'telnet_port' => 'integer',
            'ssh_port' => 'integer',
            'last_checked_at' => 'datetime',
        ];
    }

    // ================================================================
    // CONSTANTS
    // ================================================================

    const TYPE_HIOSO = 'HIOSO';
    const TYPE_HSGQ = 'HSGQ';
    const TYPE_ZTE = 'ZTE';
    const TYPE_VSOL = 'VSOL';
    const TYPE_LAINNYA = 'Lainnya';

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_MAINTENANCE = 'maintenance';

    public static function getTypes(): array
    {
        return [
            self::TYPE_HIOSO => 'HIOSO',
            self::TYPE_HSGQ => 'HSGQ',
            self::TYPE_ZTE => 'ZTE',
            self::TYPE_VSOL => 'VSOL',
            self::TYPE_LAINNYA => 'Lainnya',
        ];
    }

    public static function getPonPortOptions(): array
    {
        return [
            '2' => '2 PON Ports',
            '4' => '4 PON Ports',
            '8' => '8 PON Ports',
            '16' => '16 PON Ports',
        ];
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Aktif',
            self::STATUS_INACTIVE => 'Nonaktif',
            self::STATUS_MAINTENANCE => 'Maintenance',
        ];
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ================================================================
    // ACCESSORS & HELPERS
    // ================================================================

    public function getStatusLabelAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'green',
            self::STATUS_INACTIVE => 'gray',
            self::STATUS_MAINTENANCE => 'yellow',
            default => 'gray',
        };
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getTelnetUrl(): string
    {
        return "telnet://{$this->ip_address}:{$this->telnet_port}";
    }

    public function getSshCommand(): string
    {
        return "ssh -p {$this->ssh_port} {$this->username}@{$this->ip_address}";
    }
}
