<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddItemConfirmationStatusToPenjualanDetail extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('penjualan_detail', function (Blueprint $table) {
            if (!Schema::hasColumn('penjualan_detail', 'item_confirmation_status')) {
                $table->enum('item_confirmation_status', ['pending', 'confirmed', 'defect'])
                    ->default('pending')
                    ->after('subtotal');
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
        Schema::table('penjualan_detail', function (Blueprint $table) {
            if (Schema::hasColumn('penjualan_detail', 'item_confirmation_status')) {
                $table->dropColumn('item_confirmation_status');
            }
        });
    }
}





