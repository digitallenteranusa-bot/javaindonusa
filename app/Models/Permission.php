<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'group',
        'description',
    ];

    // ================================================================
    // CACHE KEY
    // ================================================================

    const CACHE_KEY = 'permissions_by_role';
    const CACHE_TTL = 3600; // 1 hour

    // ================================================================
    // STATIC HELPERS
    // ================================================================

    /**
     * Get all permissions for a role from cache
     */
    public static function getForRole(string $role): array
    {
        $allPermissions = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return \DB::table('role_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->select('role_permissions.role', 'permissions.name')
                ->get()
                ->groupBy('role')
                ->map(fn($items) => $items->pluck('name')->toArray())
                ->toArray();
        });

        return $allPermissions[$role] ?? [];
    }

    /**
     * Check if role has a specific permission
     */
    public static function roleHas(string $role, string $permission): bool
    {
        return in_array($permission, self::getForRole($role));
    }

    /**
     * Clear permission cache
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Sync permissions for a role
     */
    public static function syncForRole(string $role, array $permissionNames): void
    {
        // Get permission IDs
        $permissionIds = self::whereIn('name', $permissionNames)->pluck('id');

        // Delete existing
        \DB::table('role_permissions')->where('role', $role)->delete();

        // Insert new
        $records = $permissionIds->map(fn($id) => [
            'role' => $role,
            'permission_id' => $id,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        if (!empty($records)) {
            \DB::table('role_permissions')->insert($records);
        }

        self::clearCache();
    }

    /**
     * Get permissions grouped by group name
     */
    public static function getGrouped(): array
    {
        return self::orderBy('group')
            ->orderBy('name')
            ->get()
            ->groupBy('group')
            ->toArray();
    }

    /**
     * Get all available permission groups
     */
    public static function getGroups(): array
    {
        return [
            'dashboard' => 'Dashboard',
            'customers' => 'Pelanggan',
            'invoices' => 'Tagihan',
            'payments' => 'Pembayaran',
            'packages' => 'Paket',
            'areas' => 'Area',
            'routers' => 'Router',
            'odps' => 'ODP',
            'olts' => 'OLT',
            'radius' => 'Radius Server',
            'devices' => 'Perangkat',
            'users' => 'Pengguna',
            'roles' => 'Role & Permission',
            'collectors' => 'Penagih',
            'expenses' => 'Pengeluaran',
            'settlements' => 'Setoran',
            'reports' => 'Laporan',
            'mapping' => 'Peta',
            'settings' => 'Pengaturan',
            'audit' => 'Audit Log',
            'system' => 'Sistem',
        ];
    }
}
