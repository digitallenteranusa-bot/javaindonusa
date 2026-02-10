<?php

namespace App\Services\Admin;

use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class UpdateService
{
    protected string $updateServer;
    protected string $backupPath;
    protected string $dbBackupPath;
    protected string $tempPath;

    public function __construct()
    {
        $this->updateServer = config('app.update_server', 'https://update.javaindonusa.net');
        $this->backupPath = storage_path('app/backups');
        $this->dbBackupPath = storage_path('app/backups/database');
        $this->tempPath = storage_path('app/temp');
    }

    /**
     * Check for available updates
     */
    public function checkForUpdates(): array
    {
        try {
            $currentVersion = config('app.version', '1.0.0');

            // Try to get update info from server
            $response = Http::timeout(10)->get("{$this->updateServer}/api/version");

            if ($response->successful()) {
                $data = $response->json();
                $latestVersion = $data['version'] ?? $currentVersion;
                $changelog = $data['changelog'] ?? [];
                $downloadUrl = $data['download_url'] ?? null;
                $minPhpVersion = $data['min_php_version'] ?? '8.1';
                $releaseDate = $data['release_date'] ?? null;
            } else {
                // Fallback: check from local or return current version
                $latestVersion = $currentVersion;
                $changelog = [];
                $downloadUrl = null;
                $minPhpVersion = '8.1';
                $releaseDate = null;
            }

            $updateAvailable = version_compare($latestVersion, $currentVersion, '>');

            // Save check result
            Setting::updateOrCreate(
                ['group' => 'system', 'key' => 'last_update_check'],
                ['value' => now()->toDateTimeString()]
            );

            Setting::updateOrCreate(
                ['group' => 'system', 'key' => 'latest_version'],
                ['value' => $latestVersion]
            );

            return [
                'success' => true,
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'update_available' => $updateAvailable,
                'changelog' => $changelog,
                'download_url' => $downloadUrl,
                'min_php_version' => $minPhpVersion,
                'release_date' => $releaseDate,
                'php_compatible' => version_compare(PHP_VERSION, $minPhpVersion, '>='),
                'checked_at' => now()->toDateTimeString(),
            ];
        } catch (\Exception $e) {
            Log::error('Update check failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'current_version' => config('app.version', '1.0.0'),
                'latest_version' => config('app.version', '1.0.0'),
                'update_available' => false,
                'error' => 'Gagal mengecek update: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Download update package
     */
    public function downloadUpdate(string $downloadUrl): array
    {
        try {
            // Ensure temp directory exists
            if (!File::isDirectory($this->tempPath)) {
                File::makeDirectory($this->tempPath, 0755, true);
            }

            $fileName = 'update_' . time() . '.zip';
            $filePath = "{$this->tempPath}/{$fileName}";

            // Download file
            $response = Http::timeout(300)->withOptions([
                'sink' => $filePath,
            ])->get($downloadUrl);

            if (!$response->successful()) {
                throw new \Exception('Failed to download update package');
            }

            // Verify file exists and is a valid zip
            if (!File::exists($filePath)) {
                throw new \Exception('Download file not found');
            }

            $zip = new ZipArchive();
            if ($zip->open($filePath) !== true) {
                File::delete($filePath);
                throw new \Exception('Invalid update package');
            }
            $zip->close();

            return [
                'success' => true,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_size' => File::size($filePath),
            ];
        } catch (\Exception $e) {
            Log::error('Update download failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create backup before update
     */
    public function createBackup(): array
    {
        try {
            // Ensure backup directory exists
            if (!File::isDirectory($this->backupPath)) {
                File::makeDirectory($this->backupPath, 0755, true);
            }

            $backupName = 'backup_' . date('Y-m-d_His') . '.zip';
            $backupFile = "{$this->backupPath}/{$backupName}";

            $zip = new ZipArchive();
            if ($zip->open($backupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \Exception('Cannot create backup file');
            }

            // Backup important directories
            $dirsToBackup = [
                'app',
                'config',
                'database/migrations',
                'resources/js',
                'resources/views',
                'routes',
            ];

            foreach ($dirsToBackup as $dir) {
                $fullPath = base_path($dir);
                if (File::isDirectory($fullPath)) {
                    $this->addDirectoryToZip($zip, $fullPath, $dir);
                }
            }

            // Backup important files
            $filesToBackup = [
                'composer.json',
                'composer.lock',
                'package.json',
                '.env',
            ];

            foreach ($filesToBackup as $file) {
                $fullPath = base_path($file);
                if (File::exists($fullPath)) {
                    $zip->addFile($fullPath, $file);
                }
            }

            $zip->close();

            // Save backup info using Setting::setValue helper
            Setting::setValue('system', 'last_backup', [
                'file' => $backupName,
                'path' => $backupFile,
                'created_at' => now()->toDateTimeString(),
                'size' => File::size($backupFile),
            ], 'array');

            return [
                'success' => true,
                'backup_file' => $backupName,
                'backup_path' => $backupFile,
                'backup_size' => $this->formatBytes(File::size($backupFile)),
            ];
        } catch (\Exception $e) {
            Log::error('Backup creation failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Install update from uploaded or downloaded package
     */
    public function installUpdate(string $packagePath): array
    {
        try {
            // Verify package exists
            if (!File::exists($packagePath)) {
                throw new \Exception('Update package not found');
            }

            // Put application in maintenance mode
            Artisan::call('down', ['--secret' => 'update-in-progress']);

            $zip = new ZipArchive();
            if ($zip->open($packagePath) !== true) {
                Artisan::call('up');
                throw new \Exception('Cannot open update package');
            }

            // Extract to temp directory first
            $extractPath = "{$this->tempPath}/extract_" . time();
            File::makeDirectory($extractPath, 0755, true);

            $zip->extractTo($extractPath);
            $zip->close();

            // Find the root directory in the extracted content
            $extractedDirs = File::directories($extractPath);
            $sourceDir = count($extractedDirs) === 1 ? $extractedDirs[0] : $extractPath;

            // Copy files to application
            $this->copyDirectory($sourceDir, base_path());

            // Clean up extraction directory
            File::deleteDirectory($extractPath);

            // Run post-update commands
            $this->runPostUpdateCommands();

            // Bring application back up
            Artisan::call('up');

            // Update version in settings
            $newVersion = config('app.version', '1.0.0');
            Setting::updateOrCreate(
                ['group' => 'system', 'key' => 'last_update'],
                ['value' => json_encode([
                    'version' => $newVersion,
                    'installed_at' => now()->toDateTimeString(),
                ])]
            );

            return [
                'success' => true,
                'message' => 'Update berhasil diinstall',
                'new_version' => $newVersion,
            ];
        } catch (\Exception $e) {
            // Ensure application is back up
            Artisan::call('up');

            Log::error('Update installation failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Install update from uploaded file
     */
    public function installFromUpload($uploadedFile): array
    {
        try {
            // Validate file
            if (!$uploadedFile->isValid()) {
                throw new \Exception('File upload tidak valid');
            }

            $extension = strtolower($uploadedFile->getClientOriginalExtension());
            if ($extension !== 'zip') {
                throw new \Exception('File harus berformat ZIP');
            }

            // Save uploaded file
            $fileName = 'upload_' . time() . '.zip';
            $filePath = "{$this->tempPath}/{$fileName}";

            if (!File::isDirectory($this->tempPath)) {
                File::makeDirectory($this->tempPath, 0755, true);
            }

            $uploadedFile->move($this->tempPath, $fileName);

            // Install the update
            return $this->installUpdate($filePath);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Run post-update commands
     */
    protected function runPostUpdateCommands(): void
    {
        // Run composer install if composer.json changed
        if (File::exists(base_path('composer.json'))) {
            // Note: In production, you might want to run this via shell
            // exec('cd ' . base_path() . ' && composer install --no-dev --optimize-autoloader 2>&1');
        }

        // Run migrations
        Artisan::call('migrate', ['--force' => true]);

        // Clear all caches
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');
        Artisan::call('route:clear');

        // Rebuild caches
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');
    }

    /**
     * Restore from backup
     */
    public function restoreBackup(string $backupFile): array
    {
        try {
            $backupPath = "{$this->backupPath}/{$backupFile}";

            if (!File::exists($backupPath)) {
                throw new \Exception('Backup file not found');
            }

            // Put in maintenance mode
            Artisan::call('down', ['--secret' => 'restore-in-progress']);

            $zip = new ZipArchive();
            if ($zip->open($backupPath) !== true) {
                Artisan::call('up');
                throw new \Exception('Cannot open backup file');
            }

            // Extract backup
            $zip->extractTo(base_path());
            $zip->close();

            // Run post-restore commands
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('cache:clear');
            Artisan::call('config:clear');

            // Bring back up
            Artisan::call('up');

            return [
                'success' => true,
                'message' => 'Restore berhasil',
            ];
        } catch (\Exception $e) {
            Artisan::call('up');

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get list of available backups
     */
    public function getBackups(): array
    {
        $backups = [];

        if (File::isDirectory($this->backupPath)) {
            $files = File::files($this->backupPath);

            foreach ($files as $file) {
                if ($file->getExtension() === 'zip') {
                    $backups[] = [
                        'name' => $file->getFilename(),
                        'size' => $this->formatBytes($file->getSize()),
                        'created_at' => date('Y-m-d H:i:s', $file->getMTime()),
                    ];
                }
            }

            // Sort by date descending
            usort($backups, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
        }

        return $backups;
    }

    /**
     * Delete a backup file
     */
    public function deleteBackup(string $backupFile): bool
    {
        $path = "{$this->backupPath}/{$backupFile}";

        if (File::exists($path)) {
            return File::delete($path);
        }

        return false;
    }

    /**
     * Clean up old backups (keep last N)
     */
    public function cleanOldBackups(int $keepLast = 5): int
    {
        $backups = $this->getBackups();
        $deleted = 0;

        if (count($backups) > $keepLast) {
            $toDelete = array_slice($backups, $keepLast);

            foreach ($toDelete as $backup) {
                if ($this->deleteBackup($backup['name'])) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Create database backup using mysqldump
     */
    public function createDatabaseBackup(): array
    {
        try {
            if (!File::isDirectory($this->dbBackupPath)) {
                File::makeDirectory($this->dbBackupPath, 0755, true);
            }

            $dbHost = config('database.connections.mysql.host', '127.0.0.1');
            $dbPort = config('database.connections.mysql.port', '3306');
            $dbName = config('database.connections.mysql.database');
            $dbUser = config('database.connections.mysql.username');
            $dbPass = config('database.connections.mysql.password');

            $backupName = 'db_backup_' . date('Y-m-d_His') . '.sql';
            $backupFile = "{$this->dbBackupPath}/{$backupName}";

            // Build mysqldump command - stderr to separate file to avoid polluting SQL dump
            $errorFile = $backupFile . '.err';
            $command = sprintf(
                'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --routines --triggers --add-drop-table %s > %s 2>%s',
                escapeshellarg($dbHost),
                escapeshellarg($dbPort),
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbName),
                escapeshellarg($backupFile),
                escapeshellarg($errorFile)
            );

            exec($command, $output, $returnCode);

            // Read and clean up error file
            $errorOutput = File::exists($errorFile) ? trim(File::get($errorFile)) : '';
            if (File::exists($errorFile)) {
                File::delete($errorFile);
            }

            if ($returnCode !== 0) {
                // Clean up failed file
                if (File::exists($backupFile)) {
                    File::delete($backupFile);
                }
                throw new \Exception('mysqldump gagal: ' . $errorOutput);
            }

            // Verify file was created and has content
            if (!File::exists($backupFile) || File::size($backupFile) === 0) {
                if (File::exists($backupFile)) {
                    File::delete($backupFile);
                }
                throw new \Exception('File backup kosong atau tidak terbuat');
            }

            // Compress to .gz
            $gzFile = $backupFile . '.gz';
            $fp = gzopen($gzFile, 'w9');
            $sqlContent = File::get($backupFile);
            gzwrite($fp, $sqlContent);
            gzclose($fp);

            // Remove uncompressed file
            File::delete($backupFile);

            $finalName = $backupName . '.gz';

            Setting::setValue('system', 'last_db_backup', [
                'file' => $finalName,
                'path' => $gzFile,
                'created_at' => now()->toDateTimeString(),
                'size' => File::size($gzFile),
            ], 'array');

            return [
                'success' => true,
                'backup_file' => $finalName,
                'backup_size' => $this->formatBytes(File::size($gzFile)),
            ];
        } catch (\Exception $e) {
            Log::error('Database backup failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Restore database from backup file
     */
    public function restoreDatabaseBackup(string $backupFile): array
    {
        try {
            $backupPath = "{$this->dbBackupPath}/{$backupFile}";

            if (!File::exists($backupPath)) {
                throw new \Exception('File backup database tidak ditemukan');
            }

            $dbHost = config('database.connections.mysql.host', '127.0.0.1');
            $dbPort = config('database.connections.mysql.port', '3306');
            $dbName = config('database.connections.mysql.database');
            $dbUser = config('database.connections.mysql.username');
            $dbPass = config('database.connections.mysql.password');

            // Decompress if .gz
            if (str_ends_with($backupFile, '.gz')) {
                $sqlFile = "{$this->tempPath}/" . str_replace('.gz', '', $backupFile);

                if (!File::isDirectory($this->tempPath)) {
                    File::makeDirectory($this->tempPath, 0755, true);
                }

                $fp = gzopen($backupPath, 'rb');
                $out = fopen($sqlFile, 'wb');
                while (!gzeof($fp)) {
                    fwrite($out, gzread($fp, 4096));
                }
                gzclose($fp);
                fclose($out);
            } else {
                $sqlFile = $backupPath;
            }

            // Put in maintenance mode
            Artisan::call('down', ['--secret' => 'restore-in-progress']);

            // Clean SQL file: remove any warning lines that may have been included
            $sqlContent = File::get($sqlFile);
            $cleanedContent = preg_replace('/^(mysqldump|mysql):.*$/m', '', $sqlContent);
            $cleanedContent = ltrim($cleanedContent);
            if ($cleanedContent !== $sqlContent) {
                File::put($sqlFile, $cleanedContent);
            }

            // Build mysql import command - stderr to separate file
            $errorFile = $sqlFile . '.err';
            $command = sprintf(
                'mysql --host=%s --port=%s --user=%s --password=%s %s < %s 2>%s',
                escapeshellarg($dbHost),
                escapeshellarg($dbPort),
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbName),
                escapeshellarg($sqlFile),
                escapeshellarg($errorFile)
            );

            exec($command, $output, $returnCode);

            // Read and clean up error file
            $errorOutput = File::exists($errorFile) ? trim(File::get($errorFile)) : '';
            if (File::exists($errorFile)) {
                File::delete($errorFile);
            }

            // Clean up temp sql file
            if (str_ends_with($backupFile, '.gz') && File::exists($sqlFile)) {
                File::delete($sqlFile);
            }

            // Filter out warnings from error output, only fail on real errors
            $realErrors = collect(explode("\n", $errorOutput))
                ->filter(fn($line) => !empty(trim($line)) && !str_contains($line, '[Warning]'))
                ->implode("\n");

            if ($returnCode !== 0 && !empty(trim($realErrors))) {
                Artisan::call('up');
                throw new \Exception('Restore gagal: ' . $realErrors);
            }

            // Run migrations in case backup is older
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('cache:clear');

            // Bring back up
            Artisan::call('up');

            return [
                'success' => true,
                'message' => 'Database berhasil di-restore dari ' . $backupFile,
            ];
        } catch (\Exception $e) {
            Artisan::call('up');
            Log::error('Database restore failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get list of database backups
     */
    public function getDatabaseBackups(): array
    {
        $backups = [];

        if (File::isDirectory($this->dbBackupPath)) {
            $files = File::files($this->dbBackupPath);

            foreach ($files as $file) {
                $ext = $file->getExtension();
                if (in_array($ext, ['sql', 'gz'])) {
                    $backups[] = [
                        'name' => $file->getFilename(),
                        'size' => $this->formatBytes($file->getSize()),
                        'created_at' => date('Y-m-d H:i:s', $file->getMTime()),
                    ];
                }
            }

            usort($backups, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
        }

        return $backups;
    }

    /**
     * Delete a database backup file
     */
    public function deleteDatabaseBackup(string $backupFile): bool
    {
        $path = "{$this->dbBackupPath}/{$backupFile}";

        if (File::exists($path)) {
            return File::delete($path);
        }

        return false;
    }

    /**
     * Add directory to zip archive recursively
     */
    protected function addDirectoryToZip(ZipArchive $zip, string $path, string $relativePath): void
    {
        $files = File::allFiles($path);

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $zipPath = $relativePath . '/' . $file->getRelativePathname();
            $zip->addFile($filePath, $zipPath);
        }
    }

    /**
     * Copy directory recursively
     */
    protected function copyDirectory(string $source, string $destination): void
    {
        if (!File::isDirectory($source)) {
            return;
        }

        if (!File::isDirectory($destination)) {
            File::makeDirectory($destination, 0755, true);
        }

        $items = File::allFiles($source);

        foreach ($items as $item) {
            $target = $destination . '/' . $item->getRelativePathname();
            $targetDir = dirname($target);

            if (!File::isDirectory($targetDir)) {
                File::makeDirectory($targetDir, 0755, true);
            }

            File::copy($item->getRealPath(), $target);
        }
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
