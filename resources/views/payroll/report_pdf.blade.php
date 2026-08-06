<!DOCTYPE html>
<html>
<head>
    <title>Payroll Report - {{ date('d/m/Y', strtotime($startDate)) }} to {{ date('d/m/Y', strtotime($endDate)) }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header img { width: 60px; height: 60px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #f0f0f0; font-weight: bold; }
        .text-right { text-align: right; }
        tfoot td { font-weight: bold; background: #e9e9e9; }
        .summary { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="header">
        @if($setting && file_exists(public_path($setting->path_logo)))
        <img src="{{ public_path($setting->path_logo) }}" alt="Logo">
        @endif
        <h2>{{ str_replace('CENTER', 'CENTRE', $setting->nama_perusahaan ?? 'Company Name') }}</h2>
        <h3>PAYROLL REPORT</h3>
        <div><strong>Period:</strong> {{ date('d/m/Y', strtotime($startDate)) }} - {{ date('d/m/Y', strtotime($endDate)) }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Employee</th>
                <th>ID Number</th>
                <th>Type</th>
                <th class="text-right">Gross Salary</th>
                <th class="text-right">PAYE</th>
                <th class="text-right">SHA</th>
                <th class="text-right">NSSF</th>
                <th class="text-right">Total Deductions</th>
                <th class="text-right">Net Salary</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payrolls as $index => $payroll)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ date('d/m/Y', strtotime($payroll->payroll_date)) }}</td>
                <td>{{ $payroll->employee->name ?? 'N/A' }}</td>
                <td>{{ $payroll->employee->id_number ?? 'N/A' }}</td>
                <td>{{ ucfirst($payroll->type) }}</td>
                <td class="text-right">{{ number_format($payroll->gross_salary, 2) }}</td>
                <td class="text-right">{{ number_format($payroll->paye, 2) }}</td>
                <td class="text-right">{{ number_format($payroll->nhif, 2) }}</td>
                <td class="text-right">{{ number_format($payroll->nssf_employee, 2) }}</td>
                <td class="text-right">{{ number_format($payroll->paye + $payroll->nhif + $payroll->nssf_employee + $payroll->employee_pension + $payroll->other_deductions, 2) }}</td>
                <td class="text-right">{{ number_format($payroll->net_salary, 2) }}</td>
                <td>{{ ucfirst($payroll->status) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5"><strong>TOTALS</strong></td>
                <td class="text-right"><strong>{{ number_format($totalGrossSalary, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($payrolls->sum('paye'), 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($payrolls->sum('nhif'), 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($payrolls->sum('nssf_employee'), 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalDeductions, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalNetSalary, 2) }}</strong></td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="summary">
        <p><strong>Report Summary:</strong></p>
        <ul>
            <li>Total Payrolls: {{ $payrolls->count() }}</li>
            <li>Total Gross Salary: KES {{ number_format($totalGrossSalary, 2) }}</li>
            <li>Total Deductions: KES {{ number_format($totalDeductions, 2) }}</li>
            <li>Total Net Salary Paid: KES {{ number_format($totalNetSalary, 2) }}</li>
        </ul>
    </div>

    <div style="margin-top: 20px; text-align: center; font-size: 10px; color: #666;">
        <p>Generated on: {{ date('d F Y, h:i A') }}</p>
    </div>
</body>
</html>




