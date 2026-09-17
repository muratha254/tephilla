<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConditionToSaleReturnItems extends Migration
{
    public function up()
    {
        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->string('condition', 16)->default('good')->after('line_total');
        });
    }

    public function down()
    {
        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->dropColumn('condition');
        });
    }
}
