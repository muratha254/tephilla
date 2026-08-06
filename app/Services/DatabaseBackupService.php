<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Exception;

class DatabaseBackupService
{
    /**
     * Create a database backup (schema + data) and store it in the specified location.
     *
     * @param  string  $tag  Optional tag to include in filename (e.g. 'manual' or 'auto')
     * @param  string|null  $customPath  Optional custom path to save the backup file
     * @return array{filename:string, path:string}
     *
     * @throws \Exception
     */
    public function createBackup(string $tag = 'manual', ?string $customPath = null): array
    {
        $connection = config('database.connections.mysql');
        if (!$connection) {
            throw new Exception('Database connection [mysql] not configured.');
        }

        $database = $connection['database'] ?? null;
        $username = $connection['username'] ?? null;
        $password = $connection['password'] ?? null;
        $host = $connection['host'] ?? '127.0.0.1';
        $port = $connection['port'] ?? '3306';

        if (!$database || !$username) {
            throw new Exception('Database name or username not set in configuration.');
        }

        $timestamp = now()->format('Ymd_His');
        $filename = sprintf('backup_%s_%s.sql', $tag, $timestamp);

        // If custom path is provided, save directly to that location
        if ($customPath) {
            // Ensure the directory exists
            $directory = dirname($customPath);
            if (!is_dir($directory)) {
                if (!mkdir($directory, 0755, true)) {
                    throw new Exception('Failed to create backup directory: ' . $directory);
                }
            }

            // Use the custom path directly
            $fullPath = $customPath;
            if (!str_ends_with($fullPath, '.sql')) {
                $fullPath = rtrim($fullPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
            } else {
                // If path ends with .sql, use it as the full filename
                $fullPath = $customPath;
                $filename = basename($fullPath);
            }
        } else {
            // Default: save to storage/backups
            $relativePath = "backups/{$filename}";
            Storage::disk('local')->makeDirectory('backups');
            $fullPath = Storage::disk('local')->path($relativePath);
        }

        // Allow overriding mysqldump binary via env to support Windows (e.g. C:\xampp\mysql\bin\mysqldump.exe)
        $dumpBinary = env('MYSQLDUMP_PATH');
        if (empty($dumpBinary)) {
            // Auto-detect XAMPP mysqldump on Windows
            if (PHP_OS_FAMILY === 'Windows') {
                $xamppPaths = [
                    'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                    'C:\\laragon\\bin\\mysql\\mysql-8\\bin\\mysqldump.exe',
                ];
                foreach ($xamppPaths as $path) {
                    if (file_exists($path)) {
                        $dumpBinary = $path;
                        break;
                    }
                }
            }
        }
        $dumpBinary = $dumpBinary ?: 'mysqldump';

        // Build mysqldump process (captures output, then we save to file)
        $process = new Process([
            $dumpBinary,
            '--user=' . $username,
            '--password=' . $password,
            '--host=' . $host,
            '--port=' . $port,
            '--skip-comments',
            '--single-transaction',
            '--routines',
            '--triggers',
            $database,
        ]);

        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new Exception('Backup failed: ' . $process->getErrorOutput());
        }

        $dump = $process->getOutput();
        
        // Write to file
        if ($customPath) {
            file_put_contents($fullPath, $dump);
        } else {
            Storage::disk('local')->put($relativePath, $dump);
        }

        return [
            'filename' => $filename,
            'path' => $fullPath,
        ];
    }

    /**
     * Get list of existing backups sorted by newest first.
     *
     * @return array<int, array{filename:string, path:string, size:int, datetime:\DateTimeInterface}>
     */
    public function listBackups(): array
    {
        $files = Storage::disk('local')->files('backups');
        $backups = [];

        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'path' => Storage::disk('local')->path($file),
                'size' => Storage::disk('local')->size($file),
                'datetime' => \Carbon\Carbon::createFromTimestamp(Storage::disk('local')->lastModified($file)),
            ];
        }

        // Newest first
        usort($backups, function ($a, $b) {
            return $b['datetime']->timestamp <=> $a['datetime']->timestamp;
        });

        return $backups;
    }
}

