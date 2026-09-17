<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateBackupSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('backup_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('auto_backup_enabled')->default(true);
            $table->string('schedule_frequency')->default('everyThreeHours'); // hourly, daily, weekly, everyThreeHours, etc.
            $table->string('schedule_time')->nullable(); // For daily/weekly schedules (e.g., "02:00")
            $table->string('schedule_day')->nullable(); // For weekly schedules (e.g., "monday")
            $table->string('default_backup_path')->nullable(); // Default path for auto backups
            $table->integer('keep_backups_days')->default(30); // How many days to keep backups
            $table->timestamps();
        });

        // Insert default settings
        DB::table('backup_settings')->insert([
            'auto_backup_enabled' => true,
            'schedule_frequency' => 'everyThreeHours',
            'schedule_time' => null,
            'schedule_day' => null,
            'default_backup_path' => null, // Will use storage/backups by default
            'keep_backups_days' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('backup_settings');
    }
}
