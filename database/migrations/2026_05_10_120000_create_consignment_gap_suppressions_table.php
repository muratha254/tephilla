<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignment_gap_suppressions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('penjualan_id');
            $table->unsignedInteger('produk_id');
            $table->unsignedInteger('supplier_id');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['penjualan_id', 'produk_id', 'supplier_id'], 'cgs_penjualan_produk_supplier_uq');
            $table->index('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_gap_suppressions');
    }
};
