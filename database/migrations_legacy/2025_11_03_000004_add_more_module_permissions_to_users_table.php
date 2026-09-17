<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMoreModulePermissionsToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Reports
            $table->boolean('rep_create')->default(false)->after('inv_delete');
            $table->boolean('rep_read')->default(true)->after('rep_create');
            $table->boolean('rep_update')->default(false)->after('rep_read');
            $table->boolean('rep_delete')->default(false)->after('rep_update');

            // Sales
            $table->boolean('sal_create')->default(false)->after('rep_delete');
            $table->boolean('sal_read')->default(true)->after('sal_create');
            $table->boolean('sal_update')->default(false)->after('sal_read');
            $table->boolean('sal_delete')->default(false)->after('sal_update');

            // Expense
            $table->boolean('exp_create')->default(false)->after('sal_delete');
            $table->boolean('exp_read')->default(true)->after('exp_create');
            $table->boolean('exp_update')->default(false)->after('exp_read');
            $table->boolean('exp_delete')->default(false)->after('exp_update');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'rep_create','rep_read','rep_update','rep_delete',
                'sal_create','sal_read','sal_update','sal_delete',
                'exp_create','exp_read','exp_update','exp_delete',
            ]);
        });
    }
}














