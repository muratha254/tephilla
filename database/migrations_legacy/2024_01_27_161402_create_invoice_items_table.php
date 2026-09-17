<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoiceItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->string('uniqid')->nullable();
            $table->string('status')->nullable();
            $table->double('quantity')->nullable();
            $table->double('amount')->nullable();
            $table->double('discount')->nullable();
            $table->double('balance')->nullable();

            $table->foreignId('produk_id')->nullable();
            $table->foreignId('supplier_id')->nullable();
            $table->foreignId('invoice_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('invoice_items');
    }
}
