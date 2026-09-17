<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produk_edit_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_produk')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name', 191)->nullable();
            $table->json('changes');
            $table->string('source', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produk_edit_log');
    }
};
