<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class DailyCash extends Model
{
    use HasFactory;

    protected $table = 'daily_cash';

    protected $fillable = [
        'date',
        'opening_cash',
        'total_sales',
        'net_sales',
        'is_opened',
        'is_closed',
        'opened_by',
        'closed_by',
        'opened_at',
        'closed_at',
        'notes'
    ];

    protected $casts = [
        'date' => 'date',
        'opening_cash' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'net_sales' => 'decimal:2',
        'is_opened' => 'boolean',
        'is_closed' => 'boolean',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime'
    ];

    /**
     * Get today's cash record
     */
    public static function getToday()
    {
        return self::whereDate('date', Carbon::today())->first();
    }

    /**
     * Check if today is opened
     */
    public static function isTodayOpened()
    {
        $today = self::getToday();
        return $today && $today->is_opened;
    }

    /**
     * Check if today is closed
     */
    public static function isTodayClosed()
    {
        $today = self::getToday();
        return $today && $today->is_closed;
    }

    /**
     * Get opened by user
     */
    public function openedByUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'opened_by', 'id');
    }

    /**
     * Get closed by user
     */
    public function closedByUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'closed_by', 'id');
    }
}
