<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateManufacturingTables extends Migration
{
    public function up()
    {
        Schema::create('boms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->decimal('expected_production', 15, 4)->default(0);
            $table->decimal('production_cost', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained('boms')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('sub_total', 15, 2)->default(0);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bom_id')->constrained('boms')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->date('production_date');
            $table->text('description')->nullable();
            $table->decimal('expected_production', 15, 4)->default(0);
            $table->decimal('actual_production', 15, 4)->default(0);
            $table->decimal('production_cost', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('production_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_id')->constrained('productions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('sub_total', 15, 2)->default(0);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('packaging_setups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('packaging_setup_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('packaging_setup_id')->constrained('packaging_setups')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 4)->default(1);
            $table->timestamps();
        });

        Schema::create('packagings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('packaging_date');
            $table->decimal('qty_packaged', 15, 4)->default(0);
            $table->decimal('grand_total', 15, 4)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('packaging_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('packaging_id')->constrained('packagings')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('unit_control')->nullable();
            $table->decimal('qty_to_package', 15, 4)->default(0);
            $table->decimal('stock_packaged', 15, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('packaging_items');
        Schema::dropIfExists('packagings');
        Schema::dropIfExists('packaging_setup_items');
        Schema::dropIfExists('packaging_setups');
        Schema::dropIfExists('production_items');
        Schema::dropIfExists('productions');
        Schema::dropIfExists('bom_items');
        Schema::dropIfExists('boms');
    }
}
