<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_account_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->timestamps();
        });

        $now = now();

        DB::table('fleet_account_categories')->insert([
            ['name' => 'Trip Income', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'eb', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Phone', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Intercity', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cross Border', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Fuel', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Salary', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Trip Income', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_account_categories');
    }
};
