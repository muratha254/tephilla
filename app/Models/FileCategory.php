<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class FileCategory extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'description', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function files()
    {
        return $this->hasMany(DocumentFile::class);
    }
}
