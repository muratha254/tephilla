<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_batches', function (Blueprint $table) {
            $table->increments('id');
            $table->string('po_number', 50)->unique();
            $table->unsignedInteger('supplier_id');
            $table->date('order_date')->useCurrent();
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')
                ->references('id_supplier')
                ->on('supplier')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });

        Schema::create('purchase_order_batch_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('batch_id');
            $table->unsignedInteger('produk_id');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total_price', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('batch_id')
                ->references('id')
                ->on('purchase_order_batches')
                ->onDelete('cascade');

            $table->foreign('produk_id')
                ->references('id_produk')
                ->on('produk')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_batch_items');
        Schema::dropIfExists('purchase_order_batches');
    }
};






