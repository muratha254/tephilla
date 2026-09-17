<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_withdrawals') && ! Schema::hasColumn('supplier_withdrawals', 'receipt_no')) {
            Schema::table('supplier_withdrawals', function (Blueprint $table) {
                $table->string('receipt_no', 120)->nullable()->after('withdrawal_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('supplier_withdrawals') && Schema::hasColumn('supplier_withdrawals', 'receipt_no')) {
            Schema::table('supplier_withdrawals', function (Blueprint $table) {
                $table->dropColumn('receipt_no');
            });
        }
    }
};
