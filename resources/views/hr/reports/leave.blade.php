@extends('layouts.fleet')
@section('title', 'Leave Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Leave Report',
    'backUrl' => route('hr.reports.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'HR Reports', 'url' => route('hr.reports.index')],
        ['label' => 'Leave'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Filters</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('hr.reports.leave') }}" class="sx-acc-filter">
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
    <div class="sx-acc-card-head"><i class="fa fa-calendar"></i> Report</div>
    <div class="sx-acc-card-body">
        <div class="table-responsive">
            <table class="table table-bordered sx-gold-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Type</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Days</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ optional($row->employee)->fullName() }}</td>
                            <td>{{ optional($row->leaveType)->name }}</td>
                            <td>{{ optional($row->start_date)->format('Y-m-d') }}</td>
                            <td>{{ optional($row->end_date)->format('Y-m-d') }}</td>
                            <td>{{ number_format((float) $row->days, 1) }}</td>
                            <td>{{ ucfirst($row->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center">No leave records.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection
