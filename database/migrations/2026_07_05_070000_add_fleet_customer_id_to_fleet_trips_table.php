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
            if (! Schema::hasColumn('fleet_trips', 'fleet_customer_id')) {
                $table->foreignId('fleet_customer_id')
                    ->nullable()
                    ->after('fleet_driver_id')
                    ->constrained('fleet_customers')
                    ->nullOnDelete();
            }
        });

        if (! Schema::hasColumn('fleet_trips', 'fleet_customer_id')) {
            return;
        }

        $trips = DB::table('fleet_trips')->whereNull('fleet_customer_id')->get();

        foreach ($trips as $trip) {
            $customerId = DB::table('fleet_customers')
                ->where('name', $trip->customer_name)
                ->value('id');

            if ($customerId) {
                DB::table('fleet_trips')->where('id', $trip->id)->update([
                    'fleet_customer_id' => $customerId,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('fleet_trips', function (Blueprint $table) {
            if (Schema::hasColumn('fleet_trips', 'fleet_customer_id')) {
                $table->dropConstrainedForeignId('fleet_customer_id');
            }
        });
    }
};
