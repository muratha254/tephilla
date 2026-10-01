<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class FoldingOutput extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'folding_id',
        'company_id',
        'product_id',
        'product_variant_id',
        'colour_id',
        'unit_id',
        'quantity',
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'product_variant_id' => 'integer',
    ];

    public function folding()
    {
        return $this->belongsTo(Folding::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function colour()
    {
        return $this->belongsTo(Colour::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
