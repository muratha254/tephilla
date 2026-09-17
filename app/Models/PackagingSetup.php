<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PackagingSetup extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'product_id', 'user_id', 'description',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function items()
    {
        return $this->hasMany(PackagingSetupItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
