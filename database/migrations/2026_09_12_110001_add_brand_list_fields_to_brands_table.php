<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBrandListFieldsToBrandsTable extends Migration
{
    public function up()
    {
        Schema::table('brands', function (Blueprint $table) {
            if (!Schema::hasColumn('brands', 'code')) {
                $table->string('code', 32)->nullable()->after('name');
            }
            if (!Schema::hasColumn('brands', 'description')) {
                $table->text('description')->nullable()->after('code');
            }
        });
    }

    public function down()
    {
        Schema::table('brands', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('brands', 'code')) {
                $drop[] = 'code';
            }
            if (Schema::hasColumn('brands', 'description')) {
                $drop[] = 'description';
            }
            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }
}
