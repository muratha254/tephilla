<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class NumberSequence extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'document_type',
        'prefix',
        'next_number',
        'padding',
    ];

    protected $casts = [
        'next_number' => 'integer',
        'padding' => 'integer',
    ];
}
