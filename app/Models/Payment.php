<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Supplier;
use App\Models\PaymentItem;

class Payment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'type',
        'amount',
        'date',
        'payment_date',
        'uniqid',
        'supplier_id',
        'reference_number',
        'payment_method',
        'notes'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'supplier_id' => 'integer',
        'date' => 'date',
        'payment_date' => 'date'
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id_supplier');
    }

    public function paymentItems()
    {
        return $this->hasMany(PaymentItem::class);
    }
}
