<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoicePayment extends Model
{
    protected $fillable = [
        'invoice_id',
        'payment_id',
        'supplier_id',
        'amount_paid',
        'remaining_balance',
        'payment_reference',
        'payment_status',
        'notes',
        'payment_date'
    ];

    protected $casts = [
        'payment_date' => 'datetime',
        'amount_paid' => 'decimal:2',
        'remaining_balance' => 'decimal:2'
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
