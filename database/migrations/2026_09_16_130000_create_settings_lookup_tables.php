<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSettingsLookupTables extends Migration
{
    public function up()
    {
        Schema::create('setting_lookups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40); // salutation, progress_status, currency, place_county, place_city
            $table->string('name');
            $table->string('code', 32)->nullable();
            $table->string('symbol', 16)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('setting_lookups')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'type', 'is_active']);
        });

        Schema::create('backup_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('auto_backup_enabled')->default(false);
            $table->string('schedule_frequency', 40)->nullable();
            $table->string('schedule_time', 10)->nullable();
            $table->string('schedule_day', 20)->nullable();
            $table->unsignedInteger('schedule_interval_hours')->nullable();
            $table->string('default_backup_path')->nullable();
            $table->unsignedInteger('keep_backups_days')->default(30);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('backup_settings');
        Schema::dropIfExists('setting_lookups');
    }
}
