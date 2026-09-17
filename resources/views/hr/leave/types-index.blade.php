@extends('layouts.fleet')
@section('title', 'Leave Types List')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Leave Types List',
    'subtitle' => 'View/Search Leave Types',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Leave Types List'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div></div>
        @if($canManage)
            <div class="sx-toolbar-actions">
                <a href="{{ route('hr.leave.types.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Add Leave Type</a>
            </div>
        @endif
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'ltype-table'])
        <div class="table-responsive">
            <table id="ltype-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="ltype-check-all"></th>
                        <th>Leave Type</th>
                        <th>Days Allowed</th>
                        <th>Paid</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($types as $row)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="ltype-row-check"></td>
                            <td>{{ $row->name }}</td>
                            <td>{{ number_format((float) $row->days_allowed, 1) }}</td>
                            <td>{{ $row->is_paid ? 'Yes' : 'No' }}</td>
                            <td>{{ $row->description }}</td>
                            <td>@if($row->is_active)<span class="sx-status-active">Active</span>@else<span class="sx-status-inactive">Inactive</span>@endif</td>
                            <td>
                                @if($canManage)
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">Action <span class="caret"></span></button>
                                        <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                            <li><a href="{{ route('hr.leave.types.edit', $row) }}"><i class="fa fa-edit"></i> Edit</a></li>
                                            <li>
                                                <a href="#" class="sx-delete-expense" data-form="sx-del-ltype-{{ $row->id }}"><i class="fa fa-trash"></i> Delete</a>
                                                <form id="sx-del-ltype-{{ $row->id }}" action="{{ route('hr.leave.types.destroy', $row) }}" method="post" class="hidden">@csrf @method('DELETE')</form>
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
    'tableId' => 'ltype-table', 'lengthId' => 'ltype-table-length', 'searchId' => 'ltype-table-search',
    'exportId' => 'ltype-table-export', 'colvisId' => 'ltype-table-colvis',
    'checkAll' => 'ltype-check-all', 'rowCheck' => 'ltype-row-check',
    'title' => 'Leave Types', 'filename' => 'leave-types', 'noSort' => [0, 6], 'order' => [[1, 'asc']],
])
@push('scripts')
<script>
(function ($) {
    $(document).on('click', '.sx-delete-expense', function (e) {
        e.preventDefault();
        var form = document.getElementById($(this).data('form'));
        if (!form) return;
        if (window.Swal) {
            Swal.fire({icon:'warning',title:'Delete Leave Type',text:'This leave type will be removed.',showCancelButton:true,confirmButtonColor:'#dd4b39',confirmButtonText:'Delete'})
                .then(function (r) { if (r.isConfirmed) form.submit(); });
            return;
        }
        if (confirm('Delete this leave type?')) form.submit();
    });
})(jQuery);
</script>
@endpush
