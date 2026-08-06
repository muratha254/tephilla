<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_trips', function (Blueprint $table) {
            $table->decimal('billing_quantity', 12, 3)->nullable()->after('billing_type');
            $table->decimal('billing_rate', 12, 2)->nullable()->after('billing_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('fleet_trips', function (Blueprint $table) {
            $table->dropColumn(['billing_quantity', 'billing_rate']);
        });
    }
};
