@extends('layouts.fleet')
@section('title', 'Payroll Summary')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Payroll Summary',
    'backUrl' => route('hr.reports.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'HR Reports', 'url' => route('hr.reports.index')],
        ['label' => 'Payroll Summary'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Filters</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('hr.reports.payroll-summary') }}" class="sx-acc-filter">
            <input type="hidden" name="show" value="1">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>From</label>
                        <input type="date" name="from" class="form-control" value="{{ $from }}" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>To</label>
                        <input type="date" name="to" class="form-control" value="{{ $to }}" required>
                    </div>
                </div>
            </div>
            <div class="sx-form-actions">
                <button type="submit" class="btn btn-success">Show</button>
                <a href="{{ route('hr.reports.index') }}" class="btn btn-warning">Close</a>
            </div>
        </form>
    </div>
</div>

@if($generated)
<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-bar-chart"></i> Report</div>
    <div class="sx-acc-card-body">
        <div class="table-responsive">
            <table class="table table-bordered sx-gold-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Payroll</th>
                        <th class="text-right">Basic</th>
                        <th class="text-right">Allowances</th>
                        <th class="text-right">Deductions</th>
                        <th class="text-right">Gross</th>
                        <th class="text-right">Net</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ optional($row->employee)->fullName() }}</td>
                            <td>{{ optional($row->payrollRun)->number }}</td>
                            <td class="text-right">{{ number_format((float) $row->basic_salary, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->allowances, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->deductions, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->gross_pay, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->net_pay, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center">No payroll items.</td></tr>
                    @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                    <tfoot>
                        <tr>
                            <th colspan="4">Totals</th>
                            <th class="text-right">{{ number_format($totals['deductions'], 2) }}</th>
                            <th class="text-right">{{ number_format($totals['gross'], 2) }}</th>
                            <th class="text-right">{{ number_format($totals['net'], 2) }}</th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endif
@endsection
