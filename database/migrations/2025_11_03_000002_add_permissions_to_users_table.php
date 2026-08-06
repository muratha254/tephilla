<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'can_create')) {
                $table->boolean('can_create')->default(false)->after('role');
            }
            if (!Schema::hasColumn('users', 'can_read')) {
                $table->boolean('can_read')->default(true)->after('can_create');
            }
            if (!Schema::hasColumn('users', 'can_update')) {
                $table->boolean('can_update')->default(false)->after('can_read');
            }
            if (!Schema::hasColumn('users', 'can_delete')) {
                $table->boolean('can_delete')->default(false)->after('can_update');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'can_create')) {
                $table->dropColumn('can_create');
            }
            if (Schema::hasColumn('users', 'can_read')) {
                $table->dropColumn('can_read');
            }
            if (Schema::hasColumn('users', 'can_update')) {
                $table->dropColumn('can_update');
            }
            if (Schema::hasColumn('users', 'can_delete')) {
                $table->dropColumn('can_delete');
            }
        });
    }
};

















