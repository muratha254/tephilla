<?php

return [
    'backup' => [
        /*
         * The name of this application. You can use this name to monitor
         * the backups.
         *
         * IMPORTANT: Spatie stores backups in a folder based on this name
         * under the configured disk root. Setting it to `backups` ensures
         * files land in: storage/app/backups/.
         */
        'name' => env('BACKUP_NAME', 'backups'),

        'source' => [
            'files' => [
                // Database-only backups: do not include application files.
                'include' => [],
                'exclude' => [],
                'follow_links' => false,
                'ignore_unreadable_directories' => false,
                'relative_path' => null,
            ],

            'databases' => [
                // Back up ONLY the MySQL connection.
                env('DB_CONNECTION', 'mysql'),
            ],
        ],

        // No extra compression configuration needed.
        'database_dump_compressor' => null,

        // Use the default .sql extension for MySQL.
        'database_dump_file_extension' => '',

        'destination' => [
            'filename_prefix' => env('BACKUP_FILENAME_PREFIX', 'database_'),
            'disks' => [
                // This maps to `storage/app` (see config/filesystems.php "local" disk).
                'local',
            ],
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        'encryption' => 'default',
    ],

    'notifications' => [
        /*
         * Disable outbound notifications. We'll rely on the scheduler output
         * and `storage/logs/spatie-backup.log` for success/failure.
         */
        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class => [],
        ],

        'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,

        // Kept for compatibility, but won't be used since notifications are disabled above.
        'mail' => [
            'to' => env('BACKUP_NOTIFICATION_MAIL_TO', 'your@example.com'),
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Example'),
            ],
        ],
        'slack' => [
            'webhook_url' => '',
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],
        'discord' => [
            'webhook_url' => '',
            'username' => null,
            'avatar_url' => null,
        ],
    ],

    'monitor_backups' => [
        [
            'name' => env('BACKUP_NAME', 'backups'),
            'disks' => ['local'],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 2,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    'cleanup' => [
        // Use our custom strategy to keep only the last 7 backups.
        'strategy' => App\Backup\Cleanup\Strategies\KeepOnlyLastNBackups::class,

        // Custom config read by our strategy.
        'keep_only_last_n_backups' => 7,

        // These keys are unused by our custom strategy, but kept to avoid missing-key issues.
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],
    ],
];

