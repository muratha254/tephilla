<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\PurchaseOrderReceipt;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'produk_id',
        'quantity',
        'received_quantity',
        'price',
        'order_date',
        'status',
    ];

    protected $casts = [
        'order_date' => 'date',
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'received_quantity' => 'integer',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id_supplier');
    }

    public function product()
    {
        return $this->belongsTo(Produk::class, 'produk_id', 'id_produk');
    }

    public function receipts()
    {
        return $this->hasMany(PurchaseOrderReceipt::class);
    }

    public function getRemainingQuantityAttribute(): int
    {
        $remaining = ($this->quantity ?? 0) - ($this->received_quantity ?? 0);

        return max(0, $remaining);
    }

    public static function generateNumber(): string
    {
        do {
            $sequence = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $number = sprintf('PO-%s-%s', now()->format('Ymd'), $sequence);
        } while (self::where('po_number', $number)->exists());

        return $number;
    }
}







