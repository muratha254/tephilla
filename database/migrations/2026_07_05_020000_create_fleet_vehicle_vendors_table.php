<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_vehicle_vendors', function (Blueprint $table) {
            $table->id();
            $table->string('company');
            $table->string('contact_person');
            $table->string('mobile', 30);
            $table->date('contract_date')->nullable();
            $table->string('contract_doc')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('fleet_vehicle_vendors')->insert([
            'company' => 'Tarc Motors',
            'contact_person' => 'Ajaith',
            'mobile' => '88877845845',
            'contract_date' => '2026-01-10',
            'contract_doc' => null,
            'address' => 'Delhi',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_vehicle_vendors');
    }
};
