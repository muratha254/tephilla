<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_trips', function (Blueprint $table) {
            $table->decimal('distance_km', 8, 2)->nullable()->after('drop_location');
        });

        $trips = DB::table('fleet_trips')->orderBy('id')->get();
        $distances = [5.25, 4.80, 6.10, 3.90, 7.15, 5.60, 4.25, 6.80, 5.02, 4.45, 3.20];

        foreach ($trips as $index => $trip) {
            DB::table('fleet_trips')->where('id', $trip->id)->update([
                'distance_km' => $distances[$index % count($distances)],
                'base_amount' => $trip->base_amount > 0 ? $trip->base_amount : 1200,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('fleet_trips', function (Blueprint $table) {
            $table->dropColumn('distance_km');
        });
    }
};
