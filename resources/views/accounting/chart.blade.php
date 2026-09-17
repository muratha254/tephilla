@extends('layouts.fleet')

@section('title', 'Charts of Account List')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Charts of Account List',
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
                <button type="button" class="btn sx-btn-aqua" data-toggle="modal" data-target="#sx-coa-modal"><i class="fa fa-plus"></i> Add Chart of Account</button>
            @endif
        </div>
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'coa-table'])
        <div class="table-responsive">
            <table id="coa-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="coa-check-all"></th>
                        <th>Account Name</th>
                        <th>Gl Code</th>
                        <th>Sub. Acc Type</th>
                        <th>Acc. Type</th>
                        <th>Description</th>
                        <th>Created Date</th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $row)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="coa-row-check" value="{{ $row->id }}"></td>
                            <td>{{ $row->name }}</td>
                            <td>{{ $row->gl_code }}</td>
                            <td>{{ optional($row->subType)->name }}</td>
                            <td>{{ optional(optional($row->subType)->accountType)->name }}</td>
                            <td>{{ $row->description }}</td>
                            <td data-order="{{ optional($row->created_at)->format('Y-m-d') }}">{{ optional($row->created_at)->format('Y-m-d') }}</td>
                            <td>
                                <span class="{{ $row->is_active ? 'sx-status-active' : 'sx-status-inactive' }}">{{ $row->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td>
                                @if($canManage)
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">Action <span class="caret"></span></button>
                                        <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                            <li>
                                                <a href="#" class="sx-edit-coa"
                                                    data-name="{{ $row->name }}"
                                                    data-sub="{{ $row->account_sub_type_id }}"
                                                    data-description="{{ $row->description }}"
                                                    data-active="{{ $row->is_active ? 1 : 0 }}"
                                                    data-url="{{ route('accounting.chart.update', $row) }}">
                                                    <i class="fa fa-pencil"></i> Edit
                                                </a>
                                            </li>
                                            <li>
                                                <a href="#" class="sx-delete-expense" data-form="sx-del-coa-{{ $row->id }}"><i class="fa fa-trash"></i> Delete</a>
                                                <form id="sx-del-coa-{{ $row->id }}" action="{{ route('accounting.chart.destroy', $row) }}" method="post" class="hidden">
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
<div class="modal fade" id="sx-coa-modal" tabindex="-1">
    <div class="modal-dialog" role="document">
        <form method="post" action="{{ route('accounting.chart.store') }}" class="modal-content sx-conversion-modal" id="sx-coa-form">
            @csrf
            <div id="sx-coa-method"></div>
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="sx-coa-title">Add Chart of Account</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Account Name <span class="sx-req">*</span></label>
                    <input type="text" name="name" id="sx-coa-name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Sub Account Type <span class="sx-req">*</span></label>
                    <select name="account_sub_type_id" id="sx-coa-sub" class="form-control" required>
                        <option value="">--Select--</option>
                        @foreach($subTypes as $sub)
                            <option value="{{ $sub->id }}">{{ $sub->name }} ({{ optional($sub->accountType)->name }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" id="sx-coa-desc" class="form-control">
                </div>
                <p class="sx-hint">Next GL code: {{ $nextGl }}</p>
                <div class="form-group" id="sx-coa-active-wrap" style="display:none;">
                    <label>Status</label>
                    <select name="is_active" id="sx-coa-active" class="form-control">
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
    'tableId' => 'coa-table',
    'lengthId' => 'coa-table-length',
    'searchId' => 'coa-table-search',
    'exportId' => 'coa-table-export',
    'colvisId' => 'coa-table-colvis',
    'checkAll' => 'coa-check-all',
    'rowCheck' => 'coa-row-check',
    'title' => 'Charts of Account List',
    'filename' => 'chart-of-accounts',
    'noSort' => [0, 8],
])

@push('scripts')
<script>
(function ($) {
    $('#sx-coa-modal').on('show.bs.modal', function (e) {
        if ($(e.relatedTarget).hasClass('sx-edit-coa')) return;
        $('#sx-coa-form').attr('action', @json(route('accounting.chart.store')));
        $('#sx-coa-method').html('');
        $('#sx-coa-title').text('Add Chart of Account');
        $('#sx-coa-name, #sx-coa-desc').val('');
        $('#sx-coa-sub').val('');
        $('#sx-coa-active-wrap').hide();
    });
    $(document).on('click', '.sx-edit-coa', function (e) {
        e.preventDefault();
        $('#sx-coa-form').attr('action', $(this).data('url'));
        $('#sx-coa-method').html('@method("PUT")');
        $('#sx-coa-title').text('Edit Chart of Account');
        $('#sx-coa-name').val($(this).data('name'));
        $('#sx-coa-sub').val($(this).data('sub'));
        $('#sx-coa-desc').val($(this).data('description'));
        $('#sx-coa-active').val($(this).data('active'));
        $('#sx-coa-active-wrap').show();
        $('#sx-coa-modal').modal('show');
    });
    $(document).on('click', '.sx-delete-expense', function (e) {
        e.preventDefault();
        var form = document.getElementById($(this).data('form'));
        if (!form) return;
        if (window.Swal) {
            Swal.fire({ icon: 'warning', title: 'Delete', text: 'Remove this account?', showCancelButton: true, confirmButtonColor: '#dd4b39', confirmButtonText: 'Delete' })
                .then(function (result) { if (result.isConfirmed) form.submit(); });
            return;
        }
        if (window.confirm('Delete this account?')) form.submit();
    });
})(jQuery);
</script>
@endpush
