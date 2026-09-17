@extends('layouts.fleet')
@section('title', 'Employees Category')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Employees Category',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Employees Category'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div></div>
        @if($canManage)
            <div class="sx-toolbar-actions">
                <a href="{{ route('hr.employee-categories.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Add Category</a>
            </div>
        @endif
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'empcat-table'])
        <div class="table-responsive">
            <table id="empcat-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="empcat-check-all"></th>
                        <th>Category Code</th>
                        <th>Category Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($categories as $row)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="empcat-row-check"></td>
                            <td data-order="{{ $row->id }}">{{ $row->id }}</td>
                            <td>{{ $row->name }}</td>
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
                                            <li><a href="{{ route('hr.employee-categories.edit', $row) }}"><i class="fa fa-edit"></i> Edit</a></li>
                                            <li>
                                                <a href="#" class="sx-delete-expense" data-form="sx-del-empcat-{{ $row->id }}"><i class="fa fa-trash"></i> Delete</a>
                                                <form id="sx-del-empcat-{{ $row->id }}" action="{{ route('hr.employee-categories.destroy', $row) }}" method="post" class="hidden">@csrf @method('DELETE')</form>
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
    'tableId' => 'empcat-table', 'lengthId' => 'empcat-table-length', 'searchId' => 'empcat-table-search',
    'exportId' => 'empcat-table-export', 'colvisId' => 'empcat-table-colvis',
    'checkAll' => 'empcat-check-all', 'rowCheck' => 'empcat-row-check',
    'title' => 'Employees Category', 'filename' => 'employee-categories', 'noSort' => [0, 5], 'order' => [[1, 'asc']],
])
@push('scripts')
<script>
(function ($) {
    $(document).on('click', '.sx-delete-expense', function (e) {
        e.preventDefault();
        var form = document.getElementById($(this).data('form'));
        if (!form) return;
        if (window.Swal) {
            Swal.fire({icon:'warning',title:'Delete Category',text:'This employee category will be removed.',showCancelButton:true,confirmButtonColor:'#dd4b39',confirmButtonText:'Delete'})
                .then(function (r) { if (r.isConfirmed) form.submit(); });
            return;
        }
        if (confirm('Delete this category?')) form.submit();
    });
})(jQuery);
</script>
@endpush
