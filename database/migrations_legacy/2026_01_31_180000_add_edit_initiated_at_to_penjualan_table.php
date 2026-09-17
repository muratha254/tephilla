<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEditInitiatedAtToPenjualanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('penjualan', function (Blueprint $table) {
            if (!Schema::hasColumn('penjualan', 'edit_initiated_at')) {
                if (Schema::hasColumn('penjualan', 'status')) {
                    $table->timestamp('edit_initiated_at')->nullable()->after('status');
                } else {
                    $table->timestamp('edit_initiated_at')->nullable();
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
            if (Schema::hasColumn('penjualan', 'edit_initiated_at')) {
                $table->dropColumn('edit_initiated_at');
            }
        });
    }
}
