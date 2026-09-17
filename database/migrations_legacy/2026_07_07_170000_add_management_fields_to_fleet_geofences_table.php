<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_geofences', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->text('description')->nullable()->after('name');
            $table->string('created_by', 100)->default('admin')->after('center_lng');
            $table->boolean('notify_sms')->default(false)->after('created_by');
            $table->boolean('notify_email')->default(false)->after('notify_sms');
            $table->json('assigned_vehicle_ids')->nullable()->after('notify_email');
        });

        DB::table('fleet_geofences')->delete();

        $truckId = DB::table('fleet_vehicles')->where('name', 'like', '%TRUCK%')->value('id')
            ?: DB::table('fleet_vehicles')->orderBy('id')->skip(1)->value('id');
        $motorcycleId = DB::table('fleet_vehicles')->where('name', 'like', '%MOTORCYCLE%')->value('id')
            ?: DB::table('fleet_vehicles')->orderBy('id')->value('id');
        $extraVehicleId = DB::table('fleet_vehicles')->orderBy('id')->skip(2)->value('id');

        $assignedMotorcycle = array_values(array_filter([$motorcycleId, $extraVehicleId]));

        DB::table('fleet_geofences')->insert([
            [
                'name' => 'werw',
                'description' => 'erwe',
                'location_label' => 'Chennai, Tamil Nadu, India',
                'shape_type' => 'circle',
                'geometry' => json_encode([
                    'lat' => 13.0500,
                    'lng' => 80.2100,
                    'radius' => 900,
                ]),
                'center_lat' => 13.0500,
                'center_lng' => 80.2100,
                'created_by' => 'admin',
                'notify_sms' => true,
                'notify_email' => false,
                'assigned_vehicle_ids' => json_encode(array_values(array_filter([$truckId]))),
                'created_at' => '2026-01-09 10:00:00',
                'updated_at' => '2026-01-09 10:00:00',
            ],
            [
                'name' => 'T. Nagar Test Zone',
                'description' => 'Testing Geofence module',
                'location_label' => 'T. Nagar, Chennai, Tamil Nadu, India',
                'shape_type' => 'rectangle',
                'geometry' => json_encode([
                    'south' => 13.0350,
                    'west' => 80.2250,
                    'north' => 13.0480,
                    'east' => 80.2420,
                ]),
                'center_lat' => 13.0415,
                'center_lng' => 80.2335,
                'created_by' => 'admin',
                'notify_sms' => false,
                'notify_email' => true,
                'assigned_vehicle_ids' => json_encode($assignedMotorcycle),
                'created_at' => '2026-01-08 10:00:00',
                'updated_at' => '2026-01-08 10:00:00',
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('fleet_geofences', function (Blueprint $table) {
            $table->dropColumn([
                'name',
                'description',
                'created_by',
                'notify_sms',
                'notify_email',
                'assigned_vehicle_ids',
            ]);
        });
    }
};
