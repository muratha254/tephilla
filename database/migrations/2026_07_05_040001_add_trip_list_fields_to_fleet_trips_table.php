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
            $table->string('trip_code')->nullable()->unique()->after('id');
            $table->string('customer_phone', 30)->nullable()->after('customer_name');
            $table->time('start_time')->nullable()->after('start_date');
            $table->time('end_time')->nullable()->after('end_date');
            $table->string('return_trip_of')->nullable()->after('status');
        });

        $year = date('Y');
        $trips = DB::table('fleet_trips')->whereNull('trip_code')->orderBy('id')->get();
        foreach ($trips as $trip) {
            DB::table('fleet_trips')->where('id', $trip->id)->update([
                'trip_code' => sprintf('OT-%s-%03d', $year, $trip->id),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('fleet_trips', function (Blueprint $table) {
            $table->dropColumn(['trip_code', 'customer_phone', 'start_time', 'end_time', 'return_trip_of']);
        });
    }
};
