<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_geofences', function (Blueprint $table) {
            $table->id();
            $table->string('location_label');
            $table->string('shape_type', 20);
            $table->json('geometry');
            $table->decimal('center_lat', 10, 7)->nullable();
            $table->decimal('center_lng', 10, 7)->nullable();
            $table->timestamps();
        });

        DB::table('fleet_geofences')->insert([
            'location_label' => 'Delhi, India',
            'shape_type' => 'marker',
            'geometry' => json_encode([
                'lat' => 28.613939,
                'lng' => 77.209023,
                'label' => 'Delhi, India',
            ]),
            'center_lat' => 28.613939,
            'center_lng' => 77.209023,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_geofences');
    }
};
