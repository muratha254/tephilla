<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDescriptionToUnitsTable extends Migration
{
    public function up()
    {
        Schema::table('units', function (Blueprint $table) {
            if (!Schema::hasColumn('units', 'description')) {
                $table->text('description')->nullable()->after('short_name');
            }
        });
    }

    public function down()
    {
        Schema::table('units', function (Blueprint $table) {
            if (Schema::hasColumn('units', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
}
