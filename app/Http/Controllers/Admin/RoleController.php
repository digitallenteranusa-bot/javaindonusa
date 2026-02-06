<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RoleController extends Controller
{
    /**
     * Available roles in the system
     */
    protected array $roles = [
        'admin' => 'Administrator',
        'penagih' => 'Penagih (Collector)',
        'technician' => 'Teknisi',
        'finance' => 'Keuangan',
    ];

    /**
     * Display roles and permissions management
     */
    public function index()
    {
        // Get all permissions grouped
        $permissions = Permission::orderBy('group')
            ->orderBy('name')
            ->get();

        $permissionsGrouped = $permissions->groupBy('group');

        // Get current role permissions
        $rolePermissions = [];
        foreach ($this->roles as $role => $label) {
            $rolePermissions[$role] = Permission::getForRole($role);
        }

        return Inertia::render('Admin/Role/Index', [
            'roles' => $this->roles,
            'permissions' => $permissions,
            'permissionsGrouped' => $permissionsGrouped,
            'rolePermissions' => $rolePermissions,
            'permissionGroups' => Permission::getGroups(),
        ]);
    }

    /**
     * Update permissions for a role
     */
    public function update(Request $request, string $role)
    {
        if (!array_key_exists($role, $this->roles)) {
            return back()->with('error', 'Role tidak valid');
        }

        // Admin always has all permissions
        if ($role === 'admin') {
            return back()->with('error', 'Permissions untuk Admin tidak dapat diubah');
        }

        $validated = $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        Permission::syncForRole($role, $validated['permissions'] ?? []);

        return back()->with('success', "Permissions untuk {$this->roles[$role]} berhasil diperbarui");
    }

    /**
     * Get permissions for a specific role (API)
     */
    public function getPermissions(string $role)
    {
        if (!array_key_exists($role, $this->roles)) {
            return response()->json(['error' => 'Role tidak valid'], 404);
        }

        return response()->json([
            'role' => $role,
            'permissions' => Permission::getForRole($role),
        ]);
    }

    /**
     * Reset permissions to default for a role
     */
    public function reset(string $role)
    {
        if (!array_key_exists($role, $this->roles)) {
            return back()->with('error', 'Role tidak valid');
        }

        if ($role === 'admin') {
            return back()->with('error', 'Permissions untuk Admin tidak dapat direset');
        }

        $defaultPermissions = $this->getDefaultPermissions($role);
        Permission::syncForRole($role, $defaultPermissions);

        return back()->with('success', "Permissions untuk {$this->roles[$role]} berhasil direset ke default");
    }

    /**
     * Get default permissions for a role
     */
    protected function getDefaultPermissions(string $role): array
    {
        return match ($role) {
            'penagih' => [
                'dashboard.view',
                'customers.view',
                'customers.collect',
                'invoices.view',
                'payments.view',
                'payments.create',
                'areas.view',
                'collectors.own-data',
                'mapping.view',
            ],
            'technician' => [
                'dashboard.view',
                'customers.view',
                'customers.edit',
                'devices.view',
                'devices.manage',
                'devices.link',
                'routers.view',
                'odps.view',
                'odps.create',
                'odps.edit',
                'olts.view',
                'mapping.view',
                'mapping.edit',
            ],
            'finance' => [
                'dashboard.view',
                'customers.view',
                'invoices.view',
                'invoices.generate',
                'invoices.mark-paid',
                'invoices.export',
                'payments.view',
                'payments.create',
                'payments.export',
                'expenses.view',
                'expenses.approve',
                'settlements.view',
                'settlements.verify',
                'reports.view',
                'reports.export',
            ],
            default => [],
        };
    }
}
