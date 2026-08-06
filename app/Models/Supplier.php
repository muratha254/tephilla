<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $table = 'supplier';
    protected $primaryKey = 'id_supplier';
    protected $guarded = [];
    
    protected $fillable = [
        'nama',
        'alamat',
        'telepon',
        'mop',
        'opening_balance',
        'opening_balance_paid'
    ];
    
    protected $casts = [
        'opening_balance' => 'decimal:2',
        'opening_balance_paid' => 'decimal:2',
    ];
}
