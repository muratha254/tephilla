<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInventoryPermissionsToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('inv_create')->default(true)->after('can_delete');
            $table->boolean('inv_read')->default(true)->after('inv_create');
            $table->boolean('inv_update')->default(true)->after('inv_read');
            $table->boolean('inv_delete')->default(true)->after('inv_update');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['inv_create', 'inv_read', 'inv_update', 'inv_delete']);
        });
    }
}
















