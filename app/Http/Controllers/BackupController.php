<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use App\Models\BackupSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BackupController extends Controller
{
    protected DatabaseBackupService $backupService;

    public function __construct(DatabaseBackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    public function index()
    {
        $backups = $this->backupService->listBackups();
        $settings = BackupSetting::getSettings();
        return view('backup.index', compact('backups', 'settings'));
    }

    public function run(Request $request)
    {
        try {
            $customPath = $request->input('backup_path');
            
            // Validate custom path if provided
            if ($customPath) {
                $validator = Validator::make($request->all(), [
                    'backup_path' => 'required|string|max:500',
                ]);

                if ($validator->fails()) {
                    return back()->withErrors($validator)->withInput();
                }

                // Normalize path separators for Windows
                $customPath = str_replace('/', DIRECTORY_SEPARATOR, $customPath);
                $customPath = str_replace('\\', DIRECTORY_SEPARATOR, $customPath);
            }

            $result = $this->backupService->createBackup('manual', $customPath);
            
            // If custom path was used, return success message instead of download
            if ($customPath) {
                return back()->with('success', 'Backup created successfully at: ' . $result['path']);
            }
            
            return response()->download($result['path'], $result['filename']);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function download($filename)
    {
        $path = "backups/{$filename}";
        if (!Storage::disk('local')->exists($path)) {
            return back()->withErrors(['error' => 'Backup file not found.']);
        }

        return response()->download(Storage::disk('local')->path($path), $filename);
    }

    public function updateSettings(Request $request)
    {
        // Convert empty strings to null for optional fields (HTML forms submit "" not null)
        $data = $request->all();
        if (isset($data['schedule_time']) && $data['schedule_time'] === '') {
            $data['schedule_time'] = null;
        }
        if (isset($data['schedule_day']) && $data['schedule_day'] === '') {
            $data['schedule_day'] = null;
        }
        if (isset($data['schedule_interval_hours']) && $data['schedule_interval_hours'] === '') {
            $data['schedule_interval_hours'] = null;
        }
        if (isset($data['default_backup_path']) && trim($data['default_backup_path']) === '') {
            $data['default_backup_path'] = null;
        }
        if (empty($data['keep_backups_days']) || !is_numeric($data['keep_backups_days'])) {
            $data['keep_backups_days'] = 30;
        }

        $validator = Validator::make($data, [
            'auto_backup_enabled' => ['nullable', 'boolean'],
            'schedule_frequency' => ['required', 'string', 'in:every5Minutes,every10Minutes,every20Minutes,every30Minutes,hourly,daily,weekly,everyThreeHours,everyXHours'],
            'schedule_time' => ['nullable', 'string', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            'schedule_day' => ['nullable', 'string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'schedule_interval_hours' => ['required_if:schedule_frequency,everyXHours', 'nullable', 'integer', 'min:1', 'max:24'],
            'default_backup_path' => ['nullable', 'string', 'max:500'],
            'keep_backups_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $settings = BackupSetting::getSettings();
            
            $settings->auto_backup_enabled = $request->has('auto_backup_enabled');
            $settings->schedule_frequency = $data['schedule_frequency'];
            $settings->schedule_time = $data['schedule_time'] ?? null;
            $settings->schedule_day = $data['schedule_day'] ?? null;
            $settings->schedule_interval_hours = $data['schedule_interval_hours'] ?? null;
            $settings->keep_backups_days = (int) $data['keep_backups_days'];
            
            // Normalize default backup path if provided
            if (!empty($data['default_backup_path'])) {
                $defaultPath = str_replace('/', DIRECTORY_SEPARATOR, $data['default_backup_path']);
                $defaultPath = str_replace('\\', DIRECTORY_SEPARATOR, $defaultPath);
                $settings->default_backup_path = trim($defaultPath) ?: null;
            } else {
                $settings->default_backup_path = null;
            }
            
            $settings->save();

            return back()->with('success', 'Backup settings updated successfully.');
        } catch (\Throwable $e) {
            \Log::error('Backup settings save failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withErrors(['error' => 'Failed to update settings: ' . $e->getMessage()])->withInput();
        }
    }
}










