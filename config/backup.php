<?php

use Spatie\DbDumper\Compressors\GzipCompressor;

return [

    'backup' => [
        'name' => 'isp-billing-backup',

        'source' => [
            'files' => [
                'include' => [
                    base_path('.env'),
                    config_path(),
                    database_path('migrations'),
                    database_path('seeders'),
                    resource_path('views'),
                ],

                'exclude' => [],

                'follow_links' => false,
                'ignore_unreadable_directories' => true,
                'relative_path' => base_path(),
            ],

            'databases' => [
                'mysql',
            ],
        ],

        'database_dump_compressor' => GzipCompressor::class,

        'database_dump_file_timestamp_format' => 'Y-m-d-H-i-s',

        'database_dump_filename_base' => 'database',
        'database_dump_file_extension' => '',

        'destination' => [
            'compression_method' => ZipArchive::CM_DEFAULT,
            'compression_level' => 9,
            'filename_prefix' => 'backup-',

            'disks' => array_filter([
                'local',
                env('GOOGLE_DRIVE_FOLDER_ID') ? 'google' : null,
            ]),
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        'password' => env('BACKUP_ARCHIVE_PASSWORD'),
        'encryption' => 'default',

        'tries' => 3,
        'retry_delay' => 5,
    ],

    'notifications' => [
        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => env('BACKUP_NOTIFICATION_EMAIL') ? ['mail'] : [],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => env('BACKUP_NOTIFICATION_EMAIL') ? ['mail'] : [],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => env('BACKUP_NOTIFICATION_EMAIL') ? ['mail'] : [],
            \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class => env('BACKUP_NOTIFICATION_EMAIL') ? ['mail'] : [],
            \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class => [],
        ],

        'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,

        'mail' => [
            'to' => env('BACKUP_NOTIFICATION_EMAIL', 'admin@javaindonusa.com'),

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'noreply@javaindonusa.com'),
                'name' => env('MAIL_FROM_NAME', 'ISP Billing Backup'),
            ],
        ],

        'slack' => [
            'webhook_url' => env('BACKUP_SLACK_WEBHOOK', ''),
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',
            'username' => '',
            'avatar_url' => '',
        ],
    ],

    'monitor_backups' => [
        [
            'name' => 'isp-billing-backup',
            'disks' => ['local'],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    'cleanup' => [
        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,

        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 30,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 6,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],

        'tries' => 1,
        'retry_delay' => 0,
    ],

];
