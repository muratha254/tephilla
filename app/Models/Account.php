<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'balance',
        'description',
        'is_active'
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    /**
     * Add amount to account balance
     */
    public function addAmount($amount)
    {
        $this->balance = $this->balance + $amount;
        $this->save();
    }

    /**
     * Deduct amount from account balance
     */
    public function deductAmount($amount)
    {
        $this->balance = $this->balance - $amount;
        $this->save();
    }

    /**
     * Get account by name
     */
    public static function getByName($name)
    {
        return self::where('name', $name)->where('is_active', true)->first();
    }
}
