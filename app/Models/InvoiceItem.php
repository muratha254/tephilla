<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\Penjualan;

class InvoiceItem extends Model
{
    protected $fillable = [
        'uniqid',
        'produk_id',
        'quantity',
        'amount',
        'discount',
        'status',
        'user_id',
        'supplier_id',
        'invoice_id',
        'penjualan_id',
        'balance',
        'amount_paid'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'quantity' => 'integer',
        'supplier_id' => 'integer',
        'produk_id' => 'integer',
        'invoice_id' => 'integer',
        'penjualan_id' => 'integer',
    ];

    public function produk()
    {
        return $this->belongsTo(Produk::class, 'produk_id', 'id_produk');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'id');
    }

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class, 'penjualan_id', 'id_penjualan');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id_supplier');
    }

    public function paymentItems()
    {
        return $this->hasMany(PaymentItem::class, 'invoice_id', 'invoice_id');
    }

    public function getRealBalanceAttribute()
    {
        return $this->amount - ($this->amount_paid ?? 0);
    }
}
