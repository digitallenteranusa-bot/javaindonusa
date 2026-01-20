<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'speed_download',
        'speed_upload',
        'price',
        'setup_fee',
        'is_active',
        'mikrotik_profile',
        'burst_limit',
        'burst_threshold',
        'burst_time',
        'priority',
        'address_list',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'speed_download' => 'integer',
            'speed_upload' => 'integer',
            'price' => 'decimal:2',
            'setup_fee' => 'decimal:2',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    // ================================================================
    // RELATIONSHIPS
    // ================================================================

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

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('price');
    }

    // ================================================================
    // ACCESSORS
    // ================================================================

    /**
     * Format speed dalam Mbps
     */
    public function getSpeedLabelAttribute(): string
    {
        $download = $this->speed_download >= 1024
            ? ($this->speed_download / 1024) . ' Mbps'
            : $this->speed_download . ' Kbps';

        $upload = $this->speed_upload >= 1024
            ? ($this->speed_upload / 1024) . ' Mbps'
            : $this->speed_upload . ' Kbps';

        return "{$download} / {$upload}";
    }

    /**
     * Format harga
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'Rp ' . number_format($this->price, 0, ',', '.');
    }

    /**
     * Generate mikrotik rate limit string
     */
    public function getMikrotikRateLimitAttribute(): string
    {
        $download = $this->speed_download . 'k';
        $upload = $this->speed_upload . 'k';

        $rateLimit = "{$upload}/{$download}";

        if ($this->burst_limit && $this->burst_threshold && $this->burst_time) {
            $rateLimit .= " {$this->burst_limit}/{$this->burst_threshold}/{$this->burst_time}";
        }

        return $rateLimit;
    }
}
