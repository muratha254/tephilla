<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRateToSettingLookupsTable extends Migration
{
    public function up()
    {
        Schema::table('setting_lookups', function (Blueprint $table) {
            if (! Schema::hasColumn('setting_lookups', 'rate')) {
                $table->decimal('rate', 18, 6)->default(0)->after('symbol');
            }
        });
    }

    public function down()
    {
        Schema::table('setting_lookups', function (Blueprint $table) {
            if (Schema::hasColumn('setting_lookups', 'rate')) {
                $table->dropColumn('rate');
            }
        });
    }
}
