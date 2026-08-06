<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCurrencyTypeToPenjualanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('penjualan', function (Blueprint $table) {
            if (!Schema::hasColumn('penjualan', 'currency_type')) {
                $table->string('currency_type', 10)->nullable()->after('payment_method');
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
            if (Schema::hasColumn('penjualan', 'currency_type')) {
                $table->dropColumn('currency_type');
            }
        });
    }
}
