<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Spatie backups: database-only, every 6 hours, keep retention via config.
        $spatieBackupRun = $schedule
            ->command('backup:run --only-db --disable-notifications')
            ->cron('0 */6 * * *')
            ->withoutOverlapping();

        $spatieBackupRun
            ->timezone('Africa/Nairobi')
            ->sendOutputTo(storage_path('logs/spatie-backup.log'), true);

        $spatieCleanup = $schedule
            ->command('backup:clean --disable-notifications')
            ->cron('0 */6 * * *')
            ->withoutOverlapping();

        $spatieCleanup
            ->timezone('Africa/Nairobi')
            ->sendOutputTo(storage_path('logs/spatie-backup.log'), true);

        // Dynamic backup schedule based on database settings
        try {
            $backupSettings = \App\Models\BackupSetting::getSettings();
            
            if ($backupSettings->auto_backup_enabled) {
                $backupCommand = $schedule->command('app:run-backup')->withoutOverlapping();
                // Evaluate scheduled times in Kenya (East Africa)
                $backupCommand->timezone('Africa/Nairobi');

                // For hourly/everyThreeHours we allow the user to pick a minute (HH:MM).
                // Laravel's `hourly()` and `everyThreeHours()` default to minute 0, so we use cron
                // when schedule_time is provided.
                $minute = null;
                if (!empty($backupSettings->schedule_time)) {
                    $parts = explode(':', $backupSettings->schedule_time);
                    $minute = isset($parts[1]) ? (int) $parts[1] : null;
                }
                
                switch ($backupSettings->schedule_frequency) {
                    case 'every5Minutes':
                        $backupCommand->cron('*/5 * * * *');
                        break;
                    case 'every10Minutes':
                        $backupCommand->cron('*/10 * * * *');
                        break;
                    case 'every20Minutes':
                        $backupCommand->cron('*/20 * * * *');
                        break;
                    case 'every30Minutes':
                        $backupCommand->cron('*/30 * * * *');
                        break;
                    case 'hourly':
                        $m = $minute ?? 0;
                        $backupCommand->cron($m . ' * * * *'); // runs every hour at minute m
                        break;
                    case 'everyThreeHours':
                        $m = $minute ?? 0;
                        $backupCommand->cron($m . ' */3 * * *'); // runs every 3 hours at minute m
                        break;
                    case 'everyXHours':
                        $m = $minute ?? 0;
                        $intervalHours = (int) ($backupSettings->schedule_interval_hours ?? 1);
                        if ($intervalHours < 1) {
                            $intervalHours = 1;
                        }
                        if ($intervalHours > 24) {
                            $intervalHours = 24;
                        }
                        $backupCommand->cron($m . ' */' . $intervalHours . ' * * *'); // runs every X hours at minute m
                        break;
                    case 'daily':
                        if ($backupSettings->schedule_time) {
                            $backupCommand->dailyAt($backupSettings->schedule_time);
                        } else {
                            $backupCommand->daily();
                        }
                        break;
                    case 'weekly':
                        if ($backupSettings->schedule_day && $backupSettings->schedule_time) {
                            $day = $backupSettings->schedule_day;
                            $backupCommand->weeklyOn($day, $backupSettings->schedule_time);
                        } else if ($backupSettings->schedule_day) {
                            $backupCommand->weeklyOn($backupSettings->schedule_day);
                        } else {
                            $backupCommand->weekly();
                        }
                        break;
                    default:
                        $backupCommand->everyThreeHours();
                }
            }
        } catch (\Exception $e) {
            // Fallback to default schedule if settings can't be loaded
            \Log::warning('Failed to load backup settings, using default schedule', ['error' => $e->getMessage()]);
            $schedule->command('app:run-backup')->everyThreeHours()->withoutOverlapping();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

}
