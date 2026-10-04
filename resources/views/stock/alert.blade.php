@extends('layouts.fleet')

@section('title', 'Stocks Running low')

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/css/dataTables.bootstrap.css') }}">
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Stocks Running low',
    'subtitle' => 'View/Search Items',
    'backUrl' => route('products.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Stocks Running low'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <h3 class="sx-box-title" style="flex:none;margin:0;">
            Stock Alert
            <span class="sx-stock-badge">Out of stock {{ (int) ($outOfStockCount ?? 0) }}</span>
            <span class="sx-stock-badge" style="background:#A2502B;">Low / alert {{ $products->count() }}</span>
        </h3>
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="alert-length"></div>
            <div id="alert-search"></div>
        </div>

        <div class="table-responsive">
            <table id="alert-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Category Name</th>
                        <th>Brand</th>
                        <th>Item Name</th>
                        <th>Reorder Level</th>
                        <th>Stock Available</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $index => $product)
                        @php $onHand = (float) $product->stock_on_hand; @endphp
                        <tr class="{{ $onHand <= 0 ? 'sx-row-out-of-stock' : '' }}">
                            <td>{{ $index + 1 }}</td>
                            <td>{{ optional($product->category)->name }}</td>
                            <td>{{ optional($product->brand)->name }}</td>
                            <td>
                                {{ $product->name }}
                                @if($onHand <= 0)
                                    <span class="label label-danger">Out of stock</span>
                                @endif
                            </td>
                            <td data-order="{{ (float) $product->reorder_level }}">{{ number_format((float) $product->reorder_level, 2) }}</td>
                            <td data-order="{{ $onHand }}">{{ number_format($onHand, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js') }}"></script>
<script>
(function ($) {
    $('#alert-table').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[3, 'asc']],
        autoWidth: false,
        columnDefs: [
            { targets: [0], orderable: false, searchable: false }
        ],
        language: {
            lengthMenu: 'Show _MENU_ entries',
            search: 'Search:',
            zeroRecords: 'No matching items found',
            emptyTable: 'No data available in table',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'Showing 0 to 0 of 0 entries',
            paginate: { previous: 'Previous', next: 'Next' }
        },
        initComplete: function () {
            var wrap = $(this.api().table().container());
            wrap.find('.dataTables_length').appendTo('#alert-length');
            wrap.find('.dataTables_filter').appendTo('#alert-search');
        }
    });
})(jQuery);
</script>
@endpush
