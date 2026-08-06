<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        $now = now();

        foreach (['Car', 'Truck', 'Van', 'Bus', 'Motorcycle', 'Trailer'] as $name) {
            DB::table('fleet_vehicle_types')->insert([
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_vehicle_types');
    }
};
