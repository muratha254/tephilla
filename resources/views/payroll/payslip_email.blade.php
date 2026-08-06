<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .content { background: #f9f9f9; padding: 20px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>{{ str_replace('CENTER', 'CENTRE', $setting->nama_perusahaan ?? 'Company') }}</h2>
        </div>
        <div class="content">
            <p>Dear {{ $payroll->employee->name }},</p>
            
            <p>Please find attached your payslip for the period of <strong>{{ \Carbon\Carbon::parse($payroll->payroll_date)->format('F Y') }}</strong>.</p>
            
            <p><strong>Summary:</strong></p>
            <ul>
                <li>Gross Salary: KES {{ number_format($payroll->gross_salary, 2) }}</li>
                <li>Total Deductions: KES {{ number_format($payroll->paye + $payroll->nhif + $payroll->nssf_employee + $payroll->employee_pension + $payroll->other_deductions, 2) }}</li>
                <li><strong>Net Salary: KES {{ number_format($payroll->net_salary, 2) }}</strong></li>
            </ul>
            
            <p>If you have any questions regarding your payslip, please contact the HR department.</p>
            
            <p>Best regards,<br>
            {{ str_replace('CENTER', 'CENTRE', $setting->nama_perusahaan ?? 'HR Department') }}</p>
        </div>
    </div>
</body>
</html>




