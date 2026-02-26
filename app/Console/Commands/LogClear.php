<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class LogClear extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'log:clear {--days=30 : Delete log files older than N days} {--all : Truncate all log files}';

    /**
     * The console command description.
     */
    protected $description = 'Clear old log files from storage/logs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $logPath = storage_path('logs');

        if (!is_dir($logPath)) {
            $this->info('Log directory does not exist.');
            return self::SUCCESS;
        }

        if ($this->option('all')) {
            return $this->truncateAll($logPath);
        }

        return $this->deleteOldLogs($logPath, (int) $this->option('days'));
    }

    /**
     * Truncate all log files.
     */
    protected function truncateAll(string $logPath): int
    {
        $files = glob($logPath . '/*.log');
        $count = 0;

        foreach ($files as $file) {
            file_put_contents($file, '');
            $count++;
        }

        $this->info("Truncated {$count} log file(s).");

        return self::SUCCESS;
    }

    /**
     * Delete log files older than N days, truncate active laravel.log.
     */
    protected function deleteOldLogs(string $logPath, int $days): int
    {
        $files = glob($logPath . '/*.log');
        $cutoff = now()->subDays($days)->timestamp;
        $deleted = 0;

        foreach ($files as $file) {
            $basename = basename($file);

            // Truncate active laravel.log instead of deleting
            if ($basename === 'laravel.log') {
                if (filemtime($file) < $cutoff) {
                    file_put_contents($file, '');
                    $this->line("Truncated: {$basename}");
                }
                continue;
            }

            if (filemtime($file) < $cutoff) {
                unlink($file);
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} old log file(s) (older than {$days} days).");

        return self::SUCCESS;
    }
}
