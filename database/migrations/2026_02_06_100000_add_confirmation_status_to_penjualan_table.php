<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConfirmationStatusToPenjualanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('penjualan', function (Blueprint $table) {
            if (!Schema::hasColumn('penjualan', 'confirmation_status')) {
                $column = $table->enum('confirmation_status', ['pending', 'confirmed', 'defect'])
                    ->default('pending');

                if (Schema::hasColumn('penjualan', 'status')) {
                    $column->after('status');
                }
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
            if (Schema::hasColumn('penjualan', 'confirmation_status')) {
                $table->dropColumn('confirmation_status');
            }
        });
    }
}





