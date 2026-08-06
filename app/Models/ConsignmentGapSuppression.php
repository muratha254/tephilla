<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsignmentGapSuppression extends Model
{
    protected $table = 'consignment_gap_suppressions';

    protected $fillable = [
        'penjualan_id',
        'produk_id',
        'supplier_id',
        'note',
    ];
}
