<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing permissions
        DB::table('role_permissions')->truncate();
        DB::table('permissions')->truncate();

        $permissions = [
            // Customer Management
            ['name' => 'customers.view', 'group' => 'customers', 'description' => 'Melihat daftar pelanggan'],
            ['name' => 'customers.create', 'group' => 'customers', 'description' => 'Menambah pelanggan baru'],
            ['name' => 'customers.edit', 'group' => 'customers', 'description' => 'Mengubah data pelanggan'],
            ['name' => 'customers.delete', 'group' => 'customers', 'description' => 'Menghapus pelanggan'],
            ['name' => 'customers.adjust-debt', 'group' => 'customers', 'description' => 'Menyesuaikan hutang pelanggan'],
            ['name' => 'customers.write-off', 'group' => 'customers', 'description' => 'Write-off hutang pelanggan'],

            // Invoice Management
            ['name' => 'invoices.view', 'group' => 'invoices', 'description' => 'Melihat daftar tagihan'],
            ['name' => 'invoices.generate', 'group' => 'invoices', 'description' => 'Generate tagihan'],
            ['name' => 'invoices.mark-paid', 'group' => 'invoices', 'description' => 'Tandai tagihan lunas'],
            ['name' => 'invoices.cancel', 'group' => 'invoices', 'description' => 'Batalkan tagihan'],
            ['name' => 'invoices.export', 'group' => 'invoices', 'description' => 'Export data tagihan'],

            // Payment Management
            ['name' => 'payments.view', 'group' => 'payments', 'description' => 'Melihat daftar pembayaran'],
            ['name' => 'payments.create', 'group' => 'payments', 'description' => 'Input pembayaran baru'],
            ['name' => 'payments.cancel', 'group' => 'payments', 'description' => 'Batalkan pembayaran'],
            ['name' => 'payments.export', 'group' => 'payments', 'description' => 'Export data pembayaran'],

            // Package Management
            ['name' => 'packages.view', 'group' => 'packages', 'description' => 'Melihat daftar paket'],
            ['name' => 'packages.create', 'group' => 'packages', 'description' => 'Menambah paket baru'],
            ['name' => 'packages.edit', 'group' => 'packages', 'description' => 'Mengubah data paket'],
            ['name' => 'packages.delete', 'group' => 'packages', 'description' => 'Menghapus paket'],

            // Area Management
            ['name' => 'areas.view', 'group' => 'areas', 'description' => 'Melihat daftar area'],
            ['name' => 'areas.create', 'group' => 'areas', 'description' => 'Menambah area baru'],
            ['name' => 'areas.edit', 'group' => 'areas', 'description' => 'Mengubah data area'],
            ['name' => 'areas.delete', 'group' => 'areas', 'description' => 'Menghapus area'],

            // Router Management
            ['name' => 'routers.view', 'group' => 'routers', 'description' => 'Melihat daftar router'],
            ['name' => 'routers.create', 'group' => 'routers', 'description' => 'Menambah router baru'],
            ['name' => 'routers.edit', 'group' => 'routers', 'description' => 'Mengubah data router'],
            ['name' => 'routers.delete', 'group' => 'routers', 'description' => 'Menghapus router'],
            ['name' => 'routers.vpn-config', 'group' => 'routers', 'description' => 'Generate VPN config'],

            // ODP Management
            ['name' => 'odps.view', 'group' => 'odps', 'description' => 'Melihat daftar ODP'],
            ['name' => 'odps.create', 'group' => 'odps', 'description' => 'Menambah ODP baru'],
            ['name' => 'odps.edit', 'group' => 'odps', 'description' => 'Mengubah data ODP'],
            ['name' => 'odps.delete', 'group' => 'odps', 'description' => 'Menghapus ODP'],

            // OLT Management
            ['name' => 'olts.view', 'group' => 'olts', 'description' => 'Melihat daftar OLT'],
            ['name' => 'olts.create', 'group' => 'olts', 'description' => 'Menambah OLT baru'],
            ['name' => 'olts.edit', 'group' => 'olts', 'description' => 'Mengubah data OLT'],
            ['name' => 'olts.delete', 'group' => 'olts', 'description' => 'Menghapus OLT'],

            // Radius Server Management
            ['name' => 'radius.view', 'group' => 'radius', 'description' => 'Melihat daftar Radius Server'],
            ['name' => 'radius.create', 'group' => 'radius', 'description' => 'Menambah Radius Server baru'],
            ['name' => 'radius.edit', 'group' => 'radius', 'description' => 'Mengubah data Radius Server'],
            ['name' => 'radius.delete', 'group' => 'radius', 'description' => 'Menghapus Radius Server'],

            // Device Management (GenieACS)
            ['name' => 'devices.view', 'group' => 'devices', 'description' => 'Melihat daftar device'],
            ['name' => 'devices.manage', 'group' => 'devices', 'description' => 'Manage device (reboot, refresh)'],
            ['name' => 'devices.link', 'group' => 'devices', 'description' => 'Link device ke pelanggan'],
            ['name' => 'devices.delete', 'group' => 'devices', 'description' => 'Hapus device'],

            // User Management
            ['name' => 'users.view', 'group' => 'users', 'description' => 'Melihat daftar user'],
            ['name' => 'users.create', 'group' => 'users', 'description' => 'Menambah user baru'],
            ['name' => 'users.edit', 'group' => 'users', 'description' => 'Mengubah data user'],
            ['name' => 'users.delete', 'group' => 'users', 'description' => 'Menghapus user'],
            ['name' => 'users.reset-password', 'group' => 'users', 'description' => 'Reset password user'],

            // Roles & Permissions
            ['name' => 'roles.view', 'group' => 'roles', 'description' => 'Melihat role & permissions'],
            ['name' => 'roles.edit', 'group' => 'roles', 'description' => 'Mengubah permissions role'],

            // Expense Management
            ['name' => 'expenses.view', 'group' => 'expenses', 'description' => 'Melihat daftar expense'],
            ['name' => 'expenses.approve', 'group' => 'expenses', 'description' => 'Approve expense'],
            ['name' => 'expenses.reject', 'group' => 'expenses', 'description' => 'Reject expense'],

            // Settlement Management
            ['name' => 'settlements.view', 'group' => 'settlements', 'description' => 'Melihat daftar settlement'],
            ['name' => 'settlements.verify', 'group' => 'settlements', 'description' => 'Verifikasi settlement'],
            ['name' => 'settlements.reject', 'group' => 'settlements', 'description' => 'Reject settlement'],

            // Reports
            ['name' => 'reports.view', 'group' => 'reports', 'description' => 'Melihat laporan'],
            ['name' => 'reports.export', 'group' => 'reports', 'description' => 'Export laporan'],

            // Mapping
            ['name' => 'mapping.view', 'group' => 'mapping', 'description' => 'Melihat peta pelanggan & ODP'],
            ['name' => 'mapping.edit', 'group' => 'mapping', 'description' => 'Update lokasi pada peta'],

            // Settings
            ['name' => 'settings.view', 'group' => 'settings', 'description' => 'Melihat pengaturan'],
            ['name' => 'settings.edit', 'group' => 'settings', 'description' => 'Mengubah pengaturan'],
            ['name' => 'settings.branding', 'group' => 'settings', 'description' => 'Kelola logo & branding'],
            ['name' => 'settings.whatsapp', 'group' => 'settings', 'description' => 'Konfigurasi WhatsApp gateway'],

            // Audit Log
            ['name' => 'audit.view', 'group' => 'audit', 'description' => 'Melihat audit log'],
            ['name' => 'audit.export', 'group' => 'audit', 'description' => 'Export audit log'],

            // System
            ['name' => 'system.view', 'group' => 'system', 'description' => 'Melihat info sistem'],
            ['name' => 'system.manage', 'group' => 'system', 'description' => 'Manage sistem (cache, update, backup)'],
        ];

        // Insert permissions
        foreach ($permissions as $permission) {
            Permission::create($permission);
        }

        // Default permissions for 'penagih' role
        $penagihPermissions = [
            'customers.view',
            'invoices.view',
            'payments.view',
            'payments.create',
            'areas.view',
        ];

        // Default permissions for 'teknisi' role (if exists)
        $teknisiPermissions = [
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
        ];

        // Assign default permissions to roles
        Permission::syncRolePermissions('penagih', $penagihPermissions);
        Permission::syncRolePermissions('teknisi', $teknisiPermissions);

        $this->command->info('Permissions seeded successfully!');
        $this->command->info('Default permissions assigned to penagih and teknisi roles.');
    }
}
