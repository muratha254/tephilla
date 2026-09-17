<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSupplierPaymentFields extends Migration
{
    public function up()
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (! Schema::hasColumn('suppliers', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('suppliers', 'apply_withholding')) {
                $table->boolean('apply_withholding')->default(false)->after('opening_balance');
            }
            if (! Schema::hasColumn('suppliers', 'migration_account')) {
                $table->string('migration_account', 64)->nullable()->after('apply_withholding');
            }
            if (! Schema::hasColumn('suppliers', 'till_number')) {
                $table->string('till_number', 64)->nullable()->after('migration_account');
            }
            if (! Schema::hasColumn('suppliers', 'paybill_number')) {
                $table->string('paybill_number', 64)->nullable()->after('till_number');
            }
            if (! Schema::hasColumn('suppliers', 'mpesa_account_name')) {
                $table->string('mpesa_account_name', 128)->nullable()->after('paybill_number');
            }
            if (! Schema::hasColumn('suppliers', 'bank_account_name')) {
                $table->string('bank_account_name', 128)->nullable()->after('mpesa_account_name');
            }
            if (! Schema::hasColumn('suppliers', 'bank_account_number')) {
                $table->string('bank_account_number', 64)->nullable()->after('bank_account_name');
            }
            if (! Schema::hasColumn('suppliers', 'bank_name')) {
                $table->string('bank_name', 128)->nullable()->after('bank_account_number');
            }
            if (! Schema::hasColumn('suppliers', 'bank_branch')) {
                $table->string('bank_branch', 128)->nullable()->after('bank_name');
            }
        });
    }

    public function down()
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (Schema::hasColumn('suppliers', 'branch_id')) {
                $table->dropConstrainedForeignId('branch_id');
            }
            foreach ([
                'apply_withholding', 'migration_account', 'till_number', 'paybill_number',
                'mpesa_account_name', 'bank_account_name', 'bank_account_number', 'bank_name', 'bank_branch',
            ] as $column) {
                if (Schema::hasColumn('suppliers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
