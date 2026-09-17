<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsIncompleteToProdukTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('produk', function (Blueprint $table) {
            if (!Schema::hasColumn('produk', 'is_incomplete')) {
                $table->boolean('is_incomplete')
                    ->default(false)
                    ->after('date_in');
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
            if (Schema::hasColumn('produk', 'is_incomplete')) {
                $table->dropColumn('is_incomplete');
            }
        });
    }
}























