<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierWithdrawal extends Model
{
    use HasFactory;

    protected $table = 'supplier_withdrawals';
    protected $guarded = [];
    
    protected $fillable = [
        'withdrawal_number',
        'receipt_no',
        'supplier_id',
        'produk_id',
        'quantity',
        'unit_price',
        'total_amount',
        'reason',
        'notes',
        'user_id',
        'withdrawal_date',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'withdrawal_date' => 'date',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id_supplier');
    }

    public function produk()
    {
        return $this->belongsTo(Produk::class, 'produk_id', 'id_produk');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id');
    }

    public static function generateWithdrawalNumber()
    {
        $prefix = 'WD';
        $date = now()->format('Ymd');
        $lastWithdrawal = self::where('withdrawal_number', 'like', $prefix . $date . '%')
            ->orderBy('withdrawal_number', 'desc')
            ->first();
        
        if ($lastWithdrawal) {
            $lastNumber = intval(substr($lastWithdrawal->withdrawal_number, -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . $date . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}
