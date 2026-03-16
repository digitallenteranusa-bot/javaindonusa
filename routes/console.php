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
// MIKROTIK TASKS
// ==========================================================================

// Sync Mikrotik router status every 5 minutes
Schedule::command('mikrotik:status')
    ->everyFiveMinutes()
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

// Database backup daily at 02:00 (only DB, no files)
Schedule::command('backup:run --only-db')
    ->dailyAt('02:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/backup.log'));

// Full backup (DB + config files) weekly on Sunday at 03:00
Schedule::command('backup:run')
    ->weeklyOn(0, '03:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/backup.log'));

// Cleanup old backups weekly on Sunday at 04:00
Schedule::command('backup:clean')
    ->weeklyOn(0, '04:00')
    ->timezone('Asia/Jakarta')
    ->appendOutputTo(storage_path('logs/backup.log'));

// Monitor backup health daily at 08:00
Schedule::command('backup:monitor')
    ->dailyAt('08:00')
    ->timezone('Asia/Jakarta');

// Sync latest backup to Google Drive daily at 02:30 (after DB backup)
Schedule::command('backup:google-drive')
    ->dailyAt('02:30')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/backup.log'));
