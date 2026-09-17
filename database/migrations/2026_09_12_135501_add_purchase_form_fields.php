<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPurchaseFormFields extends Migration
{
    public function up()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'due_date')) {
                $table->date('due_date')->nullable()->after('expected_date');
            }
            if (!Schema::hasColumn('purchase_orders', 'reference_no')) {
                $table->string('reference_no', 64)->nullable()->after('number');
            }
            if (!Schema::hasColumn('purchase_orders', 'cu_number')) {
                $table->string('cu_number', 64)->nullable()->after('reference_no');
            }
            if (!Schema::hasColumn('purchase_orders', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('purchase_orders', 'round_off')) {
                $table->decimal('round_off', 15, 2)->default(0)->after('tax_amount');
            }
            if (!Schema::hasColumn('purchase_orders', 'expense_amount')) {
                $table->decimal('expense_amount', 15, 2)->default(0)->after('round_off');
            }
            if (!Schema::hasColumn('purchase_orders', 'paid_amount')) {
                $table->decimal('paid_amount', 15, 2)->default(0)->after('total');
            }
            if (!Schema::hasColumn('purchase_orders', 'expenses_json')) {
                $table->json('expenses_json')->nullable()->after('paid_amount');
            }
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_order_items', 'discount_percent')) {
                $table->decimal('discount_percent', 8, 2)->default(0)->after('discount_amount');
            }
            if (!Schema::hasColumn('purchase_order_items', 'selling_price')) {
                $table->decimal('selling_price', 15, 2)->default(0)->after('unit_cost');
            }
            if (!Schema::hasColumn('purchase_order_items', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('line_total');
            }
        });
    }

    public function down()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            foreach (['due_date', 'reference_no', 'cu_number', 'attachment_path', 'round_off', 'expense_amount', 'paid_amount', 'expenses_json'] as $column) {
                if (Schema::hasColumn('purchase_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            foreach (['discount_percent', 'selling_price', 'expiry_date'] as $column) {
                if (Schema::hasColumn('purchase_order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
