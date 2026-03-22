<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Pastikan permission dashboard.view ada
        $permission = Permission::firstOrCreate(
            ['name' => 'dashboard.view'],
            ['group' => 'dashboard', 'description' => 'Melihat dashboard']
        );

        // Tambahkan dashboard.view ke role technician & finance jika belum ada
        foreach (['technician', 'finance'] as $role) {
            $exists = \DB::table('role_permissions')
                ->where('role', $role)
                ->where('permission_id', $permission->id)
                ->exists();

            if (!$exists) {
                \DB::table('role_permissions')->insert([
                    'role' => $role,
                    'permission_id' => $permission->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Clear permission cache
        \Illuminate\Support\Facades\Cache::forget('permissions_by_role');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permission = Permission::where('name', 'dashboard.view')->first();

        if ($permission) {
            \DB::table('role_permissions')
                ->where('permission_id', $permission->id)
                ->whereIn('role', ['technician', 'finance'])
                ->delete();
        }

        \Illuminate\Support\Facades\Cache::forget('permissions_by_role');
    }
};
