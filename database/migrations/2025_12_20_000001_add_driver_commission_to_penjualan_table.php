<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDriverCommissionToPenjualanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('penjualan', function (Blueprint $table) {
            if (! Schema::hasColumn('penjualan', 'driver_commission')) {
                $table->decimal('driver_commission', 15, 2)->default(0)->after('tax');
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
            $table->dropColumn('driver_commission');
        });
    }
}






