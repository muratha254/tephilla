<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * withdrawal_number identifies a batch (one user action); multiple rows share it (one line per product).
 * A unique index on withdrawal_number incorrectly blocks multi-line withdrawals.
 */
class FixSupplierWithdrawalsWithdrawalNumberNotUnique extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_withdrawals')) {
            return;
        }

        Schema::table('supplier_withdrawals', function (Blueprint $table) {
            $table->dropUnique(['withdrawal_number']);
        });

        Schema::table('supplier_withdrawals', function (Blueprint $table) {
            $table->index('withdrawal_number');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('supplier_withdrawals')) {
            return;
        }

        Schema::table('supplier_withdrawals', function (Blueprint $table) {
            $table->dropIndex(['withdrawal_number']);
        });

        Schema::table('supplier_withdrawals', function (Blueprint $table) {
            $table->unique('withdrawal_number');
        });
    }
}
