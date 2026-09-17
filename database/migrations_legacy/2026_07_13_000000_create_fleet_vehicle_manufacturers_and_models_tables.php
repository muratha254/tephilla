<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_vehicle_manufacturers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('fleet_vehicle_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('fleet_vehicle_manufacturer_id')
                ->nullable()
                ->constrained('fleet_vehicle_manufacturers')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['name', 'fleet_vehicle_manufacturer_id'], 'fleet_vehicle_models_name_manufacturer_unique');
        });

        $now = now();

        $manufacturerIds = [];
        foreach (['Toyota', 'Tata', 'Ashok Leyland', 'Mahindra', 'Hyundai'] as $name) {
            $manufacturerIds[$name] = DB::table('fleet_vehicle_manufacturers')->insertGetId([
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $defaultManufacturerId = $manufacturerIds['Toyota'] ?? null;

        foreach (['Model X2', 'Model X5', 'Model X6', 'Model X7', 'Model X8'] as $modelName) {
            DB::table('fleet_vehicle_models')->insert([
                'name' => $modelName,
                'fleet_vehicle_manufacturer_id' => $defaultManufacturerId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_vehicle_models');
        Schema::dropIfExists('fleet_vehicle_manufacturers');
    }
};
