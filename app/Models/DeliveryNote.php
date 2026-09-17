<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class DeliveryNote extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'sale_id', 'invoice_number', 'invoice_date', 'delivery_date',
        'customer_name', 'delivery_address', 'delivered_by', 'received_by',
        'notes', 'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'delivery_date' => 'date',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function items()
    {
        return $this->hasMany(DeliveryNoteItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
