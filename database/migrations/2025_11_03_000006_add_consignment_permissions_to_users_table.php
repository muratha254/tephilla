<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConsignmentPermissionsToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('con_create')->default(false)->after('rep_delete');
            $table->boolean('con_read')->default(false)->after('con_create');
            $table->boolean('con_update')->default(false)->after('con_read');
            $table->boolean('con_delete')->default(false)->after('con_update');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['con_create','con_read','con_update','con_delete']);
        });
    }
}
















