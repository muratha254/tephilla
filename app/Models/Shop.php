<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Produk;

class Shop extends Model
{
    use HasFactory;
    protected $table = 'shops';
    protected $primaryKey = 'id';
    protected $guarded = [];

    public function products()
    {
        return $this->hasMany(Produk::class, 'shop_id', 'id');
    }
}
