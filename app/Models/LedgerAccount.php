<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LedgerAccount extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'account_sub_type_id', 'name', 'gl_code',
        'description', 'opening_balance', 'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function subType()
    {
        return $this->belongsTo(AccountSubType::class, 'account_sub_type_id');
    }

    public function journalLines()
    {
        return $this->hasMany(JournalLine::class);
    }

    public function moneyEntries()
    {
        return $this->hasMany(MoneyEntry::class);
    }

    public function isDebitNormal(): bool
    {
        $typeName = optional(optional($this->subType)->accountType)->name;

        return in_array($typeName, ['ASSETS', 'EXPENSES'], true);
    }
}
