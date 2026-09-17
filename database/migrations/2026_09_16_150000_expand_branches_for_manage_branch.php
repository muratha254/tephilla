<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ExpandBranchesForManageBranch extends Migration
{
    public function up()
    {
        Schema::table('branches', function (Blueprint $table) {
            $cols = [
                'phone_alt' => fn () => $table->string('phone_alt', 40)->nullable()->after('phone'),
                'email' => fn () => $table->string('email')->nullable()->after('phone_alt'),
                'city' => fn () => $table->string('city', 100)->nullable()->after('address'),
                'postcode' => fn () => $table->string('postcode', 40)->nullable()->after('city'),
                'logo_path' => fn () => $table->string('logo_path')->nullable()->after('postcode'),
                'system_mode' => fn () => $table->string('system_mode', 64)->nullable()->after('logo_path'),
                'till1' => fn () => $table->string('till1', 64)->nullable(),
                'account1' => fn () => $table->string('account1', 64)->nullable(),
                'till2' => fn () => $table->string('till2', 64)->nullable(),
                'account2' => fn () => $table->string('account2', 64)->nullable(),
                'bank_account_name' => fn () => $table->string('bank_account_name', 120)->nullable(),
                'bank_name' => fn () => $table->string('bank_name', 120)->nullable(),
                'bank_account_no' => fn () => $table->string('bank_account_no', 64)->nullable(),
                'bank_branch' => fn () => $table->string('bank_branch', 120)->nullable(),
                'bank_code' => fn () => $table->string('bank_code', 40)->nullable(),
                'bank_swift' => fn () => $table->string('bank_swift', 40)->nullable(),
                'include_catering_levy' => fn () => $table->boolean('include_catering_levy')->default(false),
                'auto_receipt_amt_pos' => fn () => $table->boolean('auto_receipt_amt_pos')->default(false),
                'list_on_login' => fn () => $table->boolean('list_on_login')->default(true),
            ];

            foreach ($cols as $name => $add) {
                if (! Schema::hasColumn('branches', $name)) {
                    $add();
                }
            }
        });
    }

    public function down()
    {
        Schema::table('branches', function (Blueprint $table) {
            $drop = [
                'phone_alt', 'email', 'city', 'postcode', 'logo_path', 'system_mode',
                'till1', 'account1', 'till2', 'account2',
                'bank_account_name', 'bank_name', 'bank_account_no', 'bank_branch', 'bank_code', 'bank_swift',
                'include_catering_levy', 'auto_receipt_amt_pos', 'list_on_login',
            ];
            foreach ($drop as $col) {
                if (Schema::hasColumn('branches', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
