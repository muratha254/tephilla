<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds database indexes for optimized product search performance
     * These indexes significantly improve query speed for:
     * - Product name searches (nama_produk)
     * - Product code searches (item_code)
     * - Shop-based filtering (shop_id)
     * - Combined shop + name searches
     *
     * @return void
     */
    public function up()
    {
        Schema::table('produk', function (Blueprint $table) {
            // Index on product name for fast name-based searches
            // Used in: WHERE nama_produk LIKE '%search%'
            $table->index('nama_produk', 'idx_produk_nama');
            
            // Index on item code for fast code-based searches
            // Used in: WHERE item_code LIKE '%search%'
            $table->index('item_code', 'idx_produk_item_code');
            
            // Index on shop_id for fast shop filtering
            // Used in: WHERE shop_id = X
            $table->index('shop_id', 'idx_produk_shop_id');
            
            // Composite index for combined shop + name searches
            // Optimizes: WHERE shop_id = X AND nama_produk LIKE '%search%'
            // This is the most common query pattern in POS search
            $table->index(['shop_id', 'nama_produk'], 'idx_produk_shop_nama');
            
            // Composite index for shop + item_code searches
            // Optimizes: WHERE shop_id = X AND item_code LIKE '%search%'
            $table->index(['shop_id', 'item_code'], 'idx_produk_shop_code');
        });
        
        // Also add index on shops table for shop_code lookups
        Schema::table('shops', function (Blueprint $table) {
            // Index on shop_code for fast shop code filtering
            // Used in JOIN: shops.shop_code = X
            if (Schema::hasColumn('shops', 'shop_code')) {
                $table->index('shop_code', 'idx_shops_shop_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->dropIndex('idx_produk_nama');
            $table->dropIndex('idx_produk_item_code');
            $table->dropIndex('idx_produk_shop_id');
            $table->dropIndex('idx_produk_shop_nama');
            $table->dropIndex('idx_produk_shop_code');
        });
        
        Schema::table('shops', function (Blueprint $table) {
            if (Schema::hasColumn('shops', 'shop_code')) {
                $table->dropIndex('idx_shops_shop_code');
            }
        });
    }
};
