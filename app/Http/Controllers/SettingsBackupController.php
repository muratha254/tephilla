<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\DatabaseBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsBackupController extends Controller
{
    public function index(DatabaseBackupService $backupService)
    {
        abort_unless(auth()->user()->hasPermission('settings.view') || auth()->user()->isCompanyAdmin(), 403);

        return view('settings.backup', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'settings.backup',
            'backups' => $backupService->listBackups(),
        ]));
    }

    public function run(DatabaseBackupService $backupService, AuditLogger $audit)
    {
        abort_unless(auth()->user()->hasPermission('settings.view') || auth()->user()->isCompanyAdmin(), 403);

        try {
            $result = $backupService->createBackup(
                'manual',
                null,
                auth()->user()->name ?? auth()->user()->username ?? 'Admin'
            );

            $audit->record('add_backup', 'backup', null, null, [
                'filename' => $result['filename'],
            ]);

            return redirect()->route('settings.backup')->with('success', 'Backup created: ' . $result['filename']);
        } catch (\Throwable $e) {
            return redirect()->route('settings.backup')->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function download(string $filename)
    {
        abort_unless(auth()->user()->hasPermission('settings.view') || auth()->user()->isCompanyAdmin(), 403);

        $path = 'backups/' . basename($filename);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->download(Storage::disk('local')->path($path), basename($filename));
    }

    public function destroy(string $filename, DatabaseBackupService $backupService, AuditLogger $audit)
    {
        abort_unless(auth()->user()->hasPermission('settings.view') || auth()->user()->isCompanyAdmin(), 403);

        $name = basename($filename);
        if (! $backupService->deleteBackup($name)) {
            return redirect()->route('settings.backup')->withErrors(['error' => 'Backup file not found.']);
        }

        $audit->record('delete_backup', 'backup', null, ['filename' => $name], null);

        return redirect()->route('settings.backup')->with('success', 'Backup deleted.');
    }
}
