<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddItemProfileAndConversion extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('company_id')->constrained('products')->nullOnDelete();
            $table->decimal('conversion_rate', 15, 4)->nullable()->after('parent_id');
        });

        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('batch_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('retail_price', 15, 2)->default(0);
            $table->decimal('wholesale_price', 15, 2)->default(0);
            $table->decimal('promo_price', 15, 2)->default(0);
            $table->decimal('stocked_qty', 15, 4)->default(0);
            $table->decimal('balance_qty', 15, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'branch_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_batches');

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('conversion_rate');
        });
    }
}
