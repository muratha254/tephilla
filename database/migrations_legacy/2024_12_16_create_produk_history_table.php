<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Safely (re)create the produk_history table without a strict foreign key
        // to avoid migration failures on existing databases.
        Schema::dropIfExists('produk_history');

        Schema::create('produk_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_produk');
            $table->integer('previous_stock');
            $table->integer('restock_amount');
            $table->integer('current_stock');
            $table->string('type')->default('restock'); // restock, purchase, etc.
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign key removed to prevent errors on existing schemas.
            // Logical relationship to produk is still via id_produk.
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('produk_history');
    }
};
