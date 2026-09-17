@extends('layouts.fleet')
@section('title', 'Employees')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Employees',
    'subtitle' => 'View/Search Employees',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Employees'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div></div>
        @if($canManage)
            <div class="sx-toolbar-actions">
                <button type="button" class="btn btn-success" data-toggle="modal" data-target="#sx-emp-upload"><i class="fa fa-cloud-upload"></i> Employees Bulk Upload</button>
                <a href="{{ route('hr.employees.template') }}" class="btn btn-primary"><i class="fa fa-download"></i> Download Upload Template</a>
                <a href="{{ route('hr.employees.create') }}" class="btn sx-btn-aqua"><i class="fa fa-plus"></i> New Employee</a>
            </div>
        @endif
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'emp-table'])
        <div class="table-responsive">
            <table id="emp-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="emp-check-all"></th>
                        <th>Emp. No.</th>
                        <th>Employee</th>
                        <th>Phone No.</th>
                        <th>National ID</th>
                        <th>Email Address</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($employees as $row)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="emp-row-check"></td>
                            <td>{{ $row->displayCode() }}</td>
                            <td>{{ $row->fullName() }}</td>
                            <td>{{ $row->phone }}</td>
                            <td>{{ $row->national_id }}</td>
                            <td>{{ $row->email }}</td>
                            <td>{{ optional($row->department)->name }}</td>
                            <td>{{ optional($row->designation)->name }}</td>
                            <td>
                                @if($row->isActive())
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
                                            <li><a href="{{ route('hr.employees.edit', $row) }}"><i class="fa fa-edit"></i> Edit</a></li>
                                            <li>
                                                <a href="#" class="sx-delete-expense" data-form="sx-del-emp-{{ $row->id }}"><i class="fa fa-archive"></i> Archive</a>
                                                <form id="sx-del-emp-{{ $row->id }}" action="{{ route('hr.employees.destroy', $row) }}" method="post" class="hidden">@csrf @method('DELETE')</form>
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
<div class="modal fade" id="sx-emp-upload" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="{{ route('hr.employees.upload') }}" enctype="multipart/form-data" class="modal-content">
            @csrf
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Employees Bulk Upload</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>CSV File</label>
                    <input type="file" name="file" class="form-control" accept=".csv,text/csv" required>
                </div>
                <p class="help-block">Use the downloadable template. Required columns: first_name, last_name.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-success">Upload</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@include('accounting.partials.datatable-js', [
    'tableId' => 'emp-table', 'lengthId' => 'emp-table-length', 'searchId' => 'emp-table-search',
    'exportId' => 'emp-table-export', 'colvisId' => 'emp-table-colvis',
    'checkAll' => 'emp-check-all', 'rowCheck' => 'emp-row-check',
    'title' => 'Employees', 'filename' => 'employees', 'noSort' => [0, 9], 'order' => [[1, 'desc']],
])
@push('scripts')
<script>
(function ($) {
    $(document).on('click', '.sx-delete-expense', function (e) {
        e.preventDefault();
        var form = document.getElementById($(this).data('form'));
        if (!form) return;
        if (window.Swal) {
            Swal.fire({icon:'warning',title:'Archive Employee',text:'This employee will be moved to archived list.',showCancelButton:true,confirmButtonColor:'#dd4b39',confirmButtonText:'Archive'})
                .then(function (r) { if (r.isConfirmed) form.submit(); });
            return;
        }
        if (confirm('Archive this employee?')) form.submit();
    });
})(jQuery);
</script>
@endpush
