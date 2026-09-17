<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $vehicleId = DB::table('fleet_vehicles')
            ->where('name', 'Fleet TRUCK 70')
            ->value('id')
            ?: DB::table('fleet_vehicles')->orderBy('id')->value('id');

        if (! $vehicleId) {
            return;
        }

        DB::table('fleet_reminders')->updateOrInsert(
            [
                'fleet_vehicle_id' => $vehicleId,
                'due_date' => '2026-01-10',
            ],
            [
                'services' => 'Vehicle Fitness Check, Insurance Claim Support',
                'notes' => 'Need to check insurance and fitness check',
                'is_completed' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('fleet_reminders')
            ->where('due_date', '2026-01-10')
            ->where('notes', 'Need to check insurance and fitness check')
            ->delete();
    }
};
