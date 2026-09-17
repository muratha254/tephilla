@extends('layouts.fleet')
@section('title', 'Allowances/Deductions')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Allowances/Deductions',
    'subtitle' => 'View/Search Charges(Allowances/Deductions)',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Allowances/Deductions'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div></div>
        @if($canManage)
            <div class="sx-toolbar-actions">
                <a href="{{ route('hr.allowances.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Add Allowances/Deductions</a>
            </div>
        @endif
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'charge-table'])
        <div class="table-responsive">
            <table id="charge-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="charge-check-all"></th>
                        <th>Charge Name</th>
                        <th>Category</th>
                        <th>Tax</th>
                        <th>Created Date</th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($charges as $row)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="charge-row-check"></td>
                            <td>{{ $row->name }}</td>
                            <td>{{ $row->category }}</td>
                            <td>{{ $row->taxable }}</td>
                            <td data-order="{{ $row->created_at }}">{{ optional($row->created_at)->format('Y-m-d') }}</td>
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
                                            <li><a href="{{ route('hr.allowances.edit', $row) }}"><i class="fa fa-edit"></i> Edit</a></li>
                                            <li>
                                                <a href="#" class="sx-delete-expense" data-form="sx-del-charge-{{ $row->id }}"><i class="fa fa-trash"></i> Delete</a>
                                                <form id="sx-del-charge-{{ $row->id }}" action="{{ route('hr.allowances.destroy', $row) }}" method="post" class="hidden">@csrf @method('DELETE')</form>
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
    'tableId' => 'charge-table', 'lengthId' => 'charge-table-length', 'searchId' => 'charge-table-search',
    'exportId' => 'charge-table-export', 'colvisId' => 'charge-table-colvis',
    'checkAll' => 'charge-check-all', 'rowCheck' => 'charge-row-check',
    'title' => 'Allowances Deductions', 'filename' => 'allowances-deductions', 'noSort' => [0, 6], 'order' => [[4, 'desc']],
])
@push('scripts')
<script>
(function ($) {
    $(document).on('click', '.sx-delete-expense', function (e) {
        e.preventDefault();
        var form = document.getElementById($(this).data('form'));
        if (!form) return;
        if (window.Swal) {
            Swal.fire({icon:'warning',title:'Delete Charge',text:'This charge will be removed.',showCancelButton:true,confirmButtonColor:'#dd4b39',confirmButtonText:'Delete'})
                .then(function (r) { if (r.isConfirmed) form.submit(); });
            return;
        }
        if (confirm('Delete this charge?')) form.submit();
    });
})(jQuery);
</script>
@endpush
