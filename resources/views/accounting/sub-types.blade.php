@extends('layouts.fleet')

@section('title', 'Sub Account Type List')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Sub Account Type List',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'List of Charts of Accounts'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div></div>
        <div class="sx-toolbar-actions">
            @if($canManage)
                <button type="button" class="btn sx-btn-aqua" data-toggle="modal" data-target="#sx-sub-modal"><i class="fa fa-plus"></i> Add Sub Account Type</button>
            @endif
        </div>
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'st-table'])
        <div class="table-responsive">
            <table id="st-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="st-check-all"></th>
                        <th>Sub Account Code</th>
                        <th>Sub Account Name</th>
                        <th>Account Type</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($subTypes as $row)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="st-row-check" value="{{ $row->id }}"></td>
                            <td>{{ $row->code }}</td>
                            <td>{{ $row->name }}</td>
                            <td>{{ optional($row->accountType)->name }}</td>
                            <td>{{ $row->description }}</td>
                            <td>
                                <span class="{{ $row->is_active ? 'sx-status-active' : 'sx-status-inactive' }}">{{ $row->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td>
                                @if($canManage)
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">Action <span class="caret"></span></button>
                                        <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                            <li>
                                                <a href="#" class="sx-edit-sub"
                                                    data-id="{{ $row->id }}"
                                                    data-name="{{ $row->name }}"
                                                    data-type="{{ $row->account_type_id }}"
                                                    data-description="{{ $row->description }}"
                                                    data-active="{{ $row->is_active ? 1 : 0 }}"
                                                    data-url="{{ route('accounting.sub-types.update', $row) }}">
                                                    <i class="fa fa-pencil"></i> Edit
                                                </a>
                                            </li>
                                            <li>
                                                <a href="#" class="sx-delete-expense" data-form="sx-del-sub-{{ $row->id }}"><i class="fa fa-trash"></i> Delete</a>
                                                <form id="sx-del-sub-{{ $row->id }}" action="{{ route('accounting.sub-types.destroy', $row) }}" method="post" class="hidden">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
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
<div class="modal fade" id="sx-sub-modal" tabindex="-1">
    <div class="modal-dialog" role="document">
        <form method="post" action="{{ route('accounting.sub-types.store') }}" class="modal-content sx-conversion-modal" id="sx-sub-form">
            @csrf
            <div id="sx-sub-method"></div>
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="sx-sub-title">Add Sub Account Type</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Sub Account Name <span class="sx-req">*</span></label>
                    <input type="text" name="name" id="sx-sub-name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Account Type <span class="sx-req">*</span></label>
                    <select name="account_type_id" id="sx-sub-type" class="form-control" required>
                        <option value="">--Select--</option>
                        @foreach($accountTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" id="sx-sub-desc" class="form-control">
                </div>
                <div class="form-group" id="sx-sub-active-wrap" style="display:none;">
                    <label>Status</label>
                    <select name="is_active" id="sx-sub-active" class="form-control">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-success">Save</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@include('accounting.partials.datatable-js', [
    'tableId' => 'st-table',
    'lengthId' => 'st-table-length',
    'searchId' => 'st-table-search',
    'exportId' => 'st-table-export',
    'colvisId' => 'st-table-colvis',
    'checkAll' => 'st-check-all',
    'rowCheck' => 'st-row-check',
    'title' => 'Sub Account Type List',
    'filename' => 'sub-account-types',
    'noSort' => [0, 6],
])

@push('scripts')
<script>
(function ($) {
    $('#sx-sub-modal').on('show.bs.modal', function (e) {
        if ($(e.relatedTarget).hasClass('sx-edit-sub')) return;
        $('#sx-sub-form').attr('action', @json(route('accounting.sub-types.store')));
        $('#sx-sub-method').html('');
        $('#sx-sub-title').text('Add Sub Account Type');
        $('#sx-sub-name').val('');
        $('#sx-sub-type').val('');
        $('#sx-sub-desc').val('');
        $('#sx-sub-active-wrap').hide();
    });
    $(document).on('click', '.sx-edit-sub', function (e) {
        e.preventDefault();
        $('#sx-sub-form').attr('action', $(this).data('url'));
        $('#sx-sub-method').html('@method("PUT")');
        $('#sx-sub-title').text('Edit Sub Account Type');
        $('#sx-sub-name').val($(this).data('name'));
        $('#sx-sub-type').val($(this).data('type'));
        $('#sx-sub-desc').val($(this).data('description'));
        $('#sx-sub-active').val($(this).data('active'));
        $('#sx-sub-active-wrap').show();
        $('#sx-sub-modal').modal('show');
    });
    $(document).on('click', '.sx-delete-expense', function (e) {
        e.preventDefault();
        var form = document.getElementById($(this).data('form'));
        if (!form) return;
        if (window.Swal) {
            Swal.fire({ icon: 'warning', title: 'Delete', text: 'Remove this sub account type?', showCancelButton: true, confirmButtonColor: '#dd4b39', confirmButtonText: 'Delete' })
                .then(function (result) { if (result.isConfirmed) form.submit(); });
            return;
        }
        if (window.confirm('Delete this sub account type?')) form.submit();
    });
})(jQuery);
</script>
@endpush
