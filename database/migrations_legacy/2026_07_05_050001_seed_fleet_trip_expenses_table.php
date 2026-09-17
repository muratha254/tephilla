<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fleet_trip_expenses') || DB::table('fleet_trip_expenses')->count() > 0) {
            return;
        }

        $tripId = DB::table('fleet_trips')->where('trip_code', 'OT-2026-001')->value('id');

        if (! $tripId) {
            return;
        }

        $now = now();

        $rows = [
            ['expense_date' => '2026-01-09', 'category' => 'Fuel', 'description' => 'Diesel refill at Erode', 'amount' => 8500, 'payment_method' => 'M-Pesa', 'reference_no' => 'MPX123456'],
            ['expense_date' => '2026-01-09', 'category' => 'Toll', 'description' => 'Highway toll charges', 'amount' => 1200, 'payment_method' => 'Cash', 'reference_no' => null],
            ['expense_date' => '2026-01-10', 'category' => 'Driver Allowance', 'description' => 'Overnight allowance', 'amount' => 2500, 'payment_method' => 'Cash', 'reference_no' => null],
            ['expense_date' => '2026-01-10', 'category' => 'Parking', 'description' => 'Destination parking fee', 'amount' => 300, 'payment_method' => 'Cash', 'reference_no' => null],
        ];

        foreach ($rows as $row) {
            DB::table('fleet_trip_expenses')->insert(array_merge($row, [
                'fleet_trip_id' => $tripId,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        $tripId = DB::table('fleet_trips')->where('trip_code', 'OT-2026-001')->value('id');

        if ($tripId) {
            DB::table('fleet_trip_expenses')->where('fleet_trip_id', $tripId)->delete();
        }
    }
};
