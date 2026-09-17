@extends('layouts.fleet')
@section('title', 'BOM List')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'BOM List',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'BOM List'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div></div>
        @if($canManage)
            <div class="sx-toolbar-actions">
                <a href="{{ route('manufacturing.bom.create') }}" class="btn sx-btn-aqua"><i class="fa fa-plus"></i> Create New BOM</a>
            </div>
        @endif
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'bom-table'])
        <div class="table-responsive">
            <table id="bom-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="bom-check-all"></th>
                        <th>Product Name</th>
                        <th>Description</th>
                        <th>Sales Price</th>
                        <th>Prod. Cost</th>
                        <th>Status</th>
                        <th>Branch</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($boms as $row)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="bom-row-check"></td>
                            <td>{{ optional($row->product)->name }}</td>
                            <td>{{ $row->description }}</td>
                            <td data-order="{{ optional($row->product)->selling_price }}">{{ number_format((float) optional($row->product)->selling_price, 2) }}</td>
                            <td data-order="{{ $row->production_cost }}">{{ number_format((float) $row->production_cost, 2) }}</td>
                            <td><span class="sx-status-active">{{ $row->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td>{{ optional($row->branch)->name }}</td>
                            <td>
                                @if($canManage)
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">Action <span class="caret"></span></button>
                                        <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                            <li><a href="{{ route('manufacturing.bom.edit', $row) }}"><i class="fa fa-edit"></i> Edit</a></li>
                                            <li>
                                                <a href="#" class="sx-delete-expense" data-form="sx-del-bom-{{ $row->id }}"><i class="fa fa-trash"></i> Delete</a>
                                                <form id="sx-del-bom-{{ $row->id }}" action="{{ route('manufacturing.bom.destroy', $row) }}" method="post" class="hidden">@csrf @method('DELETE')</form>
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
    'tableId' => 'bom-table', 'lengthId' => 'bom-table-length', 'searchId' => 'bom-table-search',
    'exportId' => 'bom-table-export', 'colvisId' => 'bom-table-colvis',
    'checkAll' => 'bom-check-all', 'rowCheck' => 'bom-row-check',
    'title' => 'BOM List', 'filename' => 'bom-list', 'noSort' => [0, 7], 'order' => [[1, 'asc']],
])
@push('scripts')
<script>
(function ($) {
    $(document).on('click', '.sx-delete-expense', function (e) {
        e.preventDefault();
        var form = document.getElementById($(this).data('form'));
        if (!form) return;
        if (window.Swal) {
            Swal.fire({icon:'warning',title:'Delete BOM',text:'This BOM will be removed.',showCancelButton:true,confirmButtonColor:'#dd4b39',confirmButtonText:'Delete'})
                .then(function (r) { if (r.isConfirmed) form.submit(); });
            return;
        }
        if (confirm('Delete this BOM?')) form.submit();
    });
})(jQuery);
</script>
@endpush
