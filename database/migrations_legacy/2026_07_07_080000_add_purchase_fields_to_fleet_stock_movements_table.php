<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_stock_movements', function (Blueprint $table) {
            $table->string('purchased_from', 150)->nullable()->after('unit_price');
            $table->string('payment_status', 30)->nullable()->after('purchased_from');
            $table->string('description')->nullable()->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('fleet_stock_movements', function (Blueprint $table) {
            $table->dropColumn(['purchased_from', 'payment_status', 'description']);
        });
    }
};
