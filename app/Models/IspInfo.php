<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class IspInfo extends Model
{
    use HasFactory;

    protected $table = 'isp_info';

    protected $fillable = [
        'company_name',
        'tagline',
        'legal_name',
        'npwp',
        'nib',
        'phone_primary',
        'phone_secondary',
        'whatsapp_number',
        'email',
        'website',
        'address',
        'city',
        'province',
        'postal_code',
        'bank_accounts',
        'ewallet_accounts',
        'operational_hours',
        'logo',
        'favicon',
        'invoice_footer',
        'isolation_message',
        'payment_instructions',
    ];

    protected function casts(): array
    {
        return [
            'bank_accounts' => 'array',
            'ewallet_accounts' => 'array',
            'operational_hours' => 'array',
        ];
    }

    // ================================================================
    // CACHE HELPER
    // ================================================================

    /**
     * Get cached ISP info
     */
    public static function getCached(): ?self
    {
        return Cache::remember('isp_info', 3600, function () {
            return self::first();
        });
    }

    /**
     * Clear cache
     */
    public static function clearCache(): void
    {
        Cache::forget('isp_info');
    }

    /**
     * Save and clear cache
     */
    public function saveAndClearCache(): bool
    {
        $result = $this->save();
        self::clearCache();
        return $result;
    }

    // ================================================================
    // ACCESSORS
    // ================================================================

    /**
     * Get logo URL
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        return asset('storage/' . $this->logo);
    }

    /**
     * Get favicon URL
     */
    public function getFaviconUrlAttribute(): ?string
    {
        if (!$this->favicon) {
            return null;
        }

        return asset('storage/' . $this->favicon);
    }

    /**
     * Get formatted phone number for WhatsApp
     */
    public function getWhatsappUrlAttribute(): string
    {
        $phone = preg_replace('/[^0-9]/', '', $this->whatsapp_number);

        // Convert 08 to 628
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        return "https://wa.me/{$phone}";
    }

    /**
     * Get primary bank account
     */
    public function getPrimaryBankAccountAttribute(): ?array
    {
        if (!$this->bank_accounts || empty($this->bank_accounts)) {
            return null;
        }

        return $this->bank_accounts[0];
    }

    /**
     * Get formatted bank accounts for display
     */
    public function getFormattedBankAccountsAttribute(): string
    {
        if (!$this->bank_accounts || empty($this->bank_accounts)) {
            return '-';
        }

        return collect($this->bank_accounts)->map(function ($acc) {
            return "{$acc['bank']} - {$acc['account']} a.n {$acc['name']}";
        })->join("\n");
    }

    /**
     * Get full address
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->province,
            $this->postal_code,
        ]);

        return implode(', ', $parts);
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * Get operational status
     */
    public function isOperationalNow(): bool
    {
        if (!$this->operational_hours) {
            return true;
        }

        $now = now();
        $dayOfWeek = strtolower($now->format('l'));

        if (!isset($this->operational_hours[$dayOfWeek])) {
            return false;
        }

        $hours = $this->operational_hours[$dayOfWeek];

        if (isset($hours['closed']) && $hours['closed']) {
            return false;
        }

        $open = $hours['open'] ?? '00:00';
        $close = $hours['close'] ?? '23:59';

        $currentTime = $now->format('H:i');

        return $currentTime >= $open && $currentTime <= $close;
    }
}
