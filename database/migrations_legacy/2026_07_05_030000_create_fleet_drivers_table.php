<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mobile', 30);
            $table->string('email')->unique();
            $table->string('status')->default('Active');
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('password');
            $table->date('date_of_joining');
            $table->string('photo_path')->nullable();

            $table->string('license_number')->nullable();
            $table->date('license_expiry')->nullable();
            $table->string('license_doc')->nullable();
            $table->string('id_number')->nullable();
            $table->string('id_doc')->nullable();

            $table->string('employment_type')->nullable();
            $table->string('department')->nullable();
            $table->string('employee_id')->nullable();
            $table->date('contract_end_date')->nullable();

            $table->decimal('salary', 12, 2)->nullable();
            $table->string('payment_type')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_drivers');
    }
};
