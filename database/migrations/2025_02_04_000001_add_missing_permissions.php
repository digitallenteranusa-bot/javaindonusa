<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permissions = [
            // Dashboard
            ['name' => 'dashboard.view', 'group' => 'dashboard', 'description' => 'Melihat dashboard'],

            // Additional Customer permissions
            ['name' => 'customers.collect', 'group' => 'customers', 'description' => 'Melakukan penagihan ke pelanggan'],

            // Additional Invoice permissions
            ['name' => 'invoices.manage', 'group' => 'invoices', 'description' => 'Kelola tagihan (CRUD penuh)'],

            // Additional Payment permissions
            ['name' => 'payments.manage', 'group' => 'payments', 'description' => 'Kelola pembayaran (CRUD penuh)'],

            // Collector Management
            ['name' => 'collectors.view', 'group' => 'collectors', 'description' => 'Melihat daftar penagih'],
            ['name' => 'collectors.manage', 'group' => 'collectors', 'description' => 'Kelola data penagih'],
            ['name' => 'collectors.own-data', 'group' => 'collectors', 'description' => 'Akses data sendiri saja'],
        ];

        foreach ($permissions as $permission) {
            // Only insert if not exists
            $exists = DB::table('permissions')->where('name', $permission['name'])->exists();
            if (!$exists) {
                DB::table('permissions')->insert([
                    'name' => $permission['name'],
                    'group' => $permission['group'],
                    'description' => $permission['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Clear permission cache
        \Illuminate\Support\Facades\Cache::forget('permissions_by_role');

        // Assign dashboard.view to all non-admin roles
        $dashboardPermission = DB::table('permissions')->where('name', 'dashboard.view')->first();
        if ($dashboardPermission) {
            $roles = ['penagih', 'technician', 'finance'];
            foreach ($roles as $role) {
                $exists = DB::table('role_permissions')
                    ->where('role', $role)
                    ->where('permission_id', $dashboardPermission->id)
                    ->exists();

                if (!$exists) {
                    DB::table('role_permissions')->insert([
                        'role' => $role,
                        'permission_id' => $dashboardPermission->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Clear permission cache again after role assignments
        \Illuminate\Support\Facades\Cache::forget('permissions_by_role');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissionNames = [
            'dashboard.view',
            'customers.collect',
            'invoices.manage',
            'payments.manage',
            'collectors.view',
            'collectors.manage',
            'collectors.own-data',
        ];

        DB::table('permissions')->whereIn('name', $permissionNames)->delete();

        // Clear permission cache
        \Illuminate\Support\Facades\Cache::forget('permissions_by_role');
    }
};
