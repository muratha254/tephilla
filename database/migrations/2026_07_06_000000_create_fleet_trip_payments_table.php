<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_trip_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_trip_id')->constrained('fleet_trips')->cascadeOnDelete();
            $table->foreignId('fleet_customer_id')->nullable()->constrained('fleet_customers')->nullOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 50)->default('Cash');
            $table->string('reference_no', 100)->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_trip_payments');
    }
};
