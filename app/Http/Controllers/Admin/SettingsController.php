<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\IspInfo;
use App\Models\User;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Admin\UpdateService;
use App\Services\Notification\Channels\WhatsAppChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SettingsController extends Controller
{
    /**
     * Display settings page
     */
    public function index()
    {
        $settings = Setting::pluck('value', 'key')->toArray();
        $ispInfo = IspInfo::first();

        // Get WhatsApp config
        $whatsappConfig = [
            'driver' => $settings['whatsapp_driver'] ?? 'fonnte',
            'api_key' => !empty($settings['whatsapp_api_key']) ? '********' : '',
            'sender' => $settings['whatsapp_sender'] ?? '',
        ];

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
            'ispInfo' => $ispInfo,
            'whatsappConfig' => $whatsappConfig,
            'whatsappDrivers' => WhatsAppChannel::getAvailableDrivers(),
            'logoUrl' => $this->getLogoUrl(),
        ]);
    }

    /**
     * Get current logo URL
     */
    protected function getLogoUrl(): ?string
    {
        $logoPath = Setting::where('key', 'company_logo')->value('value');
        if ($logoPath && Storage::disk('public')->exists($logoPath)) {
            return Storage::url($logoPath);
        }
        return null;
    }

    /**
     * Upload company logo
     */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg|max:2048',
        ]);

        // Delete old logo if exists
        $oldLogo = Setting::where('key', 'company_logo')->value('value');
        if ($oldLogo && Storage::disk('public')->exists($oldLogo)) {
            Storage::disk('public')->delete($oldLogo);
        }

        // Store new logo
        $path = $request->file('logo')->store('logos', 'public');

        Setting::updateOrCreate(
            ['group' => 'branding', 'key' => 'company_logo'],
            ['value' => $path]
        );

        return back()->with('success', 'Logo berhasil diupload');
    }

    /**
     * Delete company logo
     */
    public function deleteLogo()
    {
        $logoPath = Setting::where('key', 'company_logo')->value('value');
        if ($logoPath && Storage::disk('public')->exists($logoPath)) {
            Storage::disk('public')->delete($logoPath);
        }

        Setting::where('key', 'company_logo')->delete();

        return back()->with('success', 'Logo berhasil dihapus');
    }

    /**
     * Update WhatsApp configuration
     */
    public function updateWhatsApp(Request $request)
    {
        $validated = $request->validate([
            'driver' => ['required', Rule::in(array_keys(WhatsAppChannel::getAvailableDrivers()))],
            'api_key' => 'nullable|string|max:500',
            'sender' => 'nullable|string|max:20',
        ]);

        Setting::updateOrCreate(
            ['group' => 'notification', 'key' => 'whatsapp_driver'],
            ['value' => $validated['driver']]
        );

        // Only update API key if provided (not the masked placeholder)
        if (!empty($validated['api_key']) && $validated['api_key'] !== '********') {
            Setting::updateOrCreate(
                ['group' => 'notification', 'key' => 'whatsapp_api_key'],
                ['value' => $validated['api_key']]
            );
        }

        Setting::updateOrCreate(
            ['group' => 'notification', 'key' => 'whatsapp_sender'],
            ['value' => $validated['sender'] ?? '']
        );

        return back()->with('success', 'Konfigurasi WhatsApp berhasil disimpan');
    }

    /**
     * Test WhatsApp connection and send test message
     */
    public function testWhatsApp(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        // Format phone number
        $phone = preg_replace('/[^0-9]/', '', $validated['phone']);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '62')) {
            $phone = '62' . $phone;
        }

        $channel = new WhatsAppChannel();
        $result = $channel->send($phone, 'Ini adalah pesan tes dari ISP Billing System. Jika Anda menerima pesan ini, konfigurasi WhatsApp sudah benar.');

        if ($result['success']) {
            return back()->with('success', 'Pesan tes berhasil dikirim');
        }

        return back()->with('error', 'Gagal mengirim pesan: ' . ($result['message'] ?? 'Unknown error'));
    }

    /**
     * Check WhatsApp connection status
     */
    public function checkWhatsAppStatus()
    {
        $channel = new WhatsAppChannel();
        $result = $channel->checkStatus();

        return response()->json($result);
    }

    /**
     * Update general settings
     */
    public function updateGeneral(Request $request)
    {
        $validated = $request->validate([
            'billing_due_date' => 'required|integer|min:1|max:28',
            'billing_grace_days' => 'required|integer|min:0|max:30',
            'isolation_threshold_months' => 'required|integer|min:1|max:12',
            'rapel_tolerance_months' => 'required|integer|min:1|max:12',
            'recent_payment_days' => 'required|integer|min:1|max:90',
        ]);

        // Group mapping for settings
        $groupMapping = [
            'billing_due_date' => 'billing',
            'billing_grace_days' => 'billing',
            'isolation_threshold_months' => 'isolation',
            'rapel_tolerance_months' => 'isolation',
            'recent_payment_days' => 'isolation',
        ];

        foreach ($validated as $key => $value) {
            $group = $groupMapping[$key] ?? 'billing';
            Setting::updateOrCreate(
                ['group' => $group, 'key' => $key],
                ['value' => $value]
            );
        }

        return back()->with('success', 'Pengaturan billing berhasil disimpan');
    }

    /**
     * Update ISP info
     */
    public function updateIspInfo(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone_primary' => 'required|string|max:20',
            'phone_secondary' => 'nullable|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'email' => 'nullable|string|email:filter',
            'website' => 'nullable|string|max:255',
            'bank_accounts' => 'nullable|array',
            'bank_accounts.*.bank' => 'nullable|string',
            'bank_accounts.*.account' => 'nullable|string',
            'bank_accounts.*.name' => 'nullable|string',
        ]);

        // Filter empty bank accounts
        if (isset($validated['bank_accounts'])) {
            $validated['bank_accounts'] = array_filter($validated['bank_accounts'], function ($acc) {
                return !empty($acc['bank']) || !empty($acc['account']) || !empty($acc['name']);
            });
            $validated['bank_accounts'] = array_values($validated['bank_accounts']);
            if (empty($validated['bank_accounts'])) {
                $validated['bank_accounts'] = null;
            }
        }

        // Clean empty strings to null
        foreach (['tagline', 'address', 'phone_secondary', 'whatsapp_number', 'email', 'website'] as $field) {
            if (isset($validated[$field]) && $validated[$field] === '') {
                $validated[$field] = null;
            }
        }

        try {
            $ispInfo = IspInfo::first();
            if ($ispInfo) {
                $ispInfo->update($validated);
            } else {
                $ispInfo = IspInfo::create($validated);
            }
            IspInfo::clearCache();

            return back()->with('success', 'Informasi ISP berhasil disimpan');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /**
     * Update notification settings
     */
    public function updateNotification(Request $request)
    {
        $validated = $request->validate([
            'whatsapp_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'reminder_days_before' => 'required|integer|min:1|max:14',
            'reminder_template' => 'nullable|string',
            'overdue_template' => 'nullable|string',
            'isolation_template' => 'nullable|string',
            'payment_confirmation_template' => 'nullable|string',
        ]);

        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(
                ['group' => 'notification', 'key' => $key],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : $value]
            );
        }

        return back()->with('success', 'Pengaturan notifikasi berhasil disimpan');
    }

    /**
     * Update Mikrotik settings
     */
    public function updateMikrotik(Request $request)
    {
        $validated = $request->validate([
            'mikrotik_auto_isolate' => 'boolean',
            'mikrotik_auto_reopen' => 'boolean',
            'isolation_profile' => 'nullable|string|max:50',
            'isolation_address_list' => 'nullable|string|max:50',
        ]);

        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(
                ['group' => 'system', 'key' => $key],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : $value]
            );
        }

        return back()->with('success', 'Pengaturan Mikrotik berhasil disimpan');
    }

    /**
     * Update GenieACS settings
     */
    public function updateGenieacs(Request $request)
    {
        $validated = $request->validate([
            'genieacs_enabled' => 'boolean',
            'genieacs_nbi_url' => 'nullable|url',
            'genieacs_ui_url' => 'nullable|url',
            'genieacs_fs_url' => 'nullable|url',
            'genieacs_username' => 'nullable|string|max:100',
            'genieacs_password' => 'nullable|string|max:100',
            'genieacs_sync_interval' => 'required|integer|min:5|max:60',
        ]);

        foreach ($validated as $key => $value) {
            // Skip empty password
            if ($key === 'genieacs_password' && empty($value)) {
                continue;
            }

            Setting::updateOrCreate(
                ['group' => 'system', 'key' => $key],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : $value]
            );
        }

        return back()->with('success', 'Pengaturan GenieACS berhasil disimpan');
    }

    /**
     * Display system info page
     */
    public function system()
    {
        $appVersion = config('app.version', '1.0.0');

        // Get system statistics
        $stats = [
            'total_customers' => Customer::count(),
            'active_customers' => Customer::where('status', 'active')->count(),
            'total_users' => User::count(),
            'total_invoices' => Invoice::count(),
            'total_payments' => Payment::count(),
            'database_size' => $this->getDatabaseSize(),
        ];

        // Get PHP & Laravel info
        $systemInfo = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_os' => PHP_OS,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'timezone' => config('app.timezone'),
            'max_upload' => ini_get('upload_max_filesize'),
            'memory_limit' => ini_get('memory_limit'),
            'server_time' => now()->format('Y-m-d H:i:s'),
            'server_time_formatted' => now()->translatedFormat('l, d F Y - H:i:s'),
            'uptime' => $this->getServerUptime(),
        ];

        // Get update info (stored in settings)
        $updateInfo = [
            'current_version' => $appVersion,
            'last_check' => Setting::where('key', 'last_update_check')->value('value'),
            'latest_version' => Setting::where('key', 'latest_version')->value('value'),
            'update_available' => false,
        ];

        $latestVersion = $updateInfo['latest_version'];
        if ($latestVersion && version_compare($latestVersion, $appVersion, '>')) {
            $updateInfo['update_available'] = true;
        }

        return Inertia::render('Admin/System/Index', [
            'appVersion' => $appVersion,
            'stats' => $stats,
            'systemInfo' => $systemInfo,
            'updateInfo' => $updateInfo,
        ]);
    }

    /**
     * Check for updates
     */
    public function checkUpdate(UpdateService $updateService)
    {
        $result = $updateService->checkForUpdates();

        if ($result['success']) {
            if ($result['update_available']) {
                return back()->with('info', "Update tersedia: v{$result['latest_version']}");
            } else {
                return back()->with('success', 'Aplikasi sudah versi terbaru');
            }
        }

        return back()->with('error', $result['error'] ?? 'Gagal mengecek update');
    }

    /**
     * Create backup before update
     */
    public function createBackup(UpdateService $updateService)
    {
        $result = $updateService->createBackup();

        if ($result['success']) {
            return back()->with('success', "Backup berhasil dibuat: {$result['backup_file']} ({$result['backup_size']})");
        }

        return back()->with('error', $result['error'] ?? 'Gagal membuat backup');
    }

    /**
     * Install update from uploaded file
     */
    public function installUpdate(Request $request, UpdateService $updateService)
    {
        $request->validate([
            'update_file' => 'required|file|mimes:zip|max:102400', // Max 100MB
        ]);

        // Create backup first
        $backupResult = $updateService->createBackup();
        if (!$backupResult['success']) {
            return back()->with('error', 'Gagal membuat backup sebelum update');
        }

        // Install update
        $result = $updateService->installFromUpload($request->file('update_file'));

        if ($result['success']) {
            return back()->with('success', "Update berhasil diinstall. Versi baru: {$result['new_version']}");
        }

        return back()->with('error', $result['error'] ?? 'Gagal menginstall update');
    }

    /**
     * Download and install update from server
     */
    public function downloadAndInstall(Request $request, UpdateService $updateService)
    {
        $request->validate([
            'download_url' => 'required|url',
        ]);

        // Create backup first
        $backupResult = $updateService->createBackup();
        if (!$backupResult['success']) {
            return back()->with('error', 'Gagal membuat backup sebelum update');
        }

        // Download update
        $downloadResult = $updateService->downloadUpdate($request->download_url);
        if (!$downloadResult['success']) {
            return back()->with('error', $downloadResult['error'] ?? 'Gagal download update');
        }

        // Install update
        $result = $updateService->installUpdate($downloadResult['file_path']);

        if ($result['success']) {
            return back()->with('success', "Update berhasil diinstall. Versi baru: {$result['new_version']}");
        }

        return back()->with('error', $result['error'] ?? 'Gagal menginstall update');
    }

    /**
     * Get list of backups
     */
    public function getBackups(UpdateService $updateService)
    {
        return response()->json([
            'backups' => $updateService->getBackups(),
        ]);
    }

    /**
     * Download backup file
     */
    public function downloadBackup(string $filename)
    {
        $backupPath = storage_path('app/backups/' . $filename);

        // Security: only allow .zip files and prevent directory traversal
        if (!str_ends_with($filename, '.zip') || str_contains($filename, '..') || str_contains($filename, '/')) {
            abort(404, 'Backup tidak ditemukan');
        }

        if (!file_exists($backupPath)) {
            return back()->with('error', 'File backup tidak ditemukan');
        }

        return response()->download($backupPath, $filename, [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * Upload backup file
     */
    public function uploadBackup(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|file|mimes:zip|max:102400', // Max 100MB
        ]);

        try {
            $file = $request->file('backup_file');
            $filename = $file->getClientOriginalName();

            // Ensure backup directory exists
            $backupPath = storage_path('app/backups');
            if (!is_dir($backupPath)) {
                mkdir($backupPath, 0755, true);
            }

            // If file already exists, add timestamp
            if (file_exists($backupPath . '/' . $filename)) {
                $filename = pathinfo($filename, PATHINFO_FILENAME) . '_' . time() . '.zip';
            }

            $file->move($backupPath, $filename);

            return back()->with('success', "Backup '{$filename}' berhasil diupload");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal upload backup: ' . $e->getMessage());
        }
    }

    /**
     * Restore from backup
     */
    public function restoreBackup(Request $request, UpdateService $updateService)
    {
        $request->validate([
            'backup_file' => 'required|string',
        ]);

        $result = $updateService->restoreBackup($request->backup_file);

        if ($result['success']) {
            return back()->with('success', 'Restore berhasil dilakukan');
        }

        return back()->with('error', $result['error'] ?? 'Gagal restore backup');
    }

    /**
     * Delete a backup
     */
    public function deleteBackup(Request $request, UpdateService $updateService)
    {
        $request->validate([
            'backup_file' => 'required|string',
        ]);

        if ($updateService->deleteBackup($request->backup_file)) {
            return back()->with('success', 'Backup berhasil dihapus');
        }

        return back()->with('error', 'Gagal menghapus backup');
    }

    /**
     * Create database backup
     */
    public function createDatabaseBackup(UpdateService $updateService)
    {
        $result = $updateService->createDatabaseBackup();

        if ($result['success']) {
            return back()->with('success', "Backup database berhasil: {$result['backup_file']} ({$result['backup_size']})");
        }

        return back()->with('error', $result['error'] ?? 'Gagal membuat backup database');
    }

    /**
     * Get list of database backups
     */
    public function getDatabaseBackups(UpdateService $updateService)
    {
        return response()->json([
            'backups' => $updateService->getDatabaseBackups(),
        ]);
    }

    /**
     * Download database backup file
     */
    public function downloadDatabaseBackup(string $filename)
    {
        // Security: prevent directory traversal
        if (str_contains($filename, '..') || str_contains($filename, '/')) {
            abort(404, 'Backup tidak ditemukan');
        }

        $backupPath = storage_path('app/backups/database/' . $filename);

        if (!file_exists($backupPath)) {
            return back()->with('error', 'File backup database tidak ditemukan');
        }

        return response()->download($backupPath, $filename);
    }

    /**
     * Upload database backup file
     */
    public function uploadDatabaseBackup(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|file|max:512000', // Max 500MB
        ]);

        try {
            $file = $request->file('backup_file');
            $filename = $file->getClientOriginalName();
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            // Allow .sql, .sql.gz, or .gz
            if (!in_array($ext, ['sql', 'gz'])) {
                return back()->with('error', 'File harus berformat .sql atau .sql.gz');
            }

            $dbBackupPath = storage_path('app/backups/database');
            if (!is_dir($dbBackupPath)) {
                mkdir($dbBackupPath, 0755, true);
            }

            if (file_exists($dbBackupPath . '/' . $filename)) {
                $filename = pathinfo($filename, PATHINFO_FILENAME) . '_' . time() . '.' . $ext;
            }

            $file->move($dbBackupPath, $filename);

            return back()->with('success', "Backup database '{$filename}' berhasil diupload");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal upload backup database: ' . $e->getMessage());
        }
    }

    /**
     * Restore database from backup
     */
    public function restoreDatabaseBackup(Request $request, UpdateService $updateService)
    {
        $request->validate([
            'backup_file' => 'required|string',
        ]);

        $result = $updateService->restoreDatabaseBackup($request->backup_file);

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['error'] ?? 'Gagal restore database');
    }

    /**
     * Delete a database backup
     */
    public function deleteDatabaseBackup(Request $request, UpdateService $updateService)
    {
        $request->validate([
            'backup_file' => 'required|string',
        ]);

        if ($updateService->deleteDatabaseBackup($request->backup_file)) {
            return back()->with('success', 'Backup database berhasil dihapus');
        }

        return back()->with('error', 'Gagal menghapus backup database');
    }

    /**
     * Clear application cache
     */
    public function clearCache()
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('view:clear');
            Artisan::call('route:clear');

            return back()->with('success', 'Cache berhasil dibersihkan');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal membersihkan cache: ' . $e->getMessage());
        }
    }

    /**
     * Get database size
     */
    protected function getDatabaseSize(): string
    {
        try {
            $dbName = config('database.connections.mysql.database');
            $result = DB::select("
                SELECT
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables
                WHERE table_schema = ?
            ", [$dbName]);

            return ($result[0]->size_mb ?? 0) . ' MB';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    /**
     * Get server uptime
     */
    protected function getServerUptime(): string
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows
                $uptime = shell_exec('net stats workstation | find "Statistics since"');
                if ($uptime) {
                    return trim(str_replace('Statistics since', 'Sejak', $uptime));
                }
                return 'N/A';
            } else {
                // Linux/Unix
                $uptime = shell_exec('uptime -p');
                if ($uptime) {
                    return trim($uptime);
                }
                return 'N/A';
            }
        } catch (\Exception $e) {
            return 'N/A';
        }
    }
}
