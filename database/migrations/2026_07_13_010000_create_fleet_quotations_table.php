<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number')->unique();
            $table->foreignId('fleet_customer_id')->constrained('fleet_customers')->cascadeOnDelete();
            $table->foreignId('fleet_trip_id')->nullable()->constrained('fleet_trips')->nullOnDelete();
            $table->string('trip_reference')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('status')->default('Draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_quotations');
    }
};
