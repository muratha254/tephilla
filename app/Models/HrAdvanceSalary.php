<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrAdvanceSalary extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;
    use SoftDeletes;

    protected $table = 'hr_advance_salaries';

    protected $fillable = [
        'company_id',
        'branch_id',
        'employee_id',
        'ledger_account_id',
        'user_id',
        'advance_date',
        'amount',
        'description',
    ];

    protected $casts = [
        'advance_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function ledgerAccount()
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function monthLabel(): string
    {
        return optional($this->advance_date)->format('F Y') ?: '';
    }
}
