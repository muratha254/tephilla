<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class AccountType extends Model
{
    use BelongsToCompany;

    public const REPORT_BS = 'BS';
    public const REPORT_PL = 'PL';

    protected $fillable = [
        'company_id', 'code', 'name', 'report_type', 'description',
    ];

    public function subTypes()
    {
        return $this->hasMany(AccountSubType::class);
    }

    public function isDebitNormal(): bool
    {
        return in_array($this->name, ['ASSETS', 'EXPENSES'], true)
            || $this->report_type === self::REPORT_PL && strcasecmp($this->name, 'Income/Revenue') !== 0;
    }
}
