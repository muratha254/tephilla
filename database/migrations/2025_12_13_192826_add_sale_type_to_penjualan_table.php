<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSaleTypeToPenjualanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('penjualan', function (Blueprint $table) {
            if (!Schema::hasColumn('penjualan', 'sale_type')) {
                $table->enum('sale_type', ['normal', 'management'])->default('normal')->after('receiptno');
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
        Schema::table('penjualan', function (Blueprint $table) {
            if (Schema::hasColumn('penjualan', 'sale_type')) {
                $table->dropColumn('sale_type');
            }
        });
    }
}
