<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayrollsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->date('payroll_date');
            $table->enum('type', ['salary', 'maternity', 'bonus', 'allowance', 'other'])->default('salary');
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            
            // Salary Information
            $table->decimal('gross_salary', 15, 2)->default(0);
            $table->decimal('employer_pension', 15, 2)->default(0);
            $table->decimal('employee_pension', 15, 2)->default(0);
            $table->decimal('other_additions', 15, 2)->default(0);
            $table->decimal('other_deductions', 15, 2)->default(0);
            
            // Statutory Deductions
            $table->decimal('paye', 15, 2)->default(0);
            $table->decimal('nhif', 15, 2)->default(0);
            $table->decimal('nssf_employee', 15, 2)->default(0);
            $table->decimal('nssf_employer', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2)->default(0);
            
            // Employer Contributions
            $table->decimal('employer_contributions', 15, 2)->default(0);
            
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payrolls');
    }
}
