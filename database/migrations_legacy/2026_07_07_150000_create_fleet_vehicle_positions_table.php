<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_vehicle_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_vehicle_id')->unique()->constrained('fleet_vehicles')->cascadeOnDelete();
            $table->foreignId('fleet_trip_id')->nullable()->constrained('fleet_trips')->nullOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed_kmh', 8, 2)->default(0);
            $table->string('tracking_status', 20)->default('idle');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        $vehicles = DB::table('fleet_vehicles')->orderBy('id')->get();

        if ($vehicles->isEmpty()) {
            return;
        }

        $coordinates = [
            [13.0418, 80.2341],
            [13.0067, 80.2572],
            [12.9698, 80.2209],
            [12.9815, 80.2180],
            [12.9249, 80.2100],
            [13.0600, 80.2450],
            [12.9500, 80.1900],
        ];

        $sampleNames = [
            ['name' => 'Fleet MOTORCYCLE 93', 'registration_number' => 'TN-84-I-Z-13DR', 'type' => 'Motorcycle'],
            ['name' => 'Fleet TRUCK 70', 'registration_number' => 'TN-80-LS-6289', 'type' => 'Truck'],
            ['name' => 'Fleet CAR 12', 'registration_number' => 'TN-09-AB-1234', 'type' => 'Car'],
            ['name' => 'Fleet VAN 45', 'registration_number' => 'TN-07-CD-5678', 'type' => 'Van'],
            ['name' => 'Fleet BUS 88', 'registration_number' => 'TN-12-EF-9012', 'type' => 'Bus'],
        ];

        $lastSeen = now()->subDay();
        $now = now();

        foreach ($vehicles as $index => $vehicle) {
            if (isset($sampleNames[$index])) {
                DB::table('fleet_vehicles')
                    ->where('id', $vehicle->id)
                    ->update(array_merge($sampleNames[$index], [
                        'gps_enabled' => true,
                        'status' => 'Active',
                        'updated_at' => $now,
                    ]));
            } else {
                DB::table('fleet_vehicles')
                    ->where('id', $vehicle->id)
                    ->update([
                        'gps_enabled' => true,
                        'updated_at' => $now,
                    ]);
            }

            $tripId = DB::table('fleet_trips')
                ->where('fleet_vehicle_id', $vehicle->id)
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->value('id');

            $coordinate = $coordinates[$index % count($coordinates)];

            DB::table('fleet_vehicle_positions')->insert([
                'fleet_vehicle_id' => $vehicle->id,
                'fleet_trip_id' => $tripId,
                'latitude' => $coordinate[0],
                'longitude' => $coordinate[1],
                'speed_kmh' => 0,
                'tracking_status' => 'idle',
                'last_seen_at' => $lastSeen,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_vehicle_positions');
    }
};
