<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddItemFormFieldsToProductsTable extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('for_sale')->default(true)->after('is_active');
            $table->boolean('tax_inclusive')->default(true)->after('for_sale');
            $table->date('expiry_date')->nullable()->after('tax_inclusive');
            $table->decimal('profit_margin', 8, 2)->default(0)->after('expiry_date');
            $table->decimal('promo_price', 15, 2)->nullable()->after('wholesale_price');
            $table->decimal('sales_commission', 8, 2)->default(0)->after('promo_price');
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'for_sale',
                'tax_inclusive',
                'expiry_date',
                'profit_margin',
                'promo_price',
                'sales_commission',
            ]);
        });
    }
}
