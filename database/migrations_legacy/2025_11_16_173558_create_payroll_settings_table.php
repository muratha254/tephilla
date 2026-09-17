<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreatePayrollSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->id();
            
            // PAYE Settings
            $table->decimal('paye_band1_min', 15, 2)->default(0);
            $table->decimal('paye_band1_max', 15, 2)->default(288000);
            $table->decimal('paye_band1_rate', 5, 2)->default(10.00);
            
            $table->decimal('paye_band2_min', 15, 2)->default(288000);
            $table->decimal('paye_band2_max', 15, 2)->default(388000);
            $table->decimal('paye_band2_rate', 5, 2)->default(25.00);
            
            $table->decimal('paye_band3_min', 15, 2)->default(388000);
            $table->decimal('paye_band3_rate', 5, 2)->default(30.00);
            
            $table->decimal('personal_relief', 15, 2)->default(2880); // Annual personal relief
            
            // NSSF Settings
            $table->decimal('nssf_tier1_limit', 15, 2)->default(6000);
            $table->decimal('nssf_tier2_limit', 15, 2)->default(18000);
            $table->decimal('nssf_rate', 5, 2)->default(6.00); // 6% for both employee and employer
            
            // SHA (Social Health Authority) Settings (stored as JSON for flexibility)
            $table->text('nhif_bands')->nullable(); // JSON structure for SHA bands (keeping column name for backward compatibility)
            
            $table->timestamps();
        });
        
        // Insert default values
        DB::table('payroll_settings')->insert([
            'paye_band1_min' => 0,
            'paye_band1_max' => 288000,
            'paye_band1_rate' => 10.00,
            'paye_band2_min' => 288000,
            'paye_band2_max' => 388000,
            'paye_band2_rate' => 25.00,
            'paye_band3_min' => 388000,
            'paye_band3_rate' => 30.00,
            'personal_relief' => 2880,
            'nssf_tier1_limit' => 6000,
            'nssf_tier2_limit' => 18000,
            'nssf_rate' => 6.00,
            'nhif_bands' => json_encode([
                ['min' => 0, 'max' => 5999, 'amount' => 150],
                ['min' => 6000, 'max' => 7999, 'amount' => 300],
                ['min' => 8000, 'max' => 11999, 'amount' => 400],
                ['min' => 12000, 'max' => 14999, 'amount' => 500],
                ['min' => 15000, 'max' => 19999, 'amount' => 600],
                ['min' => 20000, 'max' => 24999, 'amount' => 750],
                ['min' => 25000, 'max' => 29999, 'amount' => 850],
                ['min' => 30000, 'max' => 34999, 'amount' => 900],
                ['min' => 35000, 'max' => 39999, 'amount' => 950],
                ['min' => 40000, 'max' => 44999, 'amount' => 1000],
                ['min' => 45000, 'max' => 49999, 'amount' => 1100],
                ['min' => 50000, 'max' => 59999, 'amount' => 1200],
                ['min' => 60000, 'max' => 69999, 'amount' => 1300],
                ['min' => 70000, 'max' => 79999, 'amount' => 1400],
                ['min' => 80000, 'max' => 89999, 'amount' => 1500],
                ['min' => 90000, 'max' => 99999, 'amount' => 1600],
                ['min' => 100000, 'max' => 999999999, 'amount' => 1700],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payroll_settings');
    }
}
