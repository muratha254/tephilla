<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_settings', function (Blueprint $table) {
            $table->integer('schedule_interval_hours')->nullable()->after('schedule_time');
        });

        // Ensure any existing rows have a non-null value if needed later.
        // (We keep it nullable by default to avoid changing behavior.)
        if (Schema::hasColumn('backup_settings', 'schedule_interval_hours')) {
            DB::statement('UPDATE backup_settings SET schedule_interval_hours = schedule_interval_hours WHERE schedule_interval_hours IS NULL');
        }
    }

    public function down(): void
    {
        Schema::table('backup_settings', function (Blueprint $table) {
            $table->dropColumn('schedule_interval_hours');
        });
    }
};

