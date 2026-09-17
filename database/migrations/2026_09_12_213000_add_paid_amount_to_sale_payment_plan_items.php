<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaidAmountToSalePaymentPlanItems extends Migration
{
    public function up()
    {
        Schema::table('sale_payment_plan_items', function (Blueprint $table) {
            $table->decimal('paid_amount', 15, 2)->default(0)->after('amount');
        });
    }

    public function down()
    {
        Schema::table('sale_payment_plan_items', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });
    }
}
