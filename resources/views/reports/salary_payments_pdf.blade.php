<!DOCTYPE html>
<html>
<head>
    <title>Salary Payment Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h2 {
            margin: 0;
            padding: 0;
        }
        .info {
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 10px;
        }
        .summary {
            margin-top: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Salary Payment Report</h2>
    </div>

    <div class="info">
        <p><strong>Date Range:</strong> {{ date('Y-m-d', strtotime($startDate)) }} to {{ date('Y-m-d', strtotime($endDate)) }}</p>
        @if($employee)
            <p><strong>Employee:</strong> {{ $employee->name }} @if($employee->employer_number) ({{ $employee->employer_number }}) @endif</p>
        @else
            <p><strong>Employee:</strong> All Employees</p>
        @endif
        <p><strong>Generated:</strong> {{ date('Y-m-d H:i:s') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Employee</th>
                <th>Employee #</th>
                <th>Type</th>
                <th>Status</th>
                <th class="text-right">Gross Salary</th>
                <th class="text-right">Additions</th>
                <th class="text-right">Deductions</th>
                <th class="text-right">PAYE</th>
                <th class="text-right">NHIF</th>
                <th class="text-right">NSSF</th>
                <th class="text-right">Net Salary</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payrolls as $payroll)
            <tr>
                <td>{{ $payroll->payroll_date ? date('Y-m-d', strtotime($payroll->payroll_date)) : '-' }}</td>
                <td>{{ $payroll->employee->name ?? 'N/A' }}</td>
                <td>{{ $payroll->employee->employer_number ?? '-' }}</td>
                <td>{{ ucfirst($payroll->type) }}</td>
                <td>{{ ucfirst($payroll->status) }}</td>
                <td class="text-right">{{ number_format($payroll->gross_salary, 2) }}</td>
                <td class="text-right">{{ number_format($payroll->other_additions, 2) }}</td>
                <td class="text-right">
                    {{ number_format($payroll->paye + $payroll->nhif + $payroll->nssf_employee + $payroll->employee_pension + $payroll->other_deductions + $payroll->advance_salary, 2) }}
                </td>
                <td class="text-right">{{ number_format($payroll->paye, 2) }}</td>
                <td class="text-right">{{ number_format($payroll->nhif, 2) }}</td>
                <td class="text-right">{{ number_format($payroll->nssf_employee, 2) }}</td>
                <td class="text-right"><strong>{{ number_format($payroll->net_salary, 2) }}</strong></td>
            </tr>
            @empty
            <tr>
                <td colspan="12" class="text-center">No payroll records found.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" class="text-right"><strong>Total:</strong></td>
                <td class="text-right"><strong>{{ number_format($totalGrossSalary, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalAdditions, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalDeductions, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalPaye, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalNhif, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalNssf, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totalNetSalary, 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>

    <div class="summary">
        <h3>Summary</h3>
        <table style="border: none;">
            <tr>
                <td style="border: none;"><strong>Total Gross Salary:</strong></td>
                <td style="border: none; text-align: right;">{{ number_format($totalGrossSalary, 2) }}</td>
            </tr>
            <tr>
                <td style="border: none;"><strong>Total Additions:</strong></td>
                <td style="border: none; text-align: right;">{{ number_format($totalAdditions, 2) }}</td>
            </tr>
            <tr>
                <td style="border: none;"><strong>Total Deductions:</strong></td>
                <td style="border: none; text-align: right;">{{ number_format($totalDeductions, 2) }}</td>
            </tr>
            <tr>
                <td style="border: none;"><strong>Total PAYE:</strong></td>
                <td style="border: none; text-align: right;">{{ number_format($totalPaye, 2) }}</td>
            </tr>
            <tr>
                <td style="border: none;"><strong>Total NHIF:</strong></td>
                <td style="border: none; text-align: right;">{{ number_format($totalNhif, 2) }}</td>
            </tr>
            <tr>
                <td style="border: none;"><strong>Total NSSF (Employee):</strong></td>
                <td style="border: none; text-align: right;">{{ number_format($totalNssf, 2) }}</td>
            </tr>
            <tr>
                <td style="border: none;"><strong>Total Employer Contributions:</strong></td>
                <td style="border: none; text-align: right;">{{ number_format($totalEmployerContributions, 2) }}</td>
            </tr>
            <tr>
                <td style="border: none;"><strong>Total Net Salary:</strong></td>
                <td style="border: none; text-align: right;"><strong>{{ number_format($totalNetSalary, 2) }}</strong></td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Generated on {{ date('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>









