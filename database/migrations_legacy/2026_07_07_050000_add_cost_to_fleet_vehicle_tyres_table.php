<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_vehicle_tyres', function (Blueprint $table) {
            $table->decimal('cost', 12, 2)->nullable()->after('install_odometer');
        });
    }

    public function down(): void
    {
        Schema::table('fleet_vehicle_tyres', function (Blueprint $table) {
            $table->dropColumn('cost');
        });
    }
};
