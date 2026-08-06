<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('fleet_maintenances')) {
            return;
        }

        $vendorId = DB::table('fleet_vehicle_vendors')->where('company', 'Jerin')->value('id');

        if (! $vendorId) {
            $vendorId = DB::table('fleet_vehicle_vendors')->insertGetId([
                'company' => 'Jerin',
                'contact_person' => 'Jerin',
                'mobile' => '9000000001',
                'contract_date' => '2026-01-10',
                'contract_doc' => null,
                'address' => 'Nairobi',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $truckId = DB::table('fleet_vehicles')->where('name', 'Fleet TRUCK 70')->value('id');
        $motorcycleId = DB::table('fleet_vehicles')->where('name', 'like', '%MOTORCYCLE%')->value('id');

        if ($motorcycleId) {
            DB::table('fleet_vehicles')
                ->where('id', $motorcycleId)
                ->update(['name' => 'Fleet MOTORCYCLE 98', 'updated_at' => now()]);
        }

        $motorcycleId = DB::table('fleet_vehicles')->where('name', 'Fleet MOTORCYCLE 98')->value('id')
            ?: $motorcycleId;

        $now = now();
        $records = [];

        if ($motorcycleId) {
            $records[] = [
                'fleet_vehicle_id' => $motorcycleId,
                'fleet_vehicle_vendor_id' => $vendorId,
                'status' => 'Completed',
                'start_date' => '2026-01-10',
                'end_date' => '2026-01-11',
                'service_details' => 'Routine service and parts replacement',
                'total_cost' => 1500,
                'mechanic' => 'John',
                'priority' => 'Medium',
                'checklist' => null,
                'receipt_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($truckId) {
            $records[] = [
                'fleet_vehicle_id' => $truckId,
                'fleet_vehicle_vendor_id' => $vendorId,
                'status' => 'Completed',
                'start_date' => '2026-01-10',
                'end_date' => '2026-01-11',
                'service_details' => 'Engine inspection and oil change',
                'total_cost' => 1500,
                'mechanic' => 'John',
                'priority' => 'Medium',
                'checklist' => null,
                'receipt_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ($records as $record) {
            DB::table('fleet_maintenances')->updateOrInsert(
                [
                    'fleet_vehicle_id' => $record['fleet_vehicle_id'],
                    'start_date' => $record['start_date'],
                    'end_date' => $record['end_date'],
                ],
                $record
            );
        }
    }

    public function down(): void
    {
        DB::table('fleet_maintenances')
            ->where('start_date', '2026-01-10')
            ->where('mechanic', 'John')
            ->where('total_cost', 1500)
            ->delete();
    }
};
