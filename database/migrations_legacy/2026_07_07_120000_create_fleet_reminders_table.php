<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_vehicle_id')->constrained('fleet_vehicles')->cascadeOnDelete();
            $table->date('due_date');
            $table->text('services')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
        });

        $vehicles = DB::table('fleet_vehicles')->orderBy('id')->limit(2)->pluck('id');

        if ($vehicles->isEmpty()) {
            return;
        }

        $vehicleOne = $vehicles[0];
        $vehicleTwo = $vehicles[1] ?? $vehicleOne;

        DB::table('fleet_reminders')->insert([
            [
                'fleet_vehicle_id' => $vehicleOne,
                'due_date' => now()->subDays(30)->toDateString(),
                'services' => 'Vehicle Fitness Check, Insurance Claim Support',
                'notes' => 'Need to check insurance and fitness check.',
                'is_completed' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'fleet_vehicle_id' => $vehicleTwo,
                'due_date' => now()->addDay()->toDateString(),
                'services' => 'Fleet Maintenance, Preventive Maintenance',
                'notes' => 'Need to do maintenance check.',
                'is_completed' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_reminders');
    }
};
