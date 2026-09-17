<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('supplier_id');
            $table->unsignedInteger('produk_id');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price', 15, 2)->default(0);
            $table->date('order_date')->useCurrent();
            $table->enum('status', ['pending', 'ordered', 'received'])->default('pending');
            $table->timestamps();

            $table->foreign('supplier_id')->references('id_supplier')->on('supplier')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('produk_id')->references('id_produk')->on('produk')->onUpdate('cascade')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};

