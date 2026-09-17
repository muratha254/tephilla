<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_vehicle_id')->constrained('fleet_vehicles')->cascadeOnDelete();
            $table->foreignId('fleet_vehicle_vendor_id')->nullable()->constrained('fleet_vehicle_vendors')->nullOnDelete();
            $table->string('status', 30)->default('Planned');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('service_details');
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->string('mechanic')->nullable();
            $table->string('priority', 20)->default('Medium');
            $table->json('checklist')->nullable();
            $table->string('receipt_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_maintenances');
    }
};
