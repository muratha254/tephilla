<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fleet_drivers')) {
            return;
        }

        DB::table('fleet_drivers')
            ->where('name', 'Driver 619')
            ->update([
                'name' => 'Driver 519',
                'email' => 'driver519@example.com',
                'updated_at' => now(),
            ]);

        $driver480 = DB::table('fleet_drivers')->where('name', 'Driver 480')->value('id');
        $driver497 = DB::table('fleet_drivers')->where('name', 'Driver 497')->value('id');
        $driver519 = DB::table('fleet_drivers')->where('name', 'Driver 519')->value('id');
        $vehicleId = DB::table('fleet_vehicles')->value('id');

        if (! $driver480 || ! $vehicleId) {
            return;
        }

        DB::table('fleet_trips')
            ->whereDate('start_date', '>=', '2025-12-31')
            ->whereDate('start_date', '<=', '2026-01-11')
            ->update([
                'fleet_driver_id' => null,
                'updated_at' => now(),
            ]);

        DB::table('fleet_trips')
            ->whereIn('trip_code', ['OT-2026-001', 'OT-2026-003'])
            ->update([
                'fleet_driver_id' => $driver480,
                'base_amount' => 1230,
                'discount_amount' => 0,
                'tax_type' => 'No Tax',
                'start_date' => '2026-01-10',
                'updated_at' => now(),
            ]);

        if ($driver497) {
            DB::table('fleet_vehicle_fuel_refills')->updateOrInsert(
                ['fleet_driver_id' => $driver497, 'refill_date' => '2026-01-10'],
                [
                    'fleet_vehicle_id' => $vehicleId,
                    'liters' => 95,
                    'cost' => 2500,
                    'source' => 'Vendor',
                    'fuel_type' => 'Diesel',
                    'payment_method' => 'Cash',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        if ($driver519) {
            DB::table('fleet_vehicle_fuel_refills')
                ->where('cost', 4500)
                ->where('refill_date', '2026-01-09')
                ->update(['fleet_driver_id' => $driver519]);

            DB::table('fleet_vehicle_fuel_refills')->updateOrInsert(
                ['fleet_driver_id' => $driver519, 'refill_date' => '2026-01-09'],
                [
                    'fleet_vehicle_id' => $vehicleId,
                    'liters' => 170,
                    'cost' => 4500,
                    'source' => 'Vendor',
                    'fuel_type' => 'Diesel',
                    'payment_method' => 'Cash',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('fleet_drivers')
            ->where('name', 'Driver 519')
            ->update([
                'name' => 'Driver 619',
                'email' => 'driver619@example.com',
                'updated_at' => now(),
            ]);
    }
};
