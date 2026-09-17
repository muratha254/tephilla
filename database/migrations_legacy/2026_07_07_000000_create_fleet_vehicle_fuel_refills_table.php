<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_vehicle_fuel_refills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_vehicle_id')->constrained('fleet_vehicles')->cascadeOnDelete();
            $table->date('refill_date');
            $table->decimal('liters', 10, 2);
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('payment_method', 50)->default('Cash');
            $table->string('reference_no', 100)->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_vehicle_fuel_refills');
    }
};
