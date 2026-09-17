@extends('layouts.fleet')
@section('title', 'Time Attendance')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Time Attendance',
    'subtitle' => 'View/Search Attendance',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Time Attendance'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <form method="get" class="form-inline" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <input type="date" name="attendance_date" class="form-control" value="{{ $filters['attendance_date'] ?? '' }}">
            <select name="employee_id" class="form-control">
                <option value="">All Employees</option>
                @foreach($employees as $employee)
                    <option value="{{ $employee->id }}" @if((string) ($filters['employee_id'] ?? '') === (string) $employee->id) selected @endif>
                        {{ $employee->fullName() }}
                    </option>
                @endforeach
            </select>
            <select name="status" class="form-control">
                <option value="">All Statuses</option>
                @foreach($statuses as $key => $label)
                    <option value="{{ $key }}" @if(($filters['status'] ?? '') === $key) selected @endif>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-default">Filter</button>
        </form>
        @if($canManage)
            <div class="sx-toolbar-actions">
                <a href="{{ route('hr.attendance.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Add Attendance</a>
            </div>
        @endif
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'att-table'])
        <div class="table-responsive">
            <table id="att-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Hours</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($records as $row)
                        <tr>
                            <td data-order="{{ optional($row->attendance_date)->format('Y-m-d') }}">{{ optional($row->attendance_date)->format('Y-m-d') }}</td>
                            <td>{{ optional($row->employee)->fullName() }}</td>
                            <td>{{ $row->clock_in ? substr((string) $row->clock_in, 0, 5) : '-' }}</td>
                            <td>{{ $row->clock_out ? substr((string) $row->clock_out, 0, 5) : '-' }}</td>
                            <td>{{ $row->hours_worked !== null ? number_format((float) $row->hours_worked, 2) : '-' }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $row->status)) }}</td>
                            <td>{{ $row->notes }}</td>
                            <td>
                                @if($canManage)
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">Action <span class="caret"></span></button>
                                        <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                            <li><a href="{{ route('hr.attendance.edit', $row) }}"><i class="fa fa-pencil"></i> Edit</a></li>
                                            <li>
                                                <a href="#" class="sx-delete-expense" data-form="sx-del-att-{{ $row->id }}"><i class="fa fa-trash"></i> Delete</a>
                                                <form id="sx-del-att-{{ $row->id }}" action="{{ route('hr.attendance.destroy', $row) }}" method="post" class="hidden">@csrf @method('DELETE')</form>
                                            </li>
                                        </ul>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@include('accounting.partials.datatable-js', [
    'tableId' => 'att-table', 'lengthId' => 'att-table-length', 'searchId' => 'att-table-search',
    'exportId' => 'att-table-export', 'colvisId' => 'att-table-colvis',
    'title' => 'Attendance', 'filename' => 'attendance', 'noSort' => [7], 'order' => [[0, 'desc']],
])
@push('scripts')
<script>
(function ($) {
    $(document).on('click', '.sx-delete-expense', function (e) {
        e.preventDefault();
        var form = document.getElementById($(this).data('form'));
        if (form && confirm('Delete this attendance record?')) form.submit();
    });
})(jQuery);
</script>
@endpush
