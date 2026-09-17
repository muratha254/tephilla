<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;

    public $timestamps = false;

    protected $fillable = [
        'company_id', 'branch_id', 'user_id', 'action', 'module',
        'auditable_type', 'auditable_id', 'before_json', 'after_json',
        'ip_address', 'user_agent', 'computer_json', 'created_at',
    ];

    protected $casts = [
        'before_json' => 'array',
        'after_json' => 'array',
        'computer_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auditable()
    {
        return $this->morphTo();
    }
}
