<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ExpandHrEmployeesTable extends Migration
{
    public function up()
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('employee_code');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
            $table->date('joining_date')->nullable()->after('last_name');
            $table->foreignId('role_id')->nullable()->after('category_id')->constrained('roles')->nullOnDelete();
            $table->string('national_id')->nullable()->after('phone');
            $table->string('alt_phone')->nullable()->after('national_id');
            $table->string('kra_pin')->nullable()->after('alt_phone');
            $table->string('shif_no')->nullable()->after('kra_pin');
            $table->string('nssf_no')->nullable()->after('shif_no');
            $table->string('gender')->nullable()->after('nssf_no');
            $table->string('marital_status')->nullable()->after('gender');
            $table->string('payment_period')->default('Monthly')->after('marital_status');
            $table->decimal('basic_salary', 15, 2)->default(0)->after('payment_period');
            $table->decimal('advance_salary_limit', 15, 2)->default(0)->after('basic_salary');
            $table->unsignedInteger('leave_counts')->default(0)->after('advance_salary_limit');
            $table->string('county')->nullable()->after('leave_counts');
            $table->string('postcode')->nullable()->after('county');
            $table->string('home_address')->nullable()->after('postcode');
            $table->string('bank_account_name')->nullable()->after('home_address');
            $table->string('bank_account_number')->nullable()->after('bank_account_name');
            $table->string('bank_name')->nullable()->after('bank_account_number');
            $table->string('bank_branch')->nullable()->after('bank_name');
            $table->boolean('apply_paye')->default(true)->after('bank_branch');
            $table->boolean('apply_shif')->default(true)->after('apply_paye');
            $table->boolean('apply_nssf')->default(true)->after('apply_shif');
            $table->boolean('apply_housing_levy')->default(true)->after('apply_nssf');
            $table->string('payroll_number')->nullable()->after('apply_housing_levy');
        });
    }

    public function down()
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn([
                'first_name', 'middle_name', 'last_name', 'joining_date',
                'national_id', 'alt_phone', 'kra_pin', 'shif_no', 'nssf_no',
                'gender', 'marital_status', 'payment_period', 'basic_salary',
                'advance_salary_limit', 'leave_counts', 'county', 'postcode',
                'home_address', 'bank_account_name', 'bank_account_number',
                'bank_name', 'bank_branch', 'apply_paye', 'apply_shif',
                'apply_nssf', 'apply_housing_levy', 'payroll_number',
            ]);
        });
    }
}
