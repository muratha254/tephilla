<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class AccountSubType extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'account_type_id', 'code', 'name', 'description', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function accountType()
    {
        return $this->belongsTo(AccountType::class);
    }

    public function accounts()
    {
        return $this->hasMany(LedgerAccount::class);
    }
}
