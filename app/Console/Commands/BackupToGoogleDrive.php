<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackupToGoogleDrive extends Command
{
    protected $signature = 'backup:google-drive';
    protected $description = 'Upload latest backup to Google Drive';

    public function handle(): int
    {
        if (!config('filesystems.disks.google.folder')) {
            $this->warn('Google Drive not configured. Set GOOGLE_DRIVE_FOLDER_ID in .env');
            return self::SUCCESS;
        }

        $backupName = config('backup.backup.name');
        $localDisk = Storage::disk('local');

        // Find latest backup zip in the backup folder
        $files = $localDisk->files($backupName);
        $backupFiles = collect($files)->filter(fn($f) => str_ends_with($f, '.zip'))->sort()->values();

        if ($backupFiles->isEmpty()) {
            $this->error('No backup files found in: ' . $backupName);
            return self::FAILURE;
        }

        $latestBackup = $backupFiles->last();
        $filename = basename($latestBackup);

        $this->info("Uploading: {$filename}");

        try {
            $googleDisk = Storage::disk('google');

            // Upload the file
            $stream = $localDisk->readStream($latestBackup);
            $googleDisk->writeStream($filename, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            $this->info("Uploaded to Google Drive: {$filename}");

            // Clean old backups on Google Drive (keep last 7)
            $driveFiles = collect($googleDisk->files('/'))
                ->filter(fn($f) => str_ends_with($f, '.zip'))
                ->sort()
                ->values();

            if ($driveFiles->count() > 7) {
                $toDelete = $driveFiles->slice(0, $driveFiles->count() - 7);
                foreach ($toDelete as $old) {
                    $googleDisk->delete($old);
                    $this->line("Deleted old: " . basename($old));
                }
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Upload failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
