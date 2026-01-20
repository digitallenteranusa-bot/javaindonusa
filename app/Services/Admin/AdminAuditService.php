<?php

namespace App\Services\Admin;

use App\Models\AdminAuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AdminAuditService
{
    // ================================================================
    // LOGGING METHODS
    // ================================================================

    /**
     * Log create action
     */
    public function logCreate(string $module, Model $model, ?string $customDescription = null): AdminAuditLog
    {
        $description = $customDescription ?? $this->generateCreateDescription($module, $model);

        return AdminAuditLog::logCreate($module, $model, $description);
    }

    /**
     * Log update action
     */
    public function logUpdate(string $module, Model $model, array $oldValues, ?string $customDescription = null): AdminAuditLog
    {
        $description = $customDescription ?? $this->generateUpdateDescription($module, $model, $oldValues);

        return AdminAuditLog::logUpdate($module, $model, $description, $oldValues);
    }

    /**
     * Log delete action
     */
    public function logDelete(string $module, Model $model, ?string $customDescription = null): AdminAuditLog
    {
        $description = $customDescription ?? $this->generateDeleteDescription($module, $model);

        return AdminAuditLog::logDelete($module, $model, $description);
    }

    /**
     * Log custom action
     */
    public function logAction(
        string $module,
        string $action,
        string $description,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null
    ): AdminAuditLog {
        return AdminAuditLog::log(
            $module,
            $action,
            $description,
            $model,
            $oldValues,
            $newValues,
            $metadata
        );
    }

    /**
     * Log export action
     */
    public function logExport(string $module, ?array $filters = null, ?int $recordCount = null): AdminAuditLog
    {
        $description = "Export data {$this->getModuleLabel($module)}";
        if ($recordCount) {
            $description .= " ({$recordCount} records)";
        }

        return AdminAuditLog::logExport($module, $description, $filters);
    }

    /**
     * Log login
     */
    public function logLogin(User $user): AdminAuditLog
    {
        return AdminAuditLog::log(
            AdminAuditLog::MODULE_AUTH,
            AdminAuditLog::ACTION_LOGIN,
            "Login ke sistem",
            $user,
            null,
            ['last_login_at' => now()->toDateTimeString()],
            null,
            $user->id
        );
    }

    /**
     * Log logout
     */
    public function logLogout(?User $user = null): AdminAuditLog
    {
        $user = $user ?? auth()->user();

        return AdminAuditLog::log(
            AdminAuditLog::MODULE_AUTH,
            AdminAuditLog::ACTION_LOGOUT,
            "Logout dari sistem",
            $user,
            null,
            null,
            null,
            $user?->id
        );
    }

    /**
     * Log failed login attempt
     */
    public function logLoginFailed(string $username, ?string $reason = null): AdminAuditLog
    {
        $description = "Gagal login dengan username: {$username}";
        if ($reason) {
            $description .= " - {$reason}";
        }

        return AdminAuditLog::log(
            AdminAuditLog::MODULE_AUTH,
            AdminAuditLog::ACTION_LOGIN_FAILED,
            $description,
            null,
            null,
            ['username' => $username, 'reason' => $reason],
            null,
            null
        );
    }

    // ================================================================
    // USER MANAGEMENT SPECIFIC LOGGING
    // ================================================================

    /**
     * Log user status toggle
     */
    public function logUserStatusToggle(User $user, bool $oldStatus): AdminAuditLog
    {
        $newStatus = $user->is_active;
        $statusText = $newStatus ? 'diaktifkan' : 'dinonaktifkan';

        return AdminAuditLog::log(
            AdminAuditLog::MODULE_USER,
            AdminAuditLog::ACTION_TOGGLE_STATUS,
            "User {$user->name} {$statusText}",
            $user,
            ['is_active' => $oldStatus],
            ['is_active' => $newStatus]
        );
    }

    /**
     * Log password reset
     */
    public function logPasswordReset(User $targetUser): AdminAuditLog
    {
        return AdminAuditLog::log(
            AdminAuditLog::MODULE_USER,
            AdminAuditLog::ACTION_PASSWORD_RESET,
            "Reset password user {$targetUser->name}",
            $targetUser
        );
    }

    // ================================================================
    // QUERY METHODS
    // ================================================================

    /**
     * Get audit logs with filters
     */
    public function getAuditLogs(array $filters = [], int $perPage = 20)
    {
        $query = AdminAuditLog::with('admin:id,name,email,role')
            ->orderBy('created_at', 'desc');

        // Filter by admin
        if (!empty($filters['admin_id'])) {
            $query->byAdmin($filters['admin_id']);
        }

        // Filter by module
        if (!empty($filters['module'])) {
            $query->byModule($filters['module']);
        }

        // Filter by action
        if (!empty($filters['action'])) {
            $query->byAction($filters['action']);
        }

        // Filter by date range
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->dateRange(
                Carbon::parse($filters['start_date'])->startOfDay(),
                Carbon::parse($filters['end_date'])->endOfDay()
            );
        } elseif (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        } elseif (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        // Search
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Get audit log detail
     */
    public function getAuditLogDetail(int $id): ?AdminAuditLog
    {
        return AdminAuditLog::with('admin:id,name,email,role')
            ->find($id);
    }

    /**
     * Get statistics
     */
    public function getStatistics(?string $period = 'this_month'): array
    {
        $dateRange = $this->getDateRange($period);

        $logs = AdminAuditLog::whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);

        // By module
        $byModule = AdminAuditLog::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->select('module', DB::raw('count(*) as count'))
            ->groupBy('module')
            ->pluck('count', 'module')
            ->toArray();

        // By action
        $byAction = AdminAuditLog::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->select('action', DB::raw('count(*) as count'))
            ->groupBy('action')
            ->pluck('count', 'action')
            ->toArray();

        // By admin (top 10)
        $byAdmin = AdminAuditLog::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->select('admin_id', DB::raw('count(*) as count'))
            ->groupBy('admin_id')
            ->orderByDesc('count')
            ->limit(10)
            ->with('admin:id,name')
            ->get()
            ->map(fn($item) => [
                'admin_id' => $item->admin_id,
                'admin_name' => $item->admin?->name ?? 'Unknown',
                'count' => $item->count,
            ])
            ->toArray();

        // Daily trend
        $dailyTrend = AdminAuditLog::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        return [
            'total' => $logs->count(),
            'by_module' => $byModule,
            'by_action' => $byAction,
            'by_admin' => $byAdmin,
            'daily_trend' => $dailyTrend,
        ];
    }

    /**
     * Get recent activities for dashboard
     */
    public function getRecentActivities(int $limit = 10): array
    {
        return AdminAuditLog::with('admin:id,name')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($log) => [
                'id' => $log->id,
                'module' => $log->module,
                'module_label' => $log->module_label,
                'action' => $log->action,
                'action_label' => $log->action_label,
                'action_color' => $log->action_color,
                'description' => $log->description,
                'admin_name' => $log->admin?->name ?? 'System',
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at->diffForHumans(),
            ])
            ->toArray();
    }

    /**
     * Get admin list for filter
     */
    public function getAdminList(): array
    {
        return User::whereIn('role', ['admin', 'finance'])
            ->orderBy('name')
            ->get(['id', 'name', 'role'])
            ->toArray();
    }

    // ================================================================
    // HELPER METHODS
    // ================================================================

    /**
     * Generate create description
     */
    protected function generateCreateDescription(string $module, Model $model): string
    {
        $moduleLabel = $this->getModuleLabel($module);
        $identifier = $this->getModelIdentifier($model);

        return "Menambah {$moduleLabel}: {$identifier}";
    }

    /**
     * Generate update description
     */
    protected function generateUpdateDescription(string $module, Model $model, array $oldValues): string
    {
        $moduleLabel = $this->getModuleLabel($module);
        $identifier = $this->getModelIdentifier($model);
        $changedFields = $this->getChangedFields($model, $oldValues);

        $description = "Mengubah {$moduleLabel}: {$identifier}";
        if (!empty($changedFields)) {
            $description .= " (field: " . implode(', ', $changedFields) . ")";
        }

        return $description;
    }

    /**
     * Generate delete description
     */
    protected function generateDeleteDescription(string $module, Model $model): string
    {
        $moduleLabel = $this->getModuleLabel($module);
        $identifier = $this->getModelIdentifier($model);

        return "Menghapus {$moduleLabel}: {$identifier}";
    }

    /**
     * Get module label in Indonesian
     */
    protected function getModuleLabel(string $module): string
    {
        return AdminAuditLog::getModules()[$module] ?? ucfirst($module);
    }

    /**
     * Get model identifier
     */
    protected function getModelIdentifier(Model $model): string
    {
        // Try common identifier fields
        if (isset($model->name)) {
            return $model->name;
        }
        if (isset($model->customer_id)) {
            return $model->customer_id;
        }
        if (isset($model->invoice_number)) {
            return $model->invoice_number;
        }
        if (isset($model->email)) {
            return $model->email;
        }

        return "#{$model->id}";
    }

    /**
     * Get changed fields
     */
    protected function getChangedFields(Model $model, array $oldValues): array
    {
        $changed = [];
        $newValues = $model->toArray();

        foreach ($oldValues as $key => $oldValue) {
            if (isset($newValues[$key]) && $newValues[$key] != $oldValue) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    /**
     * Get date range from period
     */
    protected function getDateRange(string $period): array
    {
        return match ($period) {
            'today' => [
                'start' => Carbon::today()->startOfDay(),
                'end' => Carbon::today()->endOfDay(),
            ],
            'yesterday' => [
                'start' => Carbon::yesterday()->startOfDay(),
                'end' => Carbon::yesterday()->endOfDay(),
            ],
            'this_week' => [
                'start' => Carbon::now()->startOfWeek(),
                'end' => Carbon::now()->endOfWeek(),
            ],
            'this_month' => [
                'start' => Carbon::now()->startOfMonth(),
                'end' => Carbon::now()->endOfMonth(),
            ],
            'last_month' => [
                'start' => Carbon::now()->subMonth()->startOfMonth(),
                'end' => Carbon::now()->subMonth()->endOfMonth(),
            ],
            'this_year' => [
                'start' => Carbon::now()->startOfYear(),
                'end' => Carbon::now()->endOfYear(),
            ],
            default => [
                'start' => Carbon::now()->startOfMonth(),
                'end' => Carbon::now()->endOfMonth(),
            ],
        };
    }
}
