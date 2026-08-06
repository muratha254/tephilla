<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPenjualanIdToInvoiceItemsTable extends Migration
{
    /**
     * Run the migrations.
     * Links each invoice/consignment item to the sale it came from for total items sold and consignment tracking.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->unsignedInteger('penjualan_id')->nullable()->after('invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('penjualan_id');
        });
    }
}
