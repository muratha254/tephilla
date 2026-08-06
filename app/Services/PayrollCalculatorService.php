<?php

namespace App\Services;

use App\Models\PayrollSetting;

class PayrollCalculatorService
{
    protected $settings;

    public function __construct()
    {
        $this->settings = PayrollSetting::getSettings();
    }

    /**
     * Calculate PAYE (Pay As You Earn) tax based on configurable tax bands
     * 
     * @param float $taxableIncome
     * @return float
     */
    public function calculatePAYE(float $taxableIncome): float
    {
        $personalRelief = $this->settings->personal_relief;
        
        // Tax bands from settings
        $taxBands = [
            ['min' => $this->settings->paye_band1_min, 'max' => $this->settings->paye_band1_max, 'rate' => $this->settings->paye_band1_rate / 100],
            ['min' => $this->settings->paye_band2_min, 'max' => $this->settings->paye_band2_max, 'rate' => $this->settings->paye_band2_rate / 100],
            ['min' => $this->settings->paye_band3_min, 'max' => PHP_INT_MAX, 'rate' => $this->settings->paye_band3_rate / 100],
        ];
        
        $annualTaxableIncome = $taxableIncome * 12;
        $annualTax = 0;
        
        foreach ($taxBands as $band) {
            if ($annualTaxableIncome > $band['min']) {
                $taxableInBand = min($annualTaxableIncome, $band['max']) - $band['min'];
                $annualTax += $taxableInBand * $band['rate'];
            }
        }
        
        // Apply personal relief
        $annualTax = max(0, $annualTax - $personalRelief);
        
        // Return monthly PAYE
        return round($annualTax / 12, 2);
    }
    
    /**
     * Calculate SHA (Social Health Authority) contribution based on gross salary
     * 
     * @param float $grossSalary
     * @return float
     */
    public function calculateNHIF(float $grossSalary): float
    {
        // Get SHA bands from settings (keeping method name for backward compatibility)
        $nhifBands = $this->settings->nhif_bands ?? [];
        
        if (empty($nhifBands)) {
            // Fallback to default if no bands configured
            return 1700;
        }
        
        foreach ($nhifBands as $band) {
            if ($grossSalary >= $band['min'] && $grossSalary <= $band['max']) {
                return $band['amount'];
            }
        }
        
        // Return maximum if salary exceeds all bands
        return end($nhifBands)['amount'] ?? 1700;
    }
    
    /**
     * Calculate NSSF contribution (both employee and employer)
     * 
     * @param float $grossSalary
     * @return array ['employee' => float, 'employer' => float]
     */
    public function calculateNSSF(float $grossSalary): array
    {
        $tier1Limit = $this->settings->nssf_tier1_limit;
        $tier2Limit = $this->settings->nssf_tier2_limit;
        $contributionRate = $this->settings->nssf_rate / 100; // Convert percentage to decimal
        
        $pensionableEarnings = min($grossSalary, $tier2Limit);
        
        $employeeContribution = $pensionableEarnings * $contributionRate;
        $employerContribution = $pensionableEarnings * $contributionRate;
        
        return [
            'employee' => round($employeeContribution, 2),
            'employer' => round($employerContribution, 2),
        ];
    }
    
    /**
     * Calculate complete payroll breakdown
     * 
     * @param float $grossSalary
     * @param float $employerPension
     * @param float $employeePension
     * @param float $otherAdditions
     * @param float $otherDeductions
     * @param float $advanceSalary
     * @return array
     */
    public function calculatePayroll(
        float $grossSalary,
        float $employerPension = 0,
        float $employeePension = 0,
        float $otherAdditions = 0,
        float $otherDeductions = 0,
        float $advanceSalary = 0
    ): array {
        // Calculate statutory deductions
        $sha = $this->calculateNHIF($grossSalary); // SHA (Social Health Authority)
        $nssf = $this->calculateNSSF($grossSalary);
        
        // Calculate taxable income (gross salary minus NSSF employee contribution)
        $taxableIncome = $grossSalary - $nssf['employee'];
        
        // Calculate PAYE
        $paye = $this->calculatePAYE($taxableIncome);
        
        // Calculate total deductions (including advance salary)
        $totalDeductions = $paye + $sha + $nssf['employee'] + $employeePension + $otherDeductions + $advanceSalary;
        
        // Calculate net salary
        $netSalary = $grossSalary + $otherAdditions - $totalDeductions;
        
        // Calculate employer contributions
        $employerContributions = $nssf['employer'] + $employerPension;
        
        return [
            'gross_salary' => round($grossSalary, 2),
            'other_additions' => round($otherAdditions, 2),
            'paye' => round($paye, 2),
            'nhif' => round($sha, 2), // SHA contribution (keeping key as nhif for backward compatibility)
            'nssf_employee' => round($nssf['employee'], 2),
            'nssf_employer' => round($nssf['employer'], 2),
            'employee_pension' => round($employeePension, 2),
            'employer_pension' => round($employerPension, 2),
            'other_deductions' => round($otherDeductions, 2),
            'advance_salary' => round($advanceSalary, 2),
            'total_deductions' => round($totalDeductions, 2),
            'net_salary' => round($netSalary, 2),
            'employer_contributions' => round($employerContributions, 2),
        ];
    }
    
    /**
     * Get PAYE tax bands information
     * 
     * @return array
     */
    public function getPAYEBands(): array
    {
        return [
            ['band' => "First KES " . number_format($this->settings->paye_band1_max, 0), 'rate' => $this->settings->paye_band1_rate . '%', 'description' => "Annual income up to KES " . number_format($this->settings->paye_band1_max, 0)],
            ['band' => "Next KES " . number_format($this->settings->paye_band2_max - $this->settings->paye_band2_min, 0), 'rate' => $this->settings->paye_band2_rate . '%', 'description' => "Annual income from KES " . number_format($this->settings->paye_band2_min, 0) . " to KES " . number_format($this->settings->paye_band2_max, 0)],
            ['band' => "Above KES " . number_format($this->settings->paye_band3_min, 0), 'rate' => $this->settings->paye_band3_rate . '%', 'description' => "Annual income above KES " . number_format($this->settings->paye_band3_min, 0)],
            ['band' => 'Personal Relief', 'rate' => 'KES ' . number_format($this->settings->personal_relief, 0), 'description' => 'Monthly personal relief (KES ' . number_format($this->settings->personal_relief / 12, 0) . ')'],
        ];
    }
    
    /**
     * Get SHA (Social Health Authority) contribution table
     * 
     * @return array
     */
    public function getNHIFTable(): array
    {
        $nhifBands = $this->settings->nhif_bands ?? [];
        $table = [];
        
        foreach ($nhifBands as $band) {
            $table[] = [
                'salary_range' => number_format($band['min'], 0) . ' - ' . number_format($band['max'], 0),
                'contribution' => 'KES ' . number_format($band['amount'], 0),
            ];
        }
        
        return $table;
    }
    
    /**
     * Get NSSF contribution details
     * 
     * @return array
     */
    public function getNSSFDetails(): array
    {
        return [
            ['tier' => 'Tier I', 'pensionable_earnings' => 'KES 0 - ' . number_format($this->settings->nssf_tier1_limit, 0), 'rate' => $this->settings->nssf_rate . '%', 'max_contribution' => 'KES ' . number_format($this->settings->nssf_tier1_limit * ($this->settings->nssf_rate / 100), 0)],
            ['tier' => 'Tier II', 'pensionable_earnings' => 'KES ' . number_format($this->settings->nssf_tier1_limit, 0) . ' - ' . number_format($this->settings->nssf_tier2_limit, 0), 'rate' => $this->settings->nssf_rate . '%', 'max_contribution' => 'KES ' . number_format($this->settings->nssf_tier2_limit * ($this->settings->nssf_rate / 100), 0)],
            ['tier' => 'Maximum', 'pensionable_earnings' => 'KES ' . number_format($this->settings->nssf_tier2_limit, 0), 'rate' => $this->settings->nssf_rate . '%', 'max_contribution' => 'KES ' . number_format($this->settings->nssf_tier2_limit * ($this->settings->nssf_rate / 100), 0)],
            ['tier' => 'Note', 'pensionable_earnings' => 'Both employee and employer contribute ' . $this->settings->nssf_rate . '%', 'rate' => '', 'max_contribution' => ''],
        ];
    }
}
