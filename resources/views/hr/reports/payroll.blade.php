@extends('layouts.fleet')
@section('title', 'Payroll Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Payroll Report',
    'backUrl' => route('hr.reports.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'HR Reports', 'url' => route('hr.reports.index')],
        ['label' => 'Payroll'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Filters</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('hr.reports.payroll') }}" class="sx-acc-filter">
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
    <div class="sx-acc-card-head"><i class="fa fa-list-alt"></i> Report</div>
    <div class="sx-acc-card-body">
        <div class="table-responsive">
            <table class="table table-bordered sx-gold-table">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Period</th>
                        <th>Employees</th>
                        <th class="text-right">Gross</th>
                        <th class="text-right">Deductions</th>
                        <th class="text-right">Net</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->number }}</td>
                            <td>{{ $row->period_label }}</td>
                            <td>{{ $row->items_count }}</td>
                            <td class="text-right">{{ number_format((float) $row->total_gross, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->total_deductions, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->total_net, 2) }}</td>
                            <td>{{ ucfirst($row->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center">No payroll runs.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection
