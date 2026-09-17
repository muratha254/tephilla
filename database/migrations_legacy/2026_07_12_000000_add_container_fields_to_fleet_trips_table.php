<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_trips', function (Blueprint $table) {
            $table->string('container_number', 50)->nullable()->after('drop_location');
            $table->string('container_empty_drop_point')->nullable()->after('container_number');
        });
    }

    public function down(): void
    {
        Schema::table('fleet_trips', function (Blueprint $table) {
            $table->dropColumn(['container_number', 'container_empty_drop_point']);
        });
    }
};
