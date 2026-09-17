@extends('layouts.fleet')
@section('title', 'Employees Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Employees Report',
    'backUrl' => route('hr.reports.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'HR Reports', 'url' => route('hr.reports.index')],
        ['label' => 'Employees'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Filters</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('hr.reports.employees') }}" class="sx-acc-filter">
            <input type="hidden" name="show" value="1">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="active" @if($status === 'active') selected @endif>Active</option>
                            <option value="inactive" @if($status === 'inactive') selected @endif>Inactive</option>
                            <option value="all" @if($status === 'all') selected @endif>All</option>
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
    <div class="sx-acc-card-head"><i class="fa fa-users"></i> Report</div>
    <div class="sx-acc-card-body">
        <div class="table-responsive">
            <table class="table table-bordered sx-gold-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Branch</th>
                        <th class="text-right">Basic Salary</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->displayCode() }}</td>
                            <td>{{ $row->fullName() }}</td>
                            <td>{{ optional($row->department)->name }}</td>
                            <td>{{ optional($row->designation)->name }}</td>
                            <td>{{ optional($row->branch)->name }}</td>
                            <td class="text-right">{{ number_format((float) $row->basic_salary, 2) }}</td>
                            <td>{{ ucfirst($row->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center">No employees found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection
