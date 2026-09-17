@extends('layouts.fleet')
@section('title', 'Manage Leaves')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Manage Leaves',
    'subtitle' => 'View/Search Leave Requests',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Manage Leaves'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div></div>
        @if($canManage)
            <div class="sx-toolbar-actions">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#sx-leave-modal"><i class="fa fa-plus"></i> Add Leave</button>
            </div>
        @endif
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'leave-table'])
        <div class="table-responsive">
            <table id="leave-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="leave-check-all"></th>
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Days</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($leaves as $row)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="leave-row-check"></td>
                            <td>{{ optional($row->employee)->fullName() }}</td>
                            <td>{{ optional($row->leaveType)->name }}</td>
                            <td>{{ optional($row->start_date)->format('Y-m-d') }}</td>
                            <td>{{ optional($row->end_date)->format('Y-m-d') }}</td>
                            <td>{{ number_format((float) $row->days, 1) }}</td>
                            <td>{{ $row->reason }}</td>
                            <td>
                                @if($row->status === 'approved')
                                    <span class="sx-status-active">Approved</span>
                                @elseif($row->status === 'rejected')
                                    <span class="sx-status-inactive">Rejected</span>
                                @else
                                    <span class="label label-warning">Pending</span>
                                @endif
                            </td>
                            <td>
                                @if($canManage)
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">Action <span class="caret"></span></button>
                                        <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                            @if($row->status !== 'approved')
                                                <li>
                                                    <a href="#" onclick="event.preventDefault(); document.getElementById('sx-approve-{{ $row->id }}').submit();"><i class="fa fa-check"></i> Approve</a>
                                                    <form id="sx-approve-{{ $row->id }}" action="{{ route('hr.leave.manage.status', $row) }}" method="post" class="hidden">@csrf @method('PUT')<input type="hidden" name="status" value="approved"></form>
                                                </li>
                                            @endif
                                            @if($row->status !== 'rejected')
                                                <li>
                                                    <a href="#" onclick="event.preventDefault(); document.getElementById('sx-reject-{{ $row->id }}').submit();"><i class="fa fa-times"></i> Reject</a>
                                                    <form id="sx-reject-{{ $row->id }}" action="{{ route('hr.leave.manage.status', $row) }}" method="post" class="hidden">@csrf @method('PUT')<input type="hidden" name="status" value="rejected"></form>
                                                </li>
                                            @endif
                                            <li>
                                                <a href="#" class="sx-delete-expense" data-form="sx-del-leave-{{ $row->id }}"><i class="fa fa-trash"></i> Delete</a>
                                                <form id="sx-del-leave-{{ $row->id }}" action="{{ route('hr.leave.manage.destroy', $row) }}" method="post" class="hidden">@csrf @method('DELETE')</form>
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

@if($canManage)
<div class="modal fade" id="sx-leave-modal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="{{ route('hr.leave.manage.store') }}" class="modal-content">
            @csrf
            <div class="modal-header" style="background:#c9a027;color:#fff;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1;">&times;</button>
                <h4 class="modal-title">Add Leave</h4>
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
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Start Date <span class="sx-req">*</span></label>
                    <input type="date" name="start_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>End Date <span class="sx-req">*</span></label>
                    <input type="date" name="end_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Reason</label>
                    <textarea name="reason" class="form-control" rows="3"></textarea>
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
    'tableId' => 'leave-table', 'lengthId' => 'leave-table-length', 'searchId' => 'leave-table-search',
    'exportId' => 'leave-table-export', 'colvisId' => 'leave-table-colvis',
    'checkAll' => 'leave-check-all', 'rowCheck' => 'leave-row-check',
    'title' => 'Manage Leaves', 'filename' => 'manage-leaves', 'noSort' => [0, 8], 'order' => [[3, 'desc']],
])
@push('scripts')
<script>
(function ($) {
    $(document).on('click', '.sx-delete-expense', function (e) {
        e.preventDefault();
        var form = document.getElementById($(this).data('form'));
        if (!form) return;
        if (confirm('Delete this leave?')) form.submit();
    });
})(jQuery);
</script>
@endpush
