<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUnitCostToFoldingOutputs extends Migration
{
    public function up()
    {
        Schema::table('folding_outputs', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 4)->nullable()->after('quantity');
        });
    }

    public function down()
    {
        Schema::table('folding_outputs', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
}
