<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'address',
        'phone',
        'phone_alt',
        'email',
        'city',
        'postcode',
        'logo_path',
        'system_mode',
        'till1',
        'account1',
        'till2',
        'account2',
        'bank_account_name',
        'bank_name',
        'bank_account_no',
        'bank_branch',
        'bank_code',
        'bank_swift',
        'include_catering_levy',
        'auto_receipt_amt_pos',
        'list_on_login',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'include_catering_levy' => 'boolean',
        'auto_receipt_amt_pos' => 'boolean',
        'list_on_login' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function shortName(): string
    {
        return (string) ($this->code ?: $this->name);
    }
}
