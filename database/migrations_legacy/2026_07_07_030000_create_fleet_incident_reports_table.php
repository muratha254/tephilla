<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_incident_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_vehicle_id')->constrained('fleet_vehicles')->cascadeOnDelete();
            $table->foreignId('fleet_maintenance_id')->nullable()->constrained('fleet_maintenances')->nullOnDelete();
            $table->string('reported_by')->nullable();
            $table->text('description');
            $table->date('incident_date');
            $table->string('status', 30)->default('Open');
            $table->timestamps();
        });

        $vehicleId = DB::table('fleet_vehicles')->orderBy('id')->value('id');

        if (! $vehicleId) {
            return;
        }

        $description = "Uneven tyre wear\nLow tyre pressure warning\nWheel alignment issue";

        DB::table('fleet_incident_reports')->insert([
            [
                'fleet_vehicle_id' => $vehicleId,
                'reported_by' => 'admin',
                'description' => $description,
                'incident_date' => '2026-01-10',
                'status' => 'Open',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'fleet_vehicle_id' => $vehicleId,
                'reported_by' => 'admin',
                'description' => $description,
                'incident_date' => '2026-01-10',
                'status' => 'Open',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_incident_reports');
    }
};
