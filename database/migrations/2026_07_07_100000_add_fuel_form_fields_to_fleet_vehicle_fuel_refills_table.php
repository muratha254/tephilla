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
            $table->string('fuel_type', 50)->nullable()->after('fleet_driver_id');
            $table->string('source', 20)->default('tank')->after('fuel_type');
            $table->foreignId('fleet_vehicle_vendor_id')->nullable()->after('source')->constrained('fleet_vehicle_vendors')->nullOnDelete();
            $table->foreignId('fleet_stock_item_id')->nullable()->after('fleet_vehicle_vendor_id')->constrained('fleet_stock_items')->nullOnDelete();
            $table->string('receipt_path')->nullable()->after('notes');
        });

        $exists = DB::table('fleet_stock_items')
            ->where('name', 'like', '%Diesel%')
            ->orWhere('name', 'like', '%Fuel%')
            ->exists();

        if (! $exists) {
            DB::table('fleet_stock_items')->insert([
                'name' => 'Diesel Fuel',
                'description' => 'Bulk diesel fuel for fleet tank',
                'quantity' => 855,
                'unit_price' => 185,
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('fleet_vehicle_fuel_refills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fleet_vehicle_vendor_id');
            $table->dropConstrainedForeignId('fleet_stock_item_id');
            $table->dropColumn(['fuel_type', 'source', 'receipt_path']);
        });
    }
};
