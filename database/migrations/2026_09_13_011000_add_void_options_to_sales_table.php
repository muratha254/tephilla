<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVoidOptionsToSalesTable extends Migration
{
    public function up()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->boolean('void_refund')->default(false)->after('void_reason');
            $table->boolean('void_flagged')->default(false)->after('void_refund');
        });
    }

    public function down()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['void_refund', 'void_flagged']);
        });
    }
}
