<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class RemoveUniqueConstraintFromNamaProduk extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Drop the unique constraint on nama_produk
        // First, try to find the index name
        $indexes = DB::select("SHOW INDEX FROM produk WHERE Column_name = 'nama_produk' AND Non_unique = 0");
        
        if (!empty($indexes)) {
            $indexName = $indexes[0]->Key_name;
            Schema::table('produk', function (Blueprint $table) use ($indexName) {
                $table->dropUnique($indexName);
            });
        } else {
            // Fallback: try common index names
            try {
                Schema::table('produk', function (Blueprint $table) {
                    $table->dropUnique(['nama_produk']);
                });
            } catch (\Exception $e) {
                // If that fails, try dropping by index name
                try {
                    DB::statement('ALTER TABLE produk DROP INDEX produk_nama_produk_unique');
                } catch (\Exception $e2) {
                    // Index might not exist or have different name, continue
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Only add unique constraint if it doesn't exist
        $indexExists = DB::select("SHOW INDEX FROM produk WHERE Key_name = 'produk_nama_produk_unique'");
        
        if (empty($indexExists)) {
            Schema::table('produk', function (Blueprint $table) {
                $table->unique('nama_produk');
            });
        }
    }
}
