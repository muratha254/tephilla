<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPayrollPermissionsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('pay_create')->default(false)->after('con_delete');
            $table->boolean('pay_read')->default(false)->after('pay_create');
            $table->boolean('pay_update')->default(false)->after('pay_read');
            $table->boolean('pay_delete')->default(false)->after('pay_update');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pay_create', 'pay_read', 'pay_update', 'pay_delete']);
        });
    }
}
