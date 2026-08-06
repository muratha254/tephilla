<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mobile', 30);
            $table->string('whatsapp', 30)->nullable();
            $table->boolean('whatsapp_same_as_mobile')->default(true);
            $table->string('email')->nullable();
            $table->string('password');
            $table->text('address');
            $table->boolean('whatsapp_notifications')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_customers');
    }
};
