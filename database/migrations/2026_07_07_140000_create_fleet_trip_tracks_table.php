<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_trip_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_trip_id')->unique()->constrained('fleet_trips')->cascadeOnDelete();
            $table->json('route_points');
            $table->decimal('distance_km', 8, 2)->default(0);
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->timestamps();
        });

        $vehicleId = DB::table('fleet_vehicles')
            ->where('registration_number', 'TN-80-LS-6289')
            ->value('id');

        if (! $vehicleId) {
            $vehicleId = DB::table('fleet_vehicles')->value('id');
        }

        if ($vehicleId) {
            DB::table('fleet_vehicles')
                ->where('id', $vehicleId)
                ->update([
                    'name' => 'Fleet TRUCK 70',
                    'registration_number' => 'TN-80-LS-6289',
                    'gps_enabled' => true,
                    'updated_at' => now(),
                ]);
        }

        $driverId = DB::table('fleet_drivers')->value('id');
        $tripId = DB::table('fleet_trips')->where('id', 9)->value('id');

        $tripPayload = [
            'trip_code' => 'OT-2026-009',
            'trip_type' => 'Single Trip',
            'fleet_vehicle_id' => $vehicleId,
            'fleet_driver_id' => $driverId,
            'customer_name' => 'Demo Customer 320',
            'customer_phone' => '9123456780',
            'pickup_location' => 'Madhya Kailash, Chennai, Tamil Nadu, India',
            'drop_location' => 'Siruseri, Chennai, Tamil Nadu, India',
            'start_date' => '2026-01-10',
            'start_time' => '08:00:00',
            'end_date' => '2026-01-10',
            'end_time' => '23:25:00',
            'status' => 'completed',
            'updated_at' => now(),
        ];

        if ($tripId) {
            DB::table('fleet_trips')->where('id', $tripId)->update($tripPayload);
        } else {
            $tripId = DB::table('fleet_trips')->insertGetId(array_merge($tripPayload, [
                'billing_type' => 'Fixed',
                'base_amount' => 0,
                'tax_type' => 'No Tax',
                'discount_amount' => 0,
                'additional_stops' => null,
                'created_at' => now(),
            ]));
        }

        $routePoints = $this->generateRoutePoints(13.0037, 80.2514, 12.8314, 80.2037);

        DB::table('fleet_trip_tracks')->updateOrInsert(
            ['fleet_trip_id' => $tripId],
            [
                'route_points' => json_encode($routePoints),
                'distance_km' => 14.19,
                'duration_minutes' => 925,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_trip_tracks');
    }

    private function generateRoutePoints(float $startLat, float $startLng, float $endLat, float $endLng, int $steps = 48): array
    {
        $points = [];

        for ($i = 0; $i <= $steps; $i++) {
            $t = $i / $steps;
            $lat = $startLat + ($endLat - $startLat) * $t + sin($t * M_PI) * 0.0025;
            $lng = $startLng + ($endLng - $startLng) * $t + sin($t * M_PI * 2) * 0.0035;
            $points[] = [
                'lat' => round($lat, 6),
                'lng' => round($lng, 6),
            ];
        }

        return $points;
    }
};
