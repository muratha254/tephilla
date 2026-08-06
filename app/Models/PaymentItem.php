<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentItem extends Model
{
    use HasFactory;

    protected $fillable=[
        'payment_id', 
        'narrative', 
        'amount', 
        'user_id',
        'invoice_id'
    ];

    public function invoice(){
        return $this->hasOne('App\Models\Invoice', 'id', 'invoice_id');
    }

    public function invoiceItem(){
        return $this->belongsTo('App\Models\InvoiceItem', 'invoice_id', 'invoice_id');
    }

    public function payment(){
        return $this->belongsTo('App\Models\Payment', 'payment_id', 'id');
    }
}
