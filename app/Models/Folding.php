<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Folding extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;

    protected $fillable = [
        'company_id',
        'branch_id',
        'product_id',
        'product_variant_id',
        'colour_id',
        'unit_id',
        'user_id',
        'employee_name',
        'folded_on',
        'quantity',
        'notes',
        'status',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected $casts = [
        'folded_on' => 'date',
        'voided_at' => 'datetime',
        'quantity' => 'decimal:4',
        'product_variant_id' => 'integer',
    ];

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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function outputs()
    {
        return $this->hasMany(FoldingOutput::class);
    }

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function number(): string
    {
        return 'FLD-' . $this->id;
    }
}
