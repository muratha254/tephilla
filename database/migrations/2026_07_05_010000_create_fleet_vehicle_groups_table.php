<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_vehicle_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('Active');
            $table->timestamps();
        });

        $now = now();

        DB::table('fleet_vehicle_groups')->insert([
            [
                'name' => 'North Fleet',
                'description' => 'Vehicles assigned to northern routes.',
                'status' => 'Active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'South Fleet',
                'description' => 'Vehicles assigned to southern routes.',
                'status' => 'Active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'City Delivery',
                'description' => 'Urban delivery vehicles.',
                'status' => 'Active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Long Haul',
                'description' => 'Long-distance transport vehicles.',
                'status' => 'Active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_vehicle_groups');
    }
};
