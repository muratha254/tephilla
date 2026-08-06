<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Pembelian extends Model
{
    use HasFactory;

    protected $table = 'pembelian';
    protected $primaryKey = 'id_pembelian';
    protected $guarded = [];
    protected $dates = ['purchasedate2'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'id_supplier', 'id_supplier');
    }

    public function details()
    {
        return $this->hasMany(PembelianDetail::class, 'id_pembelian', 'id_pembelian');
    }
}
