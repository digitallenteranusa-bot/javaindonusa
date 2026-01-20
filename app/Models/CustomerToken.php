<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CustomerToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'token',
        'otp_code',
        'otp_expires_at',
        'expires_at',
        'last_used_at',
        'device_info',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'otp_expires_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'device_info' => 'array',
        ];
    }

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

    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * Check if token is valid
     */
    public function isValid(): bool
    {
        return $this->expires_at && $this->expires_at->gt(now());
    }

    /**
     * Check if OTP is valid
     */
    public function isOtpValid(): bool
    {
        return $this->otp_code
            && $this->otp_expires_at
            && $this->otp_expires_at->gt(now());
    }

    /**
     * Verify OTP code
     */
    public function verifyOtp(string $code): bool
    {
        if (!$this->isOtpValid()) {
            return false;
        }

        return $this->otp_code === $code;
    }

    /**
     * Clear OTP after successful verification
     */
    public function clearOtp(): void
    {
        $this->update([
            'otp_code' => null,
            'otp_expires_at' => null,
            'last_used_at' => now(),
        ]);
    }

    /**
     * Refresh token
     */
    public function refresh(int $hours = 24): void
    {
        $this->update([
            'token' => Str::random(64),
            'expires_at' => now()->addHours($hours),
            'last_used_at' => now(),
        ]);
    }

    /**
     * Generate new OTP
     */
    public function generateOtp(int $minutes = 5): string
    {
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->update([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes($minutes),
        ]);

        return $otp;
    }

    /**
     * Create new token for customer
     */
    public static function createForCustomer(Customer $customer, int $hours = 24): self
    {
        return self::updateOrCreate(
            ['customer_id' => $customer->id],
            [
                'token' => Str::random(64),
                'expires_at' => now()->addHours($hours),
            ]
        );
    }

    /**
     * Find valid token by token string
     */
    public static function findValidToken(string $token): ?self
    {
        return self::where('token', $token)
            ->valid()
            ->first();
    }

    /**
     * Cleanup expired tokens
     */
    public static function cleanupExpired(): int
    {
        return self::expired()->delete();
    }
}
