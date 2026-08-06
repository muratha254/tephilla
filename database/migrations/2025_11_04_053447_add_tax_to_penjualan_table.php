<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTaxToPenjualanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('penjualan', function (Blueprint $table) {
            $table->decimal('tax', 15, 2)->default(0)->after('bayar');
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
            $table->dropColumn('tax');
        });
    }
}
