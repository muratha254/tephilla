@extends('layouts.master')

@section('title')
    Purchase Orders Report
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Purchase Orders Report</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Purchase Orders & Received Orders Report</h3>
            </div>
            <div class="box-body">
                @if(session('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                    </div>
                @endif
                
                <form action="{{ route('reports.purchase-orders') }}" method="GET" id="filter-form" class="mb-3">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="report_type">Report Type</label>
                                <select name="report_type" id="report_type" class="form-control">
                                    <option value="purchase_orders" {{ $reportType == 'purchase_orders' ? 'selected' : '' }}>Purchase Orders</option>
                                    <option value="received_orders" {{ $reportType == 'received_orders' ? 'selected' : '' }}>Received Orders</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" name="start_date" id="start_date" class="form-control"
                                    value="{{ $startDate }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" name="end_date" id="end_date" class="form-control"
                                    value="{{ $endDate }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="supplier_id">Supplier</label>
                                <select name="supplier_id" id="supplier_id" class="form-control select2">
                                    <option value="">All Suppliers</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id_supplier }}"
                                            {{ $supplierId == $supplier->id_supplier ? 'selected' : '' }}>
                                            {{ $supplier->nama }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fa fa-search"></i> Filter
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <button type="button" class="btn btn-success" onclick="exportReport()">
                                <i class="fa fa-file-pdf-o"></i> Export PDF
                            </button>
                            <button type="button" class="btn btn-info" onclick="window.print()">
                                <i class="fa fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                </form>

                @if($reportType == 'received_orders')
                    <!-- Received Orders Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered" id="received-orders-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Reference #</th>
                                    <th>PO Number</th>
                                    <th>Supplier</th>
                                    <th>Items</th>
                                    <th>Total Quantity</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($receivedOrders as $receipt)
                                <tr>
                                    <td>{{ $receipt->received_date ? date('Y-m-d', strtotime($receipt->received_date)) : '-' }}</td>
                                    <td>{{ $receipt->reference_number }}</td>
                                    <td>{{ $receipt->batch->po_number ?? '-' }}</td>
                                    <td>{{ $receipt->batch->supplier->nama ?? 'N/A' }}</td>
                                    <td>
                                        <ul class="list-unstyled">
                                            @foreach($receipt->items as $item)
                                            <li>{{ $item->product->nama_produk ?? 'N/A' }} (Qty: {{ $item->quantity }})</li>
                                            @endforeach
                                        </ul>
                                    </td>
                                    <td>{{ $receipt->items->sum('quantity') }}</td>
                                    <td>{{ $receipt->notes ?? '-' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center">No received orders found for the selected criteria.</td>
                                </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="5" class="text-right"><strong>Total Orders:</strong></td>
                                    <td><strong>{{ $receivedOrders->sum(function($r) { return $r->items->sum('quantity'); }) }}</strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <!-- Purchase Orders Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered" id="purchase-orders-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>PO Number</th>
                                    <th>Reference #</th>
                                    <th>Supplier</th>
                                    <th>Status</th>
                                    <th>Items</th>
                                    <th>Total Quantity</th>
                                    <th>Received Quantity</th>
                                    <th>Remaining</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($purchaseOrders as $order)
                                <tr>
                                    <td>{{ $order->order_date ? date('Y-m-d', strtotime($order->order_date)) : '-' }}</td>
                                    <td>{{ $order->po_number }}</td>
                                    <td>{{ $order->reference_number ?? '-' }}</td>
                                    <td>
                                        @php
                                            $uniqueSuppliers = $order->items->map(function($item) {
                                                if ($item->supplier_id && $item->supplier) {
                                                    return $item->supplier->nama;
                                                } elseif ($item->product && $item->product->supplier) {
                                                    return $item->product->supplier->nama;
                                                }
                                                return $order->supplier->nama ?? 'N/A';
                                            })->unique()->filter();
                                        @endphp
                                        @if($uniqueSuppliers->count() > 1)
                                            <span class="text-info">Multiple Suppliers</span>
                                        @else
                                            {{ $uniqueSuppliers->first() ?? $order->supplier->nama ?? 'N/A' }}
                                        @endif
                                    </td>
                                    <td>
                                        <span class="label label-{{ $order->status == 'completed' ? 'success' : ($order->status == 'pending' ? 'warning' : 'info') }}">
                                            {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                        </span>
                                    </td>
                                    <td>
                                        <ul class="list-unstyled">
                                            @foreach($order->items as $item)
                                            <li>
                                                {{ $item->product->nama_produk ?? 'N/A' }} 
                                                (Qty: {{ $item->quantity }}, 
                                                @if($item->supplier_id && $item->supplier)
                                                    Supplier: {{ $item->supplier->nama }}
                                                @elseif($item->product && $item->product->supplier)
                                                    Supplier: {{ $item->product->supplier->nama }}
                                                @endif
                                                )
                                            </li>
                                            @endforeach
                                        </ul>
                                    </td>
                                    <td>{{ $order->items->sum('quantity') }}</td>
                                    <td>{{ $order->items->sum('received_quantity') }}</td>
                                    <td>{{ $order->items->sum('quantity') - $order->items->sum('received_quantity') }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center">No purchase orders found for the selected criteria.</td>
                                </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="6" class="text-right"><strong>Total Ordered:</strong></td>
                                    <td><strong>{{ $purchaseOrders->sum(function($o) { return $o->items->sum('quantity'); }) }}</strong></td>
                                    <td><strong>{{ $purchaseOrders->sum(function($o) { return $o->items->sum('received_quantity'); }) }}</strong></td>
                                    <td><strong>{{ $purchaseOrders->sum(function($o) { return $o->items->sum('quantity') - $o->items->sum('received_quantity'); }) }}</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<style>
    @media print {
        .box-header, .btn, #filter-form {
            display: none !important;
        }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2').select2();

    $('#purchase-orders-table, #received-orders-table').DataTable({
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'print'
        ],
        order: [[0, 'desc']],
        pageLength: 25
    });

    // Validate date range
    $('#filter-form').on('submit', function(e) {
        var startDate = new Date($('#start_date').val());
        var endDate = new Date($('#end_date').val());

        if (endDate < startDate) {
            e.preventDefault();
            alert('End date cannot be earlier than start date');
            return false;
        }
    });
});

function exportReport() {
    var startDate = $('#start_date').val();
    var endDate = $('#end_date').val();
    var supplierId = $('#supplier_id').val();
    var reportType = $('#report_type').val();

    var url = "{{ route('reports.purchase-orders.export-pdf') }}";
    url += '?start_date=' + startDate + '&end_date=' + endDate + '&report_type=' + reportType;
    if (supplierId) {
        url += '&supplier_id=' + supplierId;
    }

    window.location.href = url;
}
</script>
@endpush









