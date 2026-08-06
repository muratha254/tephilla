<?php

namespace App\Backup\Cleanup\Strategies;

use Spatie\Backup\BackupDestination\BackupCollection;
use Spatie\Backup\Tasks\Cleanup\CleanupStrategy;

class KeepOnlyLastNBackups extends CleanupStrategy
{
    /**
     * Keep only the newest N backups, delete the rest.
     *
     * @param  BackupCollection  $backups  Sorted newest-first.
     */
    public function deleteOldBackups(BackupCollection $backups): void
    {
        $keepOnlyLastN = (int) $this->config->get('backup.cleanup.keep_only_last_n_backups', 7);

        if ($keepOnlyLastN < 1) {
            $keepOnlyLastN = 1;
        }

        // Keep newest first N; delete the rest.
        $backupsToDelete = $backups->slice($keepOnlyLastN);

        $backupsToDelete->each(static function ($backup): void {
            $backup->delete();
        });
    }
}

