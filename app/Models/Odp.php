<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Odp extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'latitude',
        'longitude',
        'pole_type',
        'capacity',
        'used_ports',
        'area_id',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'capacity' => 'integer',
            'used_ports' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // ================================================================
    // CONSTANTS
    // ================================================================

    const POLE_TYPE_SENDIRI = 'sendiri';
    const POLE_TYPE_PLN = 'pln';
    const POLE_TYPE_TELKOM = 'telkom';
    const POLE_TYPE_BERSAMA = 'bersama';
    const POLE_TYPE_LAINNYA = 'lainnya';

    public static function getPoleTypes(): array
    {
        return [
            self::POLE_TYPE_SENDIRI => 'Tiang Sendiri',
            self::POLE_TYPE_PLN => 'Tiang PLN',
            self::POLE_TYPE_TELKOM => 'Tiang Telkom',
            self::POLE_TYPE_BERSAMA => 'Tiang Bersama',
            self::POLE_TYPE_LAINNYA => 'Lainnya',
        ];
    }

    // ================================================================
    // RELATIONSHIPS
    // ================================================================

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInArea($query, int $areaId)
    {
        return $query->where('area_id', $areaId);
    }

    public function scopeHasAvailablePorts($query)
    {
        return $query->whereRaw('used_ports < capacity');
    }

    // ================================================================
    // ACCESSORS & HELPERS
    // ================================================================

    public function getAvailablePortsAttribute(): int
    {
        return max(0, $this->capacity - $this->used_ports);
    }

    public function getUsagePercentageAttribute(): float
    {
        if ($this->capacity == 0) {
            return 0;
        }
        return round(($this->used_ports / $this->capacity) * 100, 1);
    }

    public function getPoleTypeLabelAttribute(): string
    {
        return self::getPoleTypes()[$this->pole_type] ?? $this->pole_type;
    }

    public function hasCoordinates(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }

    public function recalculateUsedPorts(): void
    {
        $this->update([
            'used_ports' => $this->customers()->count(),
        ]);
    }
}
