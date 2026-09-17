<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSupplierProfileFields extends Migration
{
    public function up()
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'mobile')) {
                $table->string('mobile', 64)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('suppliers', 'country')) {
                $table->string('country', 64)->nullable()->after('address');
            }
            if (!Schema::hasColumn('suppliers', 'state')) {
                $table->string('state', 64)->nullable()->after('country');
            }
            if (!Schema::hasColumn('suppliers', 'postcode')) {
                $table->string('postcode', 32)->nullable()->after('state');
            }
        });
    }

    public function down()
    {
        Schema::table('suppliers', function (Blueprint $table) {
            foreach (['mobile', 'country', 'state', 'postcode'] as $column) {
                if (Schema::hasColumn('suppliers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
