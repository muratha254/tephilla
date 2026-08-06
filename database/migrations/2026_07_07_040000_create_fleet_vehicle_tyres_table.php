<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_vehicle_tyres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_vehicle_id')->constrained('fleet_vehicles')->cascadeOnDelete();
            $table->string('position', 50);
            $table->string('serial_number', 100);
            $table->string('brand_model')->nullable();
            $table->date('install_date');
            $table->unsignedInteger('install_odometer')->nullable();
            $table->timestamps();

            $table->unique(['fleet_vehicle_id', 'position']);
        });

        $vehicleId = DB::table('fleet_vehicles')->orderBy('id')->value('id');

        if (! $vehicleId) {
            return;
        }

        DB::table('fleet_vehicle_tyres')->insert([
            'fleet_vehicle_id' => $vehicleId,
            'position' => 'Front-Left',
            'serial_number' => '21332132131',
            'brand_model' => 'MRF',
            'install_date' => '2026-01-10',
            'install_odometer' => 10000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_vehicle_tyres');
    }
};
