<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_pms_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_vehicle_id')->constrained('fleet_vehicles')->cascadeOnDelete();
            $table->string('service_name');
            $table->unsignedInteger('interval_km')->nullable();
            $table->unsignedInteger('interval_days')->nullable();
            $table->date('last_service_date')->nullable();
            $table->timestamps();
        });

        $vehicleId = DB::table('fleet_vehicles')->orderBy('id')->value('id');

        if (! $vehicleId) {
            return;
        }

        DB::table('fleet_pms_rules')->insert([
            [
                'fleet_vehicle_id' => $vehicleId,
                'service_name' => 'Tyre Rotation',
                'interval_km' => 5000,
                'interval_days' => 150,
                'last_service_date' => '2026-01-10',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'fleet_vehicle_id' => $vehicleId,
                'service_name' => 'Oil service',
                'interval_km' => 10000,
                'interval_days' => 180,
                'last_service_date' => '2026-01-10',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_pms_rules');
    }
};
