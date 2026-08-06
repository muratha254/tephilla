<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BackupSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'auto_backup_enabled',
        'schedule_frequency',
        'schedule_time',
        'schedule_day',
        'schedule_interval_hours',
        'default_backup_path',
        'keep_backups_days',
    ];

    protected $casts = [
        'auto_backup_enabled' => 'boolean',
        'keep_backups_days' => 'integer',
    ];

    /**
     * Get the backup settings (singleton pattern)
     */
    public static function getSettings()
    {
        return static::first() ?? static::create([
            'auto_backup_enabled' => true,
            'schedule_frequency' => 'everyThreeHours',
            'schedule_time' => null,
            'schedule_day' => null,
            'schedule_interval_hours' => null,
            'default_backup_path' => null,
            'keep_backups_days' => 30,
        ]);
    }
}
