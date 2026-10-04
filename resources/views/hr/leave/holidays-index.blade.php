@extends('layouts.fleet')
@section('title', 'Holidays')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Holidays',
    'subtitle' => 'View/Search Holidays',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Holidays'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div></div>
        @if($canManage)
            <div class="sx-toolbar-actions">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#sx-holiday-modal"><i class="fa fa-plus"></i> Add Holiday</button>
            </div>
        @endif
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'holiday-table'])
        <div class="table-responsive">
            <table id="holiday-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="holiday-check-all"></th>
                        <th>Holiday Name</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($holidays as $row)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="holiday-row-check"></td>
                            <td>{{ $row->name }}</td>
                            <td data-order="{{ optional($row->holiday_date)->format('Y-m-d') }}">{{ optional($row->holiday_date)->format('Y-m-d') }}</td>
                            <td>{{ $row->description }}</td>
                            <td>@if($row->is_active)<span class="sx-status-active">Active</span>@else<span class="sx-status-inactive">Inactive</span>@endif</td>
                            <td>
                                @if($canManage)
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">Action <span class="caret"></span></button>
                                        <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                            <li>
                                                <a href="#" class="sx-edit-holiday"
                                                    data-url="{{ route('hr.leave.holidays.update', $row) }}"
                                                    data-name="{{ $row->name }}"
                                                    data-date="{{ optional($row->holiday_date)->format('Y-m-d') }}"
                                                    data-description="{{ $row->description }}"
                                                    data-active="{{ $row->is_active ? 1 : 0 }}"><i class="fa fa-edit"></i> Edit</a>
                                            </li>
                                            <li>
                                                <a href="#" class="sx-delete-expense" data-form="sx-del-hol-{{ $row->id }}"><i class="fa fa-trash"></i> Delete</a>
                                                <form id="sx-del-hol-{{ $row->id }}" action="{{ route('hr.leave.holidays.destroy', $row) }}" method="post" class="hidden">@csrf @method('DELETE')</form>
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
<div class="modal fade" id="sx-holiday-modal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="{{ route('hr.leave.holidays.store') }}" class="modal-content" id="sx-holiday-form">
            @csrf
            <input type="hidden" name="_method" id="sx-holiday-method" value="POST">
            <div class="modal-header" style="background:#A2502B;color:#fff;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1;">&times;</button>
                <h4 class="modal-title" id="sx-holiday-title">Add Holiday</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Holiday Name <span class="sx-req">*</span></label>
                    <input type="text" name="name" id="sx-hol-name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Date <span class="sx-req">*</span></label>
                    <input type="date" name="holiday_date" id="sx-hol-date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="sx-hol-desc" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group" id="sx-hol-status-wrap" style="display:none;">
                    <label>Status</label>
                    <select name="is_active" id="sx-hol-active" class="form-control">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
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
    'tableId' => 'holiday-table', 'lengthId' => 'holiday-table-length', 'searchId' => 'holiday-table-search',
    'exportId' => 'holiday-table-export', 'colvisId' => 'holiday-table-colvis',
    'checkAll' => 'holiday-check-all', 'rowCheck' => 'holiday-row-check',
    'title' => 'Holidays', 'filename' => 'holidays', 'noSort' => [0, 5], 'order' => [[2, 'desc']],
])
@push('scripts')
<script>
(function ($) {
    var storeUrl = @json(route('hr.leave.holidays.store'));
    $('[data-target="#sx-holiday-modal"]').on('click', function () {
        $('#sx-holiday-form').attr('action', storeUrl);
        $('#sx-holiday-method').val('POST');
        $('#sx-holiday-title').text('Add Holiday');
        $('#sx-hol-name,#sx-hol-date,#sx-hol-desc').val('');
        $('#sx-hol-status-wrap').hide();
    });
    $(document).on('click', '.sx-edit-holiday', function (e) {
        e.preventDefault();
        var b = $(this);
        $('#sx-holiday-form').attr('action', b.data('url'));
        $('#sx-holiday-method').val('PUT');
        $('#sx-holiday-title').text('Update Holiday');
        $('#sx-hol-name').val(b.data('name'));
        $('#sx-hol-date').val(b.data('date'));
        $('#sx-hol-desc').val(b.data('description') || '');
        $('#sx-hol-active').val(String(b.data('active')));
        $('#sx-hol-status-wrap').show();
        $('#sx-holiday-modal').modal('show');
    });
    $(document).on('click', '.sx-delete-expense', function (e) {
        e.preventDefault();
        var form = document.getElementById($(this).data('form'));
        if (!form) return;
        if (window.Swal) {
            Swal.fire({icon:'warning',title:'Delete Holiday',text:'This holiday will be removed.',showCancelButton:true,confirmButtonColor:'#dd4b39',confirmButtonText:'Delete'})
                .then(function (r) { if (r.isConfirmed) form.submit(); });
            return;
        }
        if (confirm('Delete this holiday?')) form.submit();
    });
})(jQuery);
</script>
@endpush
