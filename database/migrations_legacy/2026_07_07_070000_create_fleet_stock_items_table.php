<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_stock_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('fleet_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_stock_item_id')->constrained('fleet_stock_items')->cascadeOnDelete();
            $table->string('type', 30);
            $table->integer('quantity_change');
            $table->unsignedInteger('quantity_after');
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        DB::table('fleet_stock_items')->insert([
            [
                'name' => 'Engine Oil',
                'description' => 'Common engine oil for all vehicles',
                'quantity' => 112,
                'unit_price' => 185,
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Tyre',
                'description' => 'Tyre Size 145/80 R12',
                'quantity' => 50,
                'unit_price' => 2850,
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_stock_movements');
        Schema::dropIfExists('fleet_stock_items');
    }
};
