<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->index('status', 'employees_status_index');
            $table->index('id_number', 'employees_id_number_index');
            $table->index('employer_number', 'employees_employer_number_index');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->index('employee_id', 'payrolls_employee_id_index');
            $table->index('status', 'payrolls_status_index');
            $table->index('payroll_date', 'payrolls_payroll_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('employees_status_index');
            $table->dropIndex('employees_id_number_index');
            $table->dropIndex('employees_employer_number_index');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropIndex('payrolls_employee_id_index');
            $table->dropIndex('payrolls_status_index');
            $table->dropIndex('payrolls_payroll_date_index');
        });
    }
};













