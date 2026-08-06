<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class Produk extends Model
{
    use HasFactory;

    protected $table = 'produk';
    protected $primaryKey = 'id_produk';
    protected $guarded = [];
    protected $fillable = [
        'shop_id',
        'id_supplier',
        'id_kategori',
        'mop',
        'item_code',
        'kode_produk',
        'nama_produk',
        'harga_beli',
        'harga_jual',
        'diskon',
        'stok',
        'date_in',
        'is_incomplete',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'id_supplier', 'id_supplier');
    }

    /**
     * After a sale line is removed: delete POS quick-add products that are incomplete,
     * no longer on any sale, and were created via quick add (quick_added flag or placeholder supplier "-").
     */
    public static function deleteOrphanPosQuickAddProduct(?int $idProduk): void
    {
        if (! $idProduk) {
            return;
        }
        if (! Schema::hasColumn('produk', 'is_incomplete')) {
            return;
        }

        $produk = static::with('supplier')->find($idProduk);
        if (! $produk || (int) ($produk->is_incomplete ?? 0) !== 1) {
            return;
        }

        if (PenjualanDetail::where('id_produk', $idProduk)->exists()) {
            return;
        }

        $fromPosQuickAdd = false;
        if (Schema::hasColumn('produk', 'quick_added') && (int) ($produk->quick_added ?? 0) === 1) {
            $fromPosQuickAdd = true;
        }
        $supplier = $produk->supplier;
        if (! $fromPosQuickAdd && $supplier && trim((string) ($supplier->nama ?? '')) === '-') {
            $fromPosQuickAdd = true;
        }
        if (! $fromPosQuickAdd) {
            return;
        }

        try {
            $produk->delete();
        } catch (\Throwable $e) {
            Log::warning('deleteOrphanPosQuickAddProduct: could not delete produk '.$idProduk.': '.$e->getMessage());
        }
    }

}
