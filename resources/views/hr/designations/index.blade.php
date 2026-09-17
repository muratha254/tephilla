@extends('layouts.fleet')
@section('title', 'Designation List')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Designation List',
    'subtitle' => 'View/Search Designation',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Designation List'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div></div>
        @if($canManage)
            <div class="sx-toolbar-actions">
                <a href="{{ route('hr.designations.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Add Designation</a>
            </div>
        @endif
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'desig-table'])
        <div class="table-responsive">
            <table id="desig-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="desig-check-all"></th>
                        <th>Designation Code</th>
                        <th>Designation Name</th>
                        <th>Department</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($designations as $row)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="desig-row-check"></td>
                            <td data-order="{{ $row->id }}">{{ $row->id }}</td>
                            <td>{{ $row->name }}</td>
                            <td>{{ optional($row->department)->name }}</td>
                            <td>{{ $row->description }}</td>
                            <td>
                                @if($row->is_active)
                                    <span class="sx-status-active">Active</span>
                                @else
                                    <span class="sx-status-inactive">Inactive</span>
                                @endif
                            </td>
                            <td>
                                @if($canManage)
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">Action <span class="caret"></span></button>
                                        <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                            <li><a href="{{ route('hr.designations.edit', $row) }}"><i class="fa fa-edit"></i> Edit</a></li>
                                            <li>
                                                <a href="#" class="sx-delete-expense" data-form="sx-del-desig-{{ $row->id }}"><i class="fa fa-trash"></i> Delete</a>
                                                <form id="sx-del-desig-{{ $row->id }}" action="{{ route('hr.designations.destroy', $row) }}" method="post" class="hidden">@csrf @method('DELETE')</form>
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
    'tableId' => 'desig-table', 'lengthId' => 'desig-table-length', 'searchId' => 'desig-table-search',
    'exportId' => 'desig-table-export', 'colvisId' => 'desig-table-colvis',
    'checkAll' => 'desig-check-all', 'rowCheck' => 'desig-row-check',
    'title' => 'Designation List', 'filename' => 'designation-list', 'noSort' => [0, 6], 'order' => [[1, 'asc']],
])
@push('scripts')
<script>
(function ($) {
    $(document).on('click', '.sx-delete-expense', function (e) {
        e.preventDefault();
        var form = document.getElementById($(this).data('form'));
        if (!form) return;
        if (window.Swal) {
            Swal.fire({icon:'warning',title:'Delete Designation',text:'This designation will be removed.',showCancelButton:true,confirmButtonColor:'#dd4b39',confirmButtonText:'Delete'})
                .then(function (r) { if (r.isConfirmed) form.submit(); });
            return;
        }
        if (confirm('Delete this designation?')) form.submit();
    });
})(jQuery);
</script>
@endpush
