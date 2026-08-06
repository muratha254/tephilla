@extends('layouts.master')

@section('title')
    Salary Payment Report
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Salary Payment Report</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Salary Payment Report</h3>
            </div>
            <div class="box-body">
                @if(session('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                    </div>
                @endif
                
                <form action="{{ route('reports.salary-payments') }}" method="GET" id="filter-form" class="mb-3">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" name="start_date" id="start_date" class="form-control"
                                    value="{{ $startDate }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" name="end_date" id="end_date" class="form-control"
                                    value="{{ $endDate }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="employee_id">Employee</label>
                                <select name="employee_id" id="employee_id" class="form-control select2">
                                    <option value="">All Employees</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}"
                                            {{ $employeeId == $emp->id ? 'selected' : '' }}>
                                            {{ $emp->name }} @if($emp->employer_number) ({{ $emp->employer_number }}) @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fa fa-search"></i> Filter
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <button type="button" class="btn btn-success" onclick="exportReport()">
                                <i class="fa fa-file-pdf-o"></i> Export PDF
                            </button>
                            <button type="button" class="btn btn-info" onclick="window.print()">
                                <i class="fa fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-bordered" id="salary-payments-table">
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
                                <td>
                                    <span class="label label-default">{{ ucfirst($payroll->type) }}</span>
                                </td>
                                <td>
                                    <span class="label label-{{ $payroll->status == 'completed' ? 'success' : ($payroll->status == 'cancelled' ? 'danger' : 'warning') }}">
                                        {{ ucfirst($payroll->status) }}
                                    </span>
                                </td>
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
                                <td colspan="12" class="text-center">No payroll records found for the selected criteria.</td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-right"><strong>Total:</strong></td>
                                <td class="text-right"><strong>{{ number_format($payrolls->sum('gross_salary'), 2) }}</strong></td>
                                <td class="text-right"><strong>{{ number_format($payrolls->sum('other_additions'), 2) }}</strong></td>
                                <td class="text-right"><strong>{{ number_format($payrolls->sum(function($p) { return $p->paye + $p->nhif + $p->nssf_employee + $p->employee_pension + $p->other_deductions + $p->advance_salary; }), 2) }}</strong></td>
                                <td class="text-right"><strong>{{ number_format($payrolls->sum('paye'), 2) }}</strong></td>
                                <td class="text-right"><strong>{{ number_format($payrolls->sum('nhif'), 2) }}</strong></td>
                                <td class="text-right"><strong>{{ number_format($payrolls->sum('nssf_employee'), 2) }}</strong></td>
                                <td class="text-right"><strong>{{ number_format($payrolls->sum('net_salary'), 2) }}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<style>
    @media print {
        .box-header, .btn, #filter-form {
            display: none !important;
        }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2').select2();

    $('#salary-payments-table').DataTable({
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'print'
        ],
        order: [[0, 'desc']],
        pageLength: 25
    });

    // Validate date range
    $('#filter-form').on('submit', function(e) {
        var startDate = new Date($('#start_date').val());
        var endDate = new Date($('#end_date').val());

        if (endDate < startDate) {
            e.preventDefault();
            alert('End date cannot be earlier than start date');
            return false;
        }
    });
});

function exportReport() {
    var startDate = $('#start_date').val();
    var endDate = $('#end_date').val();
    var employeeId = $('#employee_id').val();

    var url = "{{ route('reports.salary-payments.export-pdf') }}";
    url += '?start_date=' + startDate + '&end_date=' + endDate;
    if (employeeId) {
        url += '&employee_id=' + employeeId;
    }

    window.location.href = url;
}
</script>
@endpush









