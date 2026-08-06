<?php

namespace App\Services;

use App\Models\BackupSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupRunnerService
{
    protected DatabaseBackupService $backupService;

    public function __construct(DatabaseBackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    /**
     * Run a database backup using Backup settings (path, retention).
     *
     * @param  string  $tag  Filename prefix e.g. 'auto', 'daily_open'
     * @param  bool  $requireAutoBackupEnabled  If true, skip when auto backup is disabled in settings
     * @return array{filename: string, path: string}|null  Null when skipped
     */
    public function run(string $tag = 'auto', bool $requireAutoBackupEnabled = true): ?array
    {
        $settings = BackupSetting::getSettings();

        if ($requireAutoBackupEnabled && ! $settings->auto_backup_enabled) {
            return null;
        }

        $backupPath = $this->resolveBackupFilePath($settings, $tag);

        $result = $this->backupService->createBackup($tag, $backupPath);
        Log::info('[Backup] Backup created', ['tag' => $tag, 'file' => $result['filename'], 'path' => $result['path']]);

        $this->cleanupOldBackups($settings);

        return $result;
    }

    protected function resolveBackupFilePath(BackupSetting $settings, string $tag): ?string
    {
        $path = $settings->default_backup_path;
        if (! $path) {
            return null;
        }

        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $path = rtrim($path, DIRECTORY_SEPARATOR);

        if (! is_dir($path)) {
            if (! mkdir($path, 0755, true)) {
                throw new \RuntimeException('Failed to create backup directory: '.$path);
            }
        }

        $timestamp = now()->format('Ymd_His');
        $filename = sprintf('backup_%s_%s.sql', $tag, $timestamp);

        return $path.DIRECTORY_SEPARATOR.$filename;
    }

    protected function cleanupOldBackups(BackupSetting $settings): void
    {
        try {
            $keepDays = $settings->keep_backups_days ?? 30;
            $cutoffDate = Carbon::now()->subDays($keepDays);

            $files = Storage::disk('local')->files('backups');
            foreach ($files as $file) {
                $lastModified = Carbon::createFromTimestamp(Storage::disk('local')->lastModified($file));
                if ($lastModified->lt($cutoffDate)) {
                    Storage::disk('local')->delete($file);
                    Log::info('[Backup] Deleted old backup', ['file' => basename($file)]);
                }
            }

            if ($settings->default_backup_path && is_dir($settings->default_backup_path)) {
                $pattern = $settings->default_backup_path.DIRECTORY_SEPARATOR.'backup_*.sql';
                $files = glob($pattern) ?: [];
                foreach ($files as $file) {
                    if (! file_exists($file)) {
                        continue;
                    }
                    $lastModified = Carbon::createFromTimestamp(filemtime($file));
                    if ($lastModified->lt($cutoffDate)) {
                        unlink($file);
                        Log::info('[Backup] Deleted old backup', ['file' => basename($file)]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[Backup] Cleanup failed', ['error' => $e->getMessage()]);
        }
    }
}
