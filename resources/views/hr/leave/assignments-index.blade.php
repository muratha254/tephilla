@extends('layouts.fleet')
@section('title', 'Assign Leaves')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Assign Leaves',
    'subtitle' => 'Assign leave days to employees',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Assign Leaves'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div></div>
        @if($canManage)
            <div class="sx-toolbar-actions">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#sx-assign-modal"><i class="fa fa-plus"></i> Assign Leave</button>
            </div>
        @endif
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'assign-table'])
        <div class="table-responsive">
            <table id="assign-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="assign-check-all"></th>
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>Year</th>
                        <th>Days Assigned</th>
                        <th>Days Used</th>
                        <th>Remaining</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($assignments as $row)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="assign-row-check"></td>
                            <td>{{ optional($row->employee)->fullName() }}</td>
                            <td>{{ optional($row->leaveType)->name }}</td>
                            <td>{{ $row->year }}</td>
                            <td>{{ number_format((float) $row->days_assigned, 1) }}</td>
                            <td>{{ number_format((float) $row->days_used, 1) }}</td>
                            <td>{{ number_format($row->remainingDays(), 1) }}</td>
                            <td>
                                @if($canManage)
                                    <a href="#" class="btn btn-danger btn-xs sx-delete-expense" data-form="sx-del-assign-{{ $row->id }}"><i class="fa fa-trash"></i></a>
                                    <form id="sx-del-assign-{{ $row->id }}" action="{{ route('hr.leave.assign.destroy', $row) }}" method="post" class="hidden">@csrf @method('DELETE')</form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($canManage)
<div class="modal fade" id="sx-assign-modal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="{{ route('hr.leave.assign.store') }}" class="modal-content">
            @csrf
            <div class="modal-header" style="background:#c9a027;color:#fff;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1;">&times;</button>
                <h4 class="modal-title">Assign Leave</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Employee <span class="sx-req">*</span></label>
                    <select name="employee_id" class="form-control" required>
                        <option value="">~~Select Employee~~</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->fullName() }}-{{ $employee->displayCode() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Leave Type <span class="sx-req">*</span></label>
                    <select name="leave_type_id" class="form-control" required>
                        <option value="">~~Select Leave Type~~</option>
                        @foreach($types as $type)
                            <option value="{{ $type->id }}">{{ $type->name }} ({{ number_format((float)$type->days_allowed,1) }} days)</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Year <span class="sx-req">*</span></label>
                    <input type="number" name="year" class="form-control" value="{{ now()->year }}" required>
                </div>
                <div class="form-group">
                    <label>Days Assigned <span class="sx-req">*</span></label>
                    <input type="number" step="0.5" min="0" name="days_assigned" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer" style="text-align:center;">
                <button type="submit" class="btn btn-success">Save</button>
                <button type="button" class="btn btn-warning" data-dismiss="modal">Close</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@include('accounting.partials.datatable-js', [
    'tableId' => 'assign-table', 'lengthId' => 'assign-table-length', 'searchId' => 'assign-table-search',
    'exportId' => 'assign-table-export', 'colvisId' => 'assign-table-colvis',
    'checkAll' => 'assign-check-all', 'rowCheck' => 'assign-row-check',
    'title' => 'Assign Leaves', 'filename' => 'assign-leaves', 'noSort' => [0, 7], 'order' => [[3, 'desc']],
])
@push('scripts')
<script>
(function ($) {
    $(document).on('click', '.sx-delete-expense', function (e) {
        e.preventDefault();
        var form = document.getElementById($(this).data('form'));
        if (!form) return;
        if (confirm('Delete this assignment?')) form.submit();
    });
})(jQuery);
</script>
@endpush
