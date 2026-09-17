<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Exception;
use ZipArchive;

class DatabaseBackupService
{
    /**
     * @return array{filename:string, path:string}
     */
    public function createBackup(string $tag = 'manual', ?string $customPath = null, ?string $createdBy = null): array
    {
        $connection = config('database.connections.mysql');
        if (! $connection) {
            throw new Exception('Database connection [mysql] not configured.');
        }

        $database = $connection['database'] ?? null;
        $username = $connection['username'] ?? null;
        $password = $connection['password'] ?? null;
        $host = $connection['host'] ?? '127.0.0.1';
        $port = $connection['port'] ?? '3306';

        if (! $database || ! $username) {
            throw new Exception('Database name or username not set in configuration.');
        }

        $stamp = now()->format('Y-m-d-H-i-s');
        $sqlName = 'backup-on-' . $stamp . '.sql';
        $zipName = 'backup-on-' . $stamp . '.zip';

        Storage::disk('local')->makeDirectory('backups');
        $sqlRelative = 'backups/' . $sqlName;
        $sqlFullPath = Storage::disk('local')->path($sqlRelative);

        $dumpBinary = env('MYSQLDUMP_PATH');
        if (empty($dumpBinary) && PHP_OS_FAMILY === 'Windows') {
            foreach ([
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                'C:\\laragon\\bin\\mysql\\mysql-8\\bin\\mysqldump.exe',
            ] as $path) {
                if (file_exists($path)) {
                    $dumpBinary = $path;
                    break;
                }
            }
        }
        $dumpBinary = $dumpBinary ?: 'mysqldump';

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
        $process->setTimeout(180);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new Exception('Backup failed: ' . $process->getErrorOutput());
        }

        Storage::disk('local')->put($sqlRelative, $process->getOutput());

        $filename = $sqlName;
        $fullPath = $sqlFullPath;

        if (class_exists(ZipArchive::class)) {
            $zipRelative = 'backups/' . $zipName;
            $zipFullPath = Storage::disk('local')->path($zipRelative);
            $zip = new ZipArchive();
            if ($zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $zip->addFile($sqlFullPath, $sqlName);
                $zip->close();
                Storage::disk('local')->delete($sqlRelative);
                $filename = $zipName;
                $fullPath = $zipFullPath;
            }
        }

        if ($customPath) {
            $directory = is_dir($customPath) ? $customPath : dirname($customPath);
            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new Exception('Failed to create backup directory: ' . $directory);
            }
            $dest = is_dir($customPath)
                ? rtrim($customPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename
                : $customPath;
            copy($fullPath, $dest);
        }

        $this->writeMeta($filename, [
            'created_by' => $createdBy ?: (auth()->user()->name ?? auth()->user()->username ?? 'System'),
            'created_at' => now()->toDateTimeString(),
            'tag' => $tag,
        ]);

        return [
            'filename' => $filename,
            'path' => $fullPath,
        ];
    }

    /**
     * @return array<int, array{filename:string, path:string, size:int, datetime:\DateTimeInterface, created_by:string}>
     */
    public function listBackups(): array
    {
        $files = Storage::disk('local')->files('backups');
        $backups = [];

        foreach ($files as $file) {
            $name = basename($file);
            if (str_ends_with($name, '.meta.json')) {
                continue;
            }

            $meta = $this->readMeta($name);
            $backups[] = [
                'filename' => $name,
                'path' => Storage::disk('local')->path($file),
                'size' => Storage::disk('local')->size($file),
                'datetime' => \Carbon\Carbon::createFromTimestamp(Storage::disk('local')->lastModified($file)),
                'created_by' => $meta['created_by'] ?? 'System',
            ];
        }

        usort($backups, fn ($a, $b) => $b['datetime']->timestamp <=> $a['datetime']->timestamp);

        return $backups;
    }

    public function deleteBackup(string $filename): bool
    {
        $filename = basename($filename);
        $path = 'backups/' . $filename;
        if (! Storage::disk('local')->exists($path)) {
            return false;
        }

        Storage::disk('local')->delete($path);
        Storage::disk('local')->delete('backups/' . $filename . '.meta.json');

        return true;
    }

    private function writeMeta(string $filename, array $meta): void
    {
        Storage::disk('local')->put(
            'backups/' . $filename . '.meta.json',
            json_encode($meta, JSON_PRETTY_PRINT)
        );
    }

    private function readMeta(string $filename): array
    {
        $path = 'backups/' . $filename . '.meta.json';
        if (! Storage::disk('local')->exists($path)) {
            return [];
        }

        $decoded = json_decode(Storage::disk('local')->get($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
