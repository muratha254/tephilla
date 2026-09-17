<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_mechanics', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('specialty', 100);
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->timestamps();
        });

        DB::table('fleet_mechanics')->insert([
            [
                'name' => 'John',
                'specialty' => 'Engine',
                'email' => 'john@example.com',
                'phone' => '+254712345678',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Mike',
                'specialty' => 'Painting',
                'email' => 'mike@example.com',
                'phone' => '+254798765432',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_mechanics');
    }
};
