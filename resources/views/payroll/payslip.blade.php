<!DOCTYPE html>
<html>
<head>
    <title>Payslip - {{ $payroll->employee->name ?? 'Employee' }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header img { width: 80px; height: 80px; }
        .company-info { margin-bottom: 20px; }
        .employee-info { margin-bottom: 20px; }
        .info-row { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f0f0f0; font-weight: bold; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        .section { margin: 20px 0; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        @if($setting && file_exists(public_path($setting->path_logo)))
        <img src="{{ public_path($setting->path_logo) }}" alt="Logo">
        @endif
        <h2>{{ str_replace('CENTER', 'CENTRE', $setting->nama_perusahaan ?? 'Company Name') }}</h2>
        <h3>PAYSLIP</h3>
    </div>

    <div class="company-info">
        <div class="info-row"><strong>Company:</strong> {{ str_replace('CENTER', 'CENTRE', $setting->nama_perusahaan ?? 'N/A') }}</div>
        @if($setting && $setting->alamat)
        <div class="info-row"><strong>Address:</strong> {{ $setting->alamat }}</div>
        @endif
        @if($setting && $setting->telepon)
        <div class="info-row"><strong>Phone:</strong> {{ $setting->telepon }}</div>
        @endif
    </div>

    <div class="employee-info">
        <div class="info-row"><strong>Employee Name:</strong> {{ $payroll->employee->name ?? 'N/A' }}</div>
        <div class="info-row"><strong>ID Number:</strong> {{ $payroll->employee->id_number ?? 'N/A' }}</div>
        <div class="info-row"><strong>KRA PIN:</strong> {{ $payroll->employee->kra_pin ?? 'N/A' }}</div>
        <div class="info-row"><strong>NSSF Number:</strong> {{ $payroll->employee->nssf_number ?? 'N/A' }}</div>
        <div class="info-row"><strong>Payroll Date:</strong> {{ \Carbon\Carbon::parse($payroll->payroll_date)->format('d F Y') }}</div>
        <div class="info-row"><strong>Pay Period:</strong> {{ \Carbon\Carbon::parse($payroll->payroll_date)->format('F Y') }}</div>
    </div>

    <div class="section">
        <h4>EARNINGS</h4>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Amount (KES)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Gross Salary</td>
                    <td class="text-right">{{ number_format($payroll->gross_salary, 2) }}</td>
                </tr>
                @if($payroll->other_additions > 0)
                <tr>
                    <td>Other Additions</td>
                    <td class="text-right">{{ number_format($payroll->other_additions, 2) }}</td>
                </tr>
                @endif
                <tr class="text-bold">
                    <td>Total Earnings</td>
                    <td class="text-right">{{ number_format($payroll->gross_salary + $payroll->other_additions, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h4>DEDUCTIONS</h4>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Amount (KES)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>PAYE</td>
                    <td class="text-right">{{ number_format($payroll->paye, 2) }}</td>
                </tr>
                <tr>
                    <td>SHA (Social Health Authority)</td>
                    <td class="text-right">{{ number_format($payroll->nhif, 2) }}</td>
                </tr>
                <tr>
                    <td>NSSF (Employee Contribution)</td>
                    <td class="text-right">{{ number_format($payroll->nssf_employee, 2) }}</td>
                </tr>
                @if($payroll->employee_pension > 0)
                <tr>
                    <td>Employee Pension</td>
                    <td class="text-right">{{ number_format($payroll->employee_pension, 2) }}</td>
                </tr>
                @endif
                @if($payroll->other_deductions > 0)
                <tr>
                    <td>Other Deductions</td>
                    <td class="text-right">{{ number_format($payroll->other_deductions, 2) }}</td>
                </tr>
                @endif
                @if($payroll->advance_salary > 0)
                <tr>
                    <td>Advance Salary</td>
                    <td class="text-right text-warning">{{ number_format($payroll->advance_salary, 2) }}</td>
                </tr>
                @endif
                <tr class="text-bold">
                    <td>Total Deductions</td>
                    <td class="text-right">{{ number_format($payroll->paye + $payroll->nhif + $payroll->nssf_employee + $payroll->employee_pension + $payroll->other_deductions + ($payroll->advance_salary ?? 0), 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h4>NET PAY</h4>
        <table>
            <tr class="text-bold" style="background: #e8f5e9;">
                <td style="font-size: 16px;">Net Salary</td>
                <td class="text-right" style="font-size: 16px;">KES {{ number_format($payroll->net_salary, 2) }}</td>
            </tr>
        </table>
    </div>

    @if($payroll->notes)
    <div class="section">
        <h4>NOTES</h4>
        <p>{{ $payroll->notes }}</p>
    </div>
    @endif

    <div class="footer">
        <p>This is a computer-generated payslip. No signature required.</p>
        <p>Generated on: {{ date('d F Y, h:i A') }}</p>
    </div>
</body>
</html>


