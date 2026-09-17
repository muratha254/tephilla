@extends('layouts.fleet')
@section('title', 'Archived Employees')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Archived Employees',
    'subtitle' => 'View/Search Employees',
    'backUrl' => route('hr.employees.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Archived Employees'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div>
            <a href="{{ route('hr.employees.index') }}">View/Search Employees</a>
        </div>
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'arch-emp-table'])
        <div class="table-responsive">
            <table id="arch-emp-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="arch-emp-check-all"></th>
                        <th>Empl. No.</th>
                        <th>Category</th>
                        <th>Employee</th>
                        <th>Phone No.</th>
                        <th>National ID</th>
                        <th>Email Address</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($employees as $row)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="arch-emp-row-check"></td>
                            <td>{{ $row->displayCode() }}</td>
                            <td>{{ optional($row->category)->name }}</td>
                            <td>{{ $row->fullName() }}</td>
                            <td>{{ $row->phone }}</td>
                            <td>{{ $row->national_id }}</td>
                            <td>{{ $row->email }}</td>
                            <td>{{ optional($row->branch)->name }}</td>
                            <td><span class="sx-status-inactive">Archived</span></td>
                            <td>
                                @if($canManage)
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">Action <span class="caret"></span></button>
                                        <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                            <li>
                                                <a href="#" class="sx-delete-expense" data-form="sx-restore-emp-{{ $row->id }}"><i class="fa fa-undo"></i> Restore</a>
                                                <form id="sx-restore-emp-{{ $row->id }}" action="{{ route('hr.employees.restore', $row->id) }}" method="post" class="hidden">@csrf</form>
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
    'tableId' => 'arch-emp-table', 'lengthId' => 'arch-emp-table-length', 'searchId' => 'arch-emp-table-search',
    'exportId' => 'arch-emp-table-export', 'colvisId' => 'arch-emp-table-colvis',
    'checkAll' => 'arch-emp-check-all', 'rowCheck' => 'arch-emp-row-check',
    'title' => 'Archived Employees', 'filename' => 'archived-employees', 'noSort' => [0, 9], 'order' => [[1, 'desc']],
])
@push('scripts')
<script>
(function ($) {
    $(document).on('click', '.sx-delete-expense', function (e) {
        e.preventDefault();
        var form = document.getElementById($(this).data('form'));
        if (!form) return;
        if (window.Swal) {
            Swal.fire({icon:'question',title:'Restore Employee',text:'Move this employee back to the active list?',showCancelButton:true,confirmButtonText:'Restore'})
                .then(function (r) { if (r.isConfirmed) form.submit(); });
            return;
        }
        if (confirm('Restore this employee?')) form.submit();
    });
})(jQuery);
</script>
@endpush
