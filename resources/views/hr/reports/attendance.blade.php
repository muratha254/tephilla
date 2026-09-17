@extends('layouts.fleet')
@section('title', 'Attendance Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Attendance Report',
    'backUrl' => route('hr.reports.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'HR Reports', 'url' => route('hr.reports.index')],
        ['label' => 'Attendance'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Filters</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('hr.reports.attendance') }}" class="sx-acc-filter">
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
    <div class="sx-acc-card-head"><i class="fa fa-clock-o"></i> Report</div>
    <div class="sx-acc-card-body">
        <div class="table-responsive">
            <table class="table table-bordered sx-gold-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>In</th>
                        <th>Out</th>
                        <th>Hours</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ optional($row->attendance_date)->format('Y-m-d') }}</td>
                            <td>{{ optional($row->employee)->fullName() }}</td>
                            <td>{{ $row->clock_in ? substr((string) $row->clock_in, 0, 5) : '-' }}</td>
                            <td>{{ $row->clock_out ? substr((string) $row->clock_out, 0, 5) : '-' }}</td>
                            <td>{{ $row->hours_worked !== null ? number_format((float) $row->hours_worked, 2) : '-' }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $row->status)) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center">No attendance records.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection
