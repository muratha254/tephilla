<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('registration_number')->unique();
            $table->string('name')->nullable();
            $table->string('type');
            $table->string('color', 20)->default('#D6E1F3');
            $table->string('status')->default('Active');
            $table->string('fuel_type')->nullable();
            $table->decimal('fuel_efficiency', 8, 2)->nullable();
            $table->decimal('opening_fuel', 8, 2)->nullable();
            $table->decimal('current_fuel', 8, 2)->nullable();
            $table->decimal('fuel_capacity', 8, 2)->default(50);
            $table->string('vehicle_group')->nullable();
            $table->string('driver')->nullable();
            $table->string('model');
            $table->string('manufacturer');
            $table->string('image_path')->nullable();

            $table->string('chassis_number')->nullable();
            $table->string('engine_number')->nullable();
            $table->date('registration_date')->nullable();
            $table->date('registration_expiry')->nullable();
            $table->string('insurance_provider')->nullable();
            $table->string('insurance_policy_no')->nullable();
            $table->date('insurance_expiry')->nullable();
            $table->string('fitness_certificate_no')->nullable();
            $table->date('fitness_expiry')->nullable();

            $table->string('owner_type')->default('Owned');
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->date('lease_start')->nullable();
            $table->date('lease_end')->nullable();

            $table->string('gps_device_id')->nullable();
            $table->string('gps_imei')->nullable();
            $table->string('gps_sim_number')->nullable();
            $table->boolean('gps_enabled')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_vehicles');
    }
};
