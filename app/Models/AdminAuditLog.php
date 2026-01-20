<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AdminAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'module',
        'action',
        'description',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
        ];
    }

    // ================================================================
    // CONSTANTS - MODULES
    // ================================================================

    const MODULE_USER = 'user';
    const MODULE_CUSTOMER = 'customer';
    const MODULE_INVOICE = 'invoice';
    const MODULE_PAYMENT = 'payment';
    const MODULE_PACKAGE = 'package';
    const MODULE_AREA = 'area';
    const MODULE_ROUTER = 'router';
    const MODULE_EXPENSE = 'expense';
    const MODULE_SETTLEMENT = 'settlement';
    const MODULE_SETTING = 'setting';
    const MODULE_AUTH = 'auth';
    const MODULE_REPORT = 'report';
    const MODULE_SYSTEM = 'system';

    // ================================================================
    // CONSTANTS - ACTIONS
    // ================================================================

    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';
    const ACTION_RESTORE = 'restore';
    const ACTION_VIEW = 'view';
    const ACTION_EXPORT = 'export';
    const ACTION_IMPORT = 'import';
    const ACTION_LOGIN = 'login';
    const ACTION_LOGOUT = 'logout';
    const ACTION_LOGIN_FAILED = 'login_failed';
    const ACTION_PASSWORD_RESET = 'password_reset';
    const ACTION_TOGGLE_STATUS = 'toggle_status';
    const ACTION_APPROVE = 'approve';
    const ACTION_REJECT = 'reject';
    const ACTION_CANCEL = 'cancel';
    const ACTION_GENERATE = 'generate';
    const ACTION_ISOLATE = 'isolate';
    const ACTION_REOPEN = 'reopen';
    const ACTION_ADJUST = 'adjust';
    const ACTION_WRITE_OFF = 'write_off';

    // ================================================================
    // RELATIONSHIPS
    // ================================================================

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopeByAdmin($query, int $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    public function scopeByModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeForModel($query, string $type, int $id)
    {
        return $query->where('auditable_type', $type)
            ->where('auditable_id', $id);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('description', 'like', "%{$search}%")
                ->orWhereHas('admin', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
        });
    }

    // ================================================================
    // STATIC METHODS
    // ================================================================

    /**
     * Log an admin action
     */
    public static function log(
        string $module,
        string $action,
        string $description,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?int $adminId = null
    ): self {
        return self::create([
            'admin_id' => $adminId ?? auth()->id(),
            'module' => $module,
            'action' => $action,
            'description' => $description,
            'auditable_type' => $model ? get_class($model) : null,
            'auditable_id' => $model?->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Log create action
     */
    public static function logCreate(string $module, Model $model, string $description, ?array $metadata = null): self
    {
        return self::log(
            $module,
            self::ACTION_CREATE,
            $description,
            $model,
            null,
            $model->toArray(),
            $metadata
        );
    }

    /**
     * Log update action
     */
    public static function logUpdate(string $module, Model $model, string $description, array $oldValues, ?array $metadata = null): self
    {
        return self::log(
            $module,
            self::ACTION_UPDATE,
            $description,
            $model,
            $oldValues,
            $model->toArray(),
            $metadata
        );
    }

    /**
     * Log delete action
     */
    public static function logDelete(string $module, Model $model, string $description, ?array $metadata = null): self
    {
        return self::log(
            $module,
            self::ACTION_DELETE,
            $description,
            $model,
            $model->toArray(),
            null,
            $metadata
        );
    }

    /**
     * Log export action
     */
    public static function logExport(string $module, string $description, ?array $filters = null): self
    {
        return self::log(
            $module,
            self::ACTION_EXPORT,
            $description,
            null,
            null,
            null,
            ['filters' => $filters]
        );
    }

    /**
     * Log authentication action
     */
    public static function logAuth(string $action, ?User $user = null, ?string $description = null): self
    {
        $desc = $description ?? match ($action) {
            self::ACTION_LOGIN => 'Login ke sistem',
            self::ACTION_LOGOUT => 'Logout dari sistem',
            self::ACTION_LOGIN_FAILED => 'Gagal login ke sistem',
            default => $action,
        };

        return self::log(
            self::MODULE_AUTH,
            $action,
            $desc,
            $user,
            null,
            null,
            null,
            $user?->id
        );
    }

    // ================================================================
    // ACCESSORS
    // ================================================================

    /**
     * Get module label in Indonesian
     */
    public function getModuleLabelAttribute(): string
    {
        return match ($this->module) {
            self::MODULE_USER => 'Pengguna',
            self::MODULE_CUSTOMER => 'Pelanggan',
            self::MODULE_INVOICE => 'Invoice',
            self::MODULE_PAYMENT => 'Pembayaran',
            self::MODULE_PACKAGE => 'Paket',
            self::MODULE_AREA => 'Area',
            self::MODULE_ROUTER => 'Router',
            self::MODULE_EXPENSE => 'Pengeluaran',
            self::MODULE_SETTLEMENT => 'Setor',
            self::MODULE_SETTING => 'Pengaturan',
            self::MODULE_AUTH => 'Autentikasi',
            self::MODULE_REPORT => 'Laporan',
            self::MODULE_SYSTEM => 'Sistem',
            default => ucfirst($this->module),
        };
    }

    /**
     * Get action label in Indonesian
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_CREATE => 'Tambah',
            self::ACTION_UPDATE => 'Ubah',
            self::ACTION_DELETE => 'Hapus',
            self::ACTION_RESTORE => 'Pulihkan',
            self::ACTION_VIEW => 'Lihat',
            self::ACTION_EXPORT => 'Export',
            self::ACTION_IMPORT => 'Import',
            self::ACTION_LOGIN => 'Login',
            self::ACTION_LOGOUT => 'Logout',
            self::ACTION_LOGIN_FAILED => 'Login Gagal',
            self::ACTION_PASSWORD_RESET => 'Reset Password',
            self::ACTION_TOGGLE_STATUS => 'Ubah Status',
            self::ACTION_APPROVE => 'Setujui',
            self::ACTION_REJECT => 'Tolak',
            self::ACTION_CANCEL => 'Batalkan',
            self::ACTION_GENERATE => 'Generate',
            self::ACTION_ISOLATE => 'Isolir',
            self::ACTION_REOPEN => 'Buka Akses',
            self::ACTION_ADJUST => 'Penyesuaian',
            self::ACTION_WRITE_OFF => 'Write Off',
            default => ucfirst($this->action),
        };
    }

    /**
     * Get action badge color
     */
    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_CREATE => 'green',
            self::ACTION_UPDATE => 'blue',
            self::ACTION_DELETE => 'red',
            self::ACTION_RESTORE => 'purple',
            self::ACTION_EXPORT, self::ACTION_IMPORT => 'yellow',
            self::ACTION_LOGIN => 'green',
            self::ACTION_LOGOUT => 'gray',
            self::ACTION_LOGIN_FAILED => 'red',
            self::ACTION_APPROVE => 'green',
            self::ACTION_REJECT, self::ACTION_CANCEL => 'red',
            self::ACTION_ISOLATE => 'orange',
            self::ACTION_REOPEN => 'green',
            default => 'gray',
        };
    }

    /**
     * Get available modules for filter
     */
    public static function getModules(): array
    {
        return [
            self::MODULE_USER => 'Pengguna',
            self::MODULE_CUSTOMER => 'Pelanggan',
            self::MODULE_INVOICE => 'Invoice',
            self::MODULE_PAYMENT => 'Pembayaran',
            self::MODULE_PACKAGE => 'Paket',
            self::MODULE_AREA => 'Area',
            self::MODULE_ROUTER => 'Router',
            self::MODULE_EXPENSE => 'Pengeluaran',
            self::MODULE_SETTLEMENT => 'Setor',
            self::MODULE_SETTING => 'Pengaturan',
            self::MODULE_AUTH => 'Autentikasi',
            self::MODULE_REPORT => 'Laporan',
            self::MODULE_SYSTEM => 'Sistem',
        ];
    }

    /**
     * Get available actions for filter
     */
    public static function getActions(): array
    {
        return [
            self::ACTION_CREATE => 'Tambah',
            self::ACTION_UPDATE => 'Ubah',
            self::ACTION_DELETE => 'Hapus',
            self::ACTION_RESTORE => 'Pulihkan',
            self::ACTION_VIEW => 'Lihat',
            self::ACTION_EXPORT => 'Export',
            self::ACTION_IMPORT => 'Import',
            self::ACTION_LOGIN => 'Login',
            self::ACTION_LOGOUT => 'Logout',
            self::ACTION_LOGIN_FAILED => 'Login Gagal',
            self::ACTION_PASSWORD_RESET => 'Reset Password',
            self::ACTION_TOGGLE_STATUS => 'Ubah Status',
            self::ACTION_APPROVE => 'Setujui',
            self::ACTION_REJECT => 'Tolak',
            self::ACTION_CANCEL => 'Batalkan',
            self::ACTION_GENERATE => 'Generate',
            self::ACTION_ISOLATE => 'Isolir',
            self::ACTION_REOPEN => 'Buka Akses',
            self::ACTION_ADJUST => 'Penyesuaian',
            self::ACTION_WRITE_OFF => 'Write Off',
        ];
    }
}
