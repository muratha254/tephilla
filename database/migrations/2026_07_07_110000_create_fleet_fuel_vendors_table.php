<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_fuel_vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->timestamps();
        });

        DB::table('fleet_fuel_vendors')->insert([
            ['name' => 'Tata Fuels', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Biofec Fules', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Arc Petrofules', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('fleet_vehicle_fuel_refills', function (Blueprint $table) {
            if (Schema::hasColumn('fleet_vehicle_fuel_refills', 'fleet_vehicle_vendor_id')) {
                $table->dropConstrainedForeignId('fleet_vehicle_vendor_id');
            }

            $table->foreignId('fleet_fuel_vendor_id')
                ->nullable()
                ->after('source')
                ->constrained('fleet_fuel_vendors')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fleet_vehicle_fuel_refills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fleet_fuel_vendor_id');
            $table->foreignId('fleet_vehicle_vendor_id')->nullable()->constrained('fleet_vehicle_vendors')->nullOnDelete();
        });

        Schema::dropIfExists('fleet_fuel_vendors');
    }
};
