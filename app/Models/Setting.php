<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'description',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    // ================================================================
    // CONSTANTS - GROUPS
    // ================================================================

    const GROUP_BILLING = 'billing';
    const GROUP_NOTIFICATION = 'notification';
    const GROUP_EXPENSE = 'expense';
    const GROUP_ISOLATION = 'isolation';
    const GROUP_SYSTEM = 'system';
    const GROUP_VPN_SERVER = 'vpn_server';
    const GROUP_TRIPAY = 'tripay';

    // ================================================================
    // CONSTANTS - KEYS
    // ================================================================

    // Billing
    const KEY_BILLING_DATE = 'billing_date';
    const KEY_DUE_DAYS = 'due_days';
    const KEY_GRACE_PERIOD = 'grace_period';
    const KEY_LATE_FEE = 'late_fee';
    const KEY_LATE_FEE_TYPE = 'late_fee_type';

    // Isolation
    const KEY_ISOLATION_THRESHOLD = 'isolation_threshold';
    const KEY_AUTO_ISOLATE = 'auto_isolate';
    const KEY_ISOLATION_TIME = 'isolation_time';

    // Expense
    const KEY_DAILY_LIMIT = 'daily_limit';
    const KEY_MONTHLY_LIMIT = 'monthly_limit';
    const KEY_REQUIRE_RECEIPT = 'require_receipt';

    // Notification
    const KEY_REMINDER_DAYS = 'reminder_days';
    const KEY_WHATSAPP_ENABLED = 'whatsapp_enabled';
    const KEY_SMS_ENABLED = 'sms_enabled';
    const KEY_EMAIL_ENABLED = 'email_enabled';

    // ================================================================
    // STATIC METHODS
    // ================================================================

    /**
     * Get setting value with caching
     */
    public static function getValue(string $group, string $key, $default = null)
    {
        $cacheKey = "setting.{$group}.{$key}";

        return Cache::remember($cacheKey, 3600, function () use ($group, $key, $default) {
            $setting = self::where('group', $group)
                ->where('key', $key)
                ->first();

            if (!$setting) {
                return $default;
            }

            return self::castValue($setting->value, $setting->type);
        });
    }

    /**
     * Set setting value
     */
    public static function setValue(string $group, string $key, $value, ?string $type = null): self
    {
        $setting = self::updateOrCreate(
            ['group' => $group, 'key' => $key],
            [
                'value' => is_array($value) ? json_encode($value) : (string) $value,
                'type' => $type ?? self::detectType($value),
            ]
        );

        // Clear cache
        Cache::forget("setting.{$group}.{$key}");
        Cache::forget("settings.{$group}");

        return $setting;
    }

    /**
     * Get all settings for a group
     */
    public static function getGroup(string $group): array
    {
        $cacheKey = "settings.{$group}";

        return Cache::remember($cacheKey, 3600, function () use ($group) {
            return self::where('group', $group)
                ->get()
                ->mapWithKeys(function ($setting) {
                    return [$setting->key => self::castValue($setting->value, $setting->type)];
                })
                ->toArray();
        });
    }

    /**
     * Get all public settings
     */
    public static function getPublic(): array
    {
        return Cache::remember('settings.public', 3600, function () {
            return self::where('is_public', true)
                ->get()
                ->mapWithKeys(function ($setting) {
                    $key = "{$setting->group}.{$setting->key}";
                    return [$key => self::castValue($setting->value, $setting->type)];
                })
                ->toArray();
        });
    }

    /**
     * Clear all settings cache
     */
    public static function clearAllCache(): void
    {
        $groups = [
            self::GROUP_BILLING,
            self::GROUP_NOTIFICATION,
            self::GROUP_EXPENSE,
            self::GROUP_ISOLATION,
            self::GROUP_SYSTEM,
            self::GROUP_VPN_SERVER,
            self::GROUP_TRIPAY,
        ];

        foreach ($groups as $group) {
            Cache::forget("settings.{$group}");
        }

        Cache::forget('settings.public');

        // Clear individual setting caches
        self::all()->each(function ($setting) {
            Cache::forget("setting.{$setting->group}.{$setting->key}");
        });
    }

    // ================================================================
    // HELPER METHODS
    // ================================================================

    /**
     * Cast value based on type
     */
    protected static function castValue($value, ?string $type)
    {
        return match ($type) {
            'integer', 'int' => (int) $value,
            'float', 'double' => (float) $value,
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'array', 'json' => is_array($value) ? $value : json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Detect value type
     */
    protected static function detectType($value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_int($value)) {
            return 'integer';
        }

        if (is_float($value)) {
            return 'float';
        }

        if (is_array($value)) {
            return 'array';
        }

        return 'string';
    }

    // ================================================================
    // QUICK ACCESS METHODS
    // ================================================================

    /**
     * Get billing settings
     */
    public static function billing(): array
    {
        return self::getGroup(self::GROUP_BILLING);
    }

    /**
     * Get notification settings
     */
    public static function notification(): array
    {
        return self::getGroup(self::GROUP_NOTIFICATION);
    }

    /**
     * Get expense settings
     */
    public static function expense(): array
    {
        return self::getGroup(self::GROUP_EXPENSE);
    }

    /**
     * Get isolation settings
     */
    public static function isolation(): array
    {
        return self::getGroup(self::GROUP_ISOLATION);
    }

    /**
     * Get VPN server settings
     */
    public static function vpnServer(): array
    {
        return self::getGroup(self::GROUP_VPN_SERVER);
    }
}
