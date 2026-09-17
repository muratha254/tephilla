<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentFile extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'company_id', 'branch_id', 'file_category_id', 'user_id',
        'title', 'file_path', 'original_name', 'mime_type', 'file_size',
        'expires_at', 'description', 'status',
    ];

    protected $casts = [
        'expires_at' => 'date',
        'file_size' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(FileCategory::class, 'file_category_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function resolveStatus(): string
    {
        if ($this->expires_at && $this->expires_at->lt(now()->startOfDay())) {
            return self::STATUS_EXPIRED;
        }

        return self::STATUS_ACTIVE;
    }

    public function statusLabel(): string
    {
        return $this->resolveStatus() === self::STATUS_EXPIRED ? 'Expired' : 'Active';
    }
}
