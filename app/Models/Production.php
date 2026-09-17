<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Production extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'bom_id', 'user_id', 'title', 'production_date',
        'description', 'expected_production', 'actual_production', 'production_cost',
    ];

    protected $casts = [
        'production_date' => 'date',
        'expected_production' => 'decimal:4',
        'actual_production' => 'decimal:4',
        'production_cost' => 'decimal:2',
    ];

    public function bom()
    {
        return $this->belongsTo(Bom::class);
    }

    public function items()
    {
        return $this->hasMany(ProductionItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
