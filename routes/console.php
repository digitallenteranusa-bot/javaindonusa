<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Schedule Tasks - ISP Billing System
|--------------------------------------------------------------------------
|
| Jadwal task otomatis untuk billing ISP.
| Pastikan cron sudah dikonfigurasi:
| * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
|
*/

// ==========================================================================
// BILLING TASKS
// ==========================================================================

// Generate invoices on 1st of each month at 00:01
Schedule::command('billing:generate-invoices')
    ->monthlyOn(1, '00:01')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// Check overdue invoices daily at 06:00
Schedule::command('billing:check-overdue')
    ->dailyAt('06:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// Process isolation for overdue customers daily at 06:30
Schedule::command('billing:process-isolation')
    ->dailyAt('06:30')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// Send payment reminders daily at 09:00
Schedule::command('billing:send-reminders')
    ->dailyAt('09:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// Send overdue notices daily at 10:00
Schedule::command('billing:send-overdue')
    ->dailyAt('10:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// ==========================================================================
// GENIEACS TASKS (TR-069)
// ==========================================================================

// Sync GenieACS devices every 15 minutes
Schedule::command('genieacs:sync-devices')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->when(function () {
        return config('genieacs.enabled', false);
    });

// ==========================================================================
// MAINTENANCE TASKS
// ==========================================================================

// Clean old logs weekly on Sunday at 01:00
Schedule::command('log:clear')
    ->weeklyOn(0, '01:00')
    ->timezone('Asia/Jakarta');

// Clean old backup files weekly
Schedule::call(function () {
    $backupPath = storage_path('backups');
    if (is_dir($backupPath)) {
        $files = glob($backupPath . '/*.zip');
        $now = time();
        foreach ($files as $file) {
            // Delete backups older than 30 days
            if ($now - filemtime($file) > 30 * 24 * 60 * 60) {
                unlink($file);
            }
        }
    }
})->weeklyOn(0, '02:00')->timezone('Asia/Jakarta');
