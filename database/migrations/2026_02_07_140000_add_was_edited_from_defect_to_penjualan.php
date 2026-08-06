<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWasEditedFromDefectToPenjualan extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('penjualan', function (Blueprint $table) {
            if (!Schema::hasColumn('penjualan', 'was_edited_from_defect')) {
                $table->boolean('was_edited_from_defect')->default(false)->after('confirmation_status');
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
            if (Schema::hasColumn('penjualan', 'was_edited_from_defect')) {
                $table->dropColumn('was_edited_from_defect');
            }
        });
    }
}





