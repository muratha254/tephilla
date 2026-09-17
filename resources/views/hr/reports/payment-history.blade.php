@extends('layouts.fleet')
@section('title', 'Payment History')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Payment History',
    'backUrl' => route('hr.reports.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'HR Reports', 'url' => route('hr.reports.index')],
        ['label' => 'Payment History'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Filters</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('hr.reports.payment-history') }}" class="sx-acc-filter">
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
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Employee</label>
                        <select name="employee_id" class="form-control">
                            <option value="">All</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" @if((string) $employeeId === (string) $employee->id) selected @endif>{{ $employee->fullName() }}</option>
                            @endforeach
                        </select>
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
    <div class="sx-acc-card-head"><i class="fa fa-money"></i> Report</div>
    <div class="sx-acc-card-body">
        <div class="table-responsive">
            <table class="table table-bordered sx-gold-table">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Payroll</th>
                        <th>Method</th>
                        <th class="text-right">Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->number }}</td>
                            <td>{{ optional($row->payment_date)->format('Y-m-d') }}</td>
                            <td>{{ optional($row->employee)->fullName() }}</td>
                            <td>{{ optional($row->payrollRun)->number ?: 'Ad-hoc' }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $row->method)) }}</td>
                            <td class="text-right">{{ number_format((float) $row->amount, 2) }}</td>
                            <td>{{ ucfirst($row->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center">No payments found.</td></tr>
                    @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                    <tfoot>
                        <tr>
                            <th colspan="5">Total</th>
                            <th class="text-right">{{ number_format($total, 2) }}</th>
                            <th></th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endif
@endsection
