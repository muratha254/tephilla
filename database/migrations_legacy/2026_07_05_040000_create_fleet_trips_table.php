<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_trips', function (Blueprint $table) {
            $table->id();
            $table->string('trip_type')->default('Single Trip');
            $table->foreignId('fleet_vehicle_id')->nullable()->constrained('fleet_vehicles')->nullOnDelete();
            $table->foreignId('fleet_driver_id')->nullable()->constrained('fleet_drivers')->nullOnDelete();
            $table->string('customer_name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('pickup_location');
            $table->string('drop_location');
            $table->json('additional_stops')->nullable();
            $table->string('billing_type')->default('Fixed');
            $table->decimal('base_amount', 12, 2)->default(0);
            $table->string('tax_type')->default('No Tax');
            $table->string('coupon_code')->nullable();
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->string('status')->default('Booked');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_trips');
    }
};
