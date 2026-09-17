<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_received', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('batch_id');
            $table->string('reference_number', 100)->unique();
            $table->date('received_date');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamps();

            $table->foreign('batch_id')
                ->references('id')
                ->on('purchase_order_batches')
                ->onDelete('cascade');

            $table->foreign('received_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });

        Schema::create('goods_received_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('goods_received_id');
            $table->unsignedInteger('batch_item_id');
            $table->unsignedInteger('produk_id');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('goods_received_id')
                ->references('id')
                ->on('goods_received')
                ->onDelete('cascade');

            $table->foreign('batch_item_id')
                ->references('id')
                ->on('purchase_order_batch_items')
                ->onDelete('cascade');

            $table->foreign('produk_id')
                ->references('id_produk')
                ->on('produk')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_received_items');
        Schema::dropIfExists('goods_received');
    }
};






