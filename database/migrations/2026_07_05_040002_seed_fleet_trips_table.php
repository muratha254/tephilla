<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fleet_trips') || DB::table('fleet_trips')->count() > 0) {
            return;
        }

        $vehicleId = DB::table('fleet_vehicles')->value('id');
        $driverId = DB::table('fleet_drivers')->where('name', 'Driver 480')->value('id')
            ?: DB::table('fleet_drivers')->value('id');

        $now = now();
        $rows = [
            ['trip_code' => 'OT-2026-001', 'customer_name' => 'Jk', 'customer_phone' => '9876543210', 'pickup_location' => 'Erode, Tamil Nadu, India', 'drop_location' => 'Karur, Tamil Nadu, India', 'start_date' => '2026-01-09', 'start_time' => '23:00:00', 'end_date' => '2026-01-10', 'end_time' => '02:00:00', 'status' => 'Booked'],
            ['trip_code' => 'OT-2026-002', 'customer_name' => 'Demo Customer 320', 'customer_phone' => '9123456780', 'pickup_location' => 'Chennai, Tamil Nadu, India', 'drop_location' => 'Coimbatore, Tamil Nadu, India', 'start_date' => '2026-01-12', 'start_time' => '08:00:00', 'end_date' => '2026-01-12', 'end_time' => '16:00:00', 'status' => 'pending', 'fleet_vehicle_id' => null],
            ['trip_code' => 'OT-2026-003', 'customer_name' => 'Metro Freight', 'customer_phone' => '9000012345', 'pickup_location' => 'Madurai, Tamil Nadu, India', 'drop_location' => 'Trichy, Tamil Nadu, India', 'start_date' => '2026-01-11', 'start_time' => '10:30:00', 'end_date' => '2026-01-11', 'end_time' => '18:30:00', 'status' => 'ongoing'],
            ['trip_code' => 'OT-2026-004', 'customer_name' => 'Sunrise Deliveries', 'customer_phone' => '9111222333', 'pickup_location' => 'Salem, Tamil Nadu, India', 'drop_location' => 'Bangalore, Karnataka, India', 'start_date' => '2026-01-13', 'start_time' => '06:00:00', 'end_date' => '2026-01-13', 'end_time' => '20:00:00', 'status' => 'Booked'],
            ['trip_code' => 'OT-2026-005', 'customer_name' => 'Blue Line Transport', 'customer_phone' => '9887766554', 'pickup_location' => 'Hyderabad, Telangana, India', 'drop_location' => 'Vijayawada, Andhra Pradesh, India', 'start_date' => '2026-01-14', 'start_time' => '07:00:00', 'end_date' => '2026-01-14', 'end_time' => '15:00:00', 'status' => 'pending'],
            ['trip_code' => 'OT-2026-006', 'customer_name' => 'Acme Logistics', 'customer_phone' => '9776655443', 'pickup_location' => 'Pune, Maharashtra, India', 'drop_location' => 'Mumbai, Maharashtra, India', 'start_date' => '2026-01-15', 'start_time' => '09:00:00', 'end_date' => '2026-01-15', 'end_time' => '17:00:00', 'status' => 'ongoing'],
            ['trip_code' => 'OT-2026-007', 'customer_name' => 'City Cargo Ltd', 'customer_phone' => '9665544332', 'pickup_location' => 'Delhi, India', 'drop_location' => 'Jaipur, Rajasthan, India', 'start_date' => '2026-01-16', 'start_time' => '05:30:00', 'end_date' => '2026-01-16', 'end_time' => '14:30:00', 'status' => 'Booked'],
            ['trip_code' => 'OT-2026-008', 'customer_name' => 'Jk', 'customer_phone' => '9876543210', 'pickup_location' => 'Karur, Tamil Nadu, India', 'drop_location' => 'Erode, Tamil Nadu, India', 'start_date' => '2026-01-17', 'start_time' => '11:00:00', 'end_date' => '2026-01-17', 'end_time' => '14:00:00', 'status' => 'Booked', 'return_trip_of' => 'OT-2026-001'],
            ['trip_code' => 'OT-2026-009', 'customer_name' => 'Demo Customer 320', 'customer_phone' => '9123456780', 'pickup_location' => 'Kochi, Kerala, India', 'drop_location' => 'Trivandrum, Kerala, India', 'start_date' => '2026-01-18', 'start_time' => '08:30:00', 'end_date' => '2026-01-18', 'end_time' => '12:30:00', 'status' => 'ongoing'],
            ['trip_code' => 'OT-2026-010', 'customer_name' => 'Metro Freight', 'customer_phone' => '9000012345', 'pickup_location' => 'Nagpur, Maharashtra, India', 'drop_location' => 'Bhopal, Madhya Pradesh, India', 'start_date' => '2026-01-19', 'start_time' => '13:00:00', 'end_date' => '2026-01-19', 'end_time' => '22:00:00', 'status' => 'pending'],
            ['trip_code' => 'OT-2026-011', 'customer_name' => 'Sunrise Deliveries', 'customer_phone' => '9111222333', 'pickup_location' => 'Ahmedabad, Gujarat, India', 'drop_location' => 'Surat, Gujarat, India', 'start_date' => '2026-01-20', 'start_time' => '07:30:00', 'end_date' => '2026-01-20', 'end_time' => '11:30:00', 'status' => 'ongoing'],
        ];

        foreach ($rows as $index => $row) {
            DB::table('fleet_trips')->insert(array_merge([
                'trip_type' => 'Single Trip',
                'fleet_vehicle_id' => $row['fleet_vehicle_id'] ?? $vehicleId,
                'fleet_driver_id' => $driverId,
                'billing_type' => 'Fixed',
                'base_amount' => 0,
                'tax_type' => 'No Tax',
                'discount_amount' => 0,
                'additional_stops' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $row));
        }
    }

    public function down(): void
    {
        DB::table('fleet_trips')->whereIn('trip_code', [
            'OT-2026-001', 'OT-2026-002', 'OT-2026-003', 'OT-2026-004', 'OT-2026-005',
            'OT-2026-006', 'OT-2026-007', 'OT-2026-008', 'OT-2026-009', 'OT-2026-010', 'OT-2026-011',
        ])->delete();
    }
};
