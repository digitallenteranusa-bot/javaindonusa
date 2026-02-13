<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['name' => 'finance.view', 'group' => 'finance', 'description' => 'Melihat dashboard keuangan'],
            ['name' => 'finance.manage', 'group' => 'finance', 'description' => 'Kelola pengeluaran operasional'],
        ];

        foreach ($permissions as $permission) {
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

        // Assign finance permissions to finance role
        foreach (['finance.view', 'finance.manage'] as $permName) {
            $perm = DB::table('permissions')->where('name', $permName)->first();
            if ($perm) {
                $exists = DB::table('role_permissions')
                    ->where('role', 'finance')
                    ->where('permission_id', $perm->id)
                    ->exists();

                if (!$exists) {
                    DB::table('role_permissions')->insert([
                        'role' => 'finance',
                        'permission_id' => $perm->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        Cache::forget('permissions_by_role');
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', ['finance.view', 'finance.manage'])->delete();
        Cache::forget('permissions_by_role');
    }
};
