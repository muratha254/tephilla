<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ExpandExpensesTable extends Migration
{
    public function up()
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'entry_type')) {
                $table->string('entry_type', 20)->default('direct')->after('user_id');
            }
            if (! Schema::hasColumn('expenses', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable()->after('entry_type')->constrained('suppliers')->nullOnDelete();
            }
            if (! Schema::hasColumn('expenses', 'voucher_no')) {
                $table->string('voucher_no')->nullable()->after('number');
            }
            if (! Schema::hasColumn('expenses', 'paying_account')) {
                $table->string('paying_account', 64)->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('expenses', 'vat_type')) {
                $table->string('vat_type', 20)->default('exempt')->after('paying_account');
            }
            if (! Schema::hasColumn('expenses', 'vat_amount')) {
                $table->decimal('vat_amount', 15, 2)->default(0)->after('vat_type');
            }
            if (! Schema::hasColumn('expenses', 'paid_amount')) {
                $table->decimal('paid_amount', 15, 2)->default(0)->after('amount');
            }
            if (! Schema::hasColumn('expenses', 'status')) {
                $table->string('status', 20)->default('paid')->after('paid_amount');
            }
        });
    }

    public function down()
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'vendor_id')) {
                $table->dropConstrainedForeignId('vendor_id');
            }
            foreach (['entry_type', 'voucher_no', 'paying_account', 'vat_type', 'vat_amount', 'paid_amount', 'status'] as $column) {
                if (Schema::hasColumn('expenses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
