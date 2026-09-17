<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_vehicle_fuel_refills', function (Blueprint $table) {
            $table->foreignId('fleet_driver_id')->nullable()->after('fleet_vehicle_id')->constrained('fleet_drivers')->nullOnDelete();
            $table->unsignedInteger('odometer')->nullable()->after('refill_date');
        });

        if (DB::table('fleet_vehicle_fuel_refills')->count() > 0) {
            return;
        }

        $vehicleId = DB::table('fleet_vehicles')->orderBy('id')->value('id');
        $driverId = DB::table('fleet_drivers')->where('name', 'Driver 497')->value('id')
            ?: DB::table('fleet_drivers')->value('id');

        if (! $vehicleId) {
            return;
        }

        $now = now();

        DB::table('fleet_vehicle_fuel_refills')->insert([
            [
                'fleet_vehicle_id' => $vehicleId,
                'fleet_driver_id' => $driverId,
                'refill_date' => '2026-01-10',
                'odometer' => 78788,
                'liters' => 45,
                'cost' => 2500,
                'payment_method' => 'Cash',
                'reference_no' => null,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'fleet_vehicle_id' => $vehicleId,
                'fleet_driver_id' => $driverId,
                'refill_date' => '2026-01-12',
                'odometer' => 79200,
                'liters' => 100,
                'cost' => 4500,
                'payment_method' => 'M-Pesa',
                'reference_no' => 'MPX123456',
                'notes' => 'Shell station refill',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $totalLiters = 145;
        DB::table('fleet_vehicles')
            ->where('id', $vehicleId)
            ->update([
                'current_fuel' => DB::raw('COALESCE(opening_fuel, 0) + ' . $totalLiters),
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        Schema::table('fleet_vehicle_fuel_refills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fleet_driver_id');
            $table->dropColumn('odometer');
        });
    }
};
