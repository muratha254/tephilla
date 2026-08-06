<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDiscountTypeToPenjualanTable extends Migration
{
    public function up()
    {
        Schema::table('penjualan', function (Blueprint $table) {
            $table->string('discount_type', 20)->default('percentage')->after('diskon');
            $table->decimal('discount_amount', 15, 2)->nullable()->after('discount_type');
        });
    }

    public function down()
    {
        Schema::table('penjualan', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_amount']);
        });
    }
}
