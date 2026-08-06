<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProdukEditLog extends Model
{
    protected $table = 'produk_edit_log';

    protected $guarded = [];

    protected $casts = [
        'changes' => 'array',
    ];

    public function produk()
    {
        return $this->belongsTo(Produk::class, 'id_produk', 'id_produk');
    }
}
