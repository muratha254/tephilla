@extends('layouts.master')

@section('title')
    Supplier Withdrawals List
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Supplier Withdrawals List</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Supplier Withdrawals</h3>
            </div>
            <div class="box-body">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        {{ session('success') }}
                    </div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        {{ $errors->first('error') ?: $errors->first() }}
                    </div>
                @endif
                <!-- Filter Form -->
                @php
                    $startFilterDisplay = '';
                    $endFilterDisplay = '';
                    if (request()->filled('start_date')) {
                        try {
                            $startFilterDisplay = \Carbon\Carbon::parse(request('start_date'))->format('d/m/Y');
                        } catch (\Throwable $e) {
                        }
                    }
                    if (request()->filled('end_date')) {
                        try {
                            $endFilterDisplay = \Carbon\Carbon::parse(request('end_date'))->format('d/m/Y');
                        } catch (\Throwable $e) {
                        }
                    }
                @endphp
                <form id="filter-form" class="mb-3" style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="text" name="start_date" id="start_date" class="form-control datepicker" autocomplete="off"
                                    placeholder="dd/mm/yyyy" value="{{ $startFilterDisplay }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="text" name="end_date" id="end_date" class="form-control datepicker" autocomplete="off"
                                    placeholder="dd/mm/yyyy" value="{{ $endFilterDisplay }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="supplier_id">Supplier</label>
                                <select name="supplier_id" id="supplier_id" class="form-control select2">
                                    <option value="">All Suppliers</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id_supplier }}"
                                            {{ request('supplier_id') == $supplier->id_supplier ? 'selected' : '' }}>
                                            {{ $supplier->nama }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div>
                                    <button type="button" class="btn btn-primary btn-block" onclick="applyFilters()">
                                        <i class="fa fa-search"></i> Apply Filters
                                    </button>
                                    <button type="button" class="btn btn-success btn-block" onclick="exportPdf()" style="margin-top: 5px;">
                                        <i class="fa fa-file-pdf-o"></i> Export PDF
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- DataTable -->
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover" id="withdrawals-table">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th style="display:none;">ID</th>
                                <th>Withdrawal #</th>
                                <th>Receipt No.</th>
                                <th>Date</th>
                                <th>Supplier</th>
                                <th>Product Code</th>
                                <th>Product Name</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total Amount</th>
                                <th>Remaining Stock</th>
                                <th>Reason</th>
                                <th>Processed By</th>
                                <th width="14%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="8" class="text-right"><strong>Totals:</strong></td>
                                <td id="total-quantity" class="text-center"><strong>0</strong></td>
                                <td></td>
                                <td id="total-amount" class="text-right"><strong>0.00</strong></td>
                                <td></td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
@endpush

@push('scripts')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
<script>
function swPad2(s) {
    s = String(s).trim();
    return s.length === 1 ? '0' + s : s;
}
function swToYmd(ddmmyyyy) {
    if (!ddmmyyyy || String(ddmmyyyy).trim() === '') return '';
    var parts = String(ddmmyyyy).trim().split('/');
    if (parts.length === 3) {
        return parts[2] + '-' + swPad2(parts[1]) + '-' + swPad2(parts[0]);
    }
    return '';
}
function swParseDdMmYyyy(s) {
    if (!s || String(s).trim() === '') return null;
    var p = String(s).trim().split('/');
    if (p.length !== 3) return null;
    var d = parseInt(p[0], 10), m = parseInt(p[1], 10) - 1, y = parseInt(p[2], 10);
    if (isNaN(d) || isNaN(m) || isNaN(y)) return null;
    return new Date(y, m, d);
}
$(document).ready(function() {
    $('.datepicker').datepicker({
        format: 'dd/mm/yyyy',
        autoclose: true,
        todayHighlight: true
    });

    // Initialize Select2 for supplier dropdown
    $('#supplier_id').select2({
        placeholder: 'Search or select supplier',
        allowClear: true,
        width: '100%',
        minimumInputLength: 0
    });

    let table = $('#withdrawals-table').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        autoWidth: false,
        ajax: {
            url: '{{ route('supplier.withdrawals.data') }}',
            data: function(d) {
                d.start_date = swToYmd($('#start_date').val());
                d.end_date = swToYmd($('#end_date').val());
                d.supplier_id = $('#supplier_id').val();
            }
        },
        columns: [
            {data: 'DT_RowIndex', searchable: false, sortable: false},
            {data: 'id', name: 'id', visible: false, searchable: false},
            {data: 'withdrawal_number_display', name: 'withdrawal_number'},
            {data: 'receipt_no_display', name: 'receipt_no'},
            {data: 'withdrawal_date_formatted', name: 'withdrawal_date'},
            {data: 'supplier_name', name: 'supplier.nama'},
            {data: 'product_code', name: 'produk.kode_produk'},
            {data: 'product_name', name: 'produk.nama_produk'},
            {data: 'quantity', name: 'quantity', className: 'text-center'},
            {data: 'unit_price_formatted', name: 'unit_price', className: 'text-right'},
            {data: 'total_amount_formatted', name: 'total_amount', className: 'text-right'},
            {data: 'remaining_stock', name: 'produk.stok', className: 'text-center'},
            {data: 'reason', name: 'reason'},
            {data: 'user_name', name: 'user.name'},
            {data: 'action', name: 'action', searchable: false, orderable: false, className: 'text-center'},
        ],
        order: [[1, 'desc']],
        pageLength: 25,
        footerCallback: function (row, data, start, end, display) {
            var api = this.api();
            
            // Calculate totals for visible rows only
            var totalQuantity = 0;
            var totalAmount = 0;
            
            api.rows({search: 'applied'}).data().each(function(row) {
                totalQuantity += parseInt(row.quantity || 0);
                totalAmount += parseFloat(row.total_amount || 0);
            });
            
            // Update footer
            $('#total-quantity').html('<strong>' + totalQuantity.toLocaleString() + '</strong>');
            $('#total-amount').html('<strong>' + totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</strong>');
        }
    });

    // Validate date range
    $('#filter-form').on('submit', function(e) {
        e.preventDefault();
        applyFilters();
    });

    function applyFilters() {
        var startDate = swParseDdMmYyyy($('#start_date').val());
        var endDate = swParseDdMmYyyy($('#end_date').val());
        if (startDate && endDate && endDate < startDate) {
            alert('End date cannot be earlier than start date');
            return false;
        }

        table.ajax.reload();
    }

    window.applyFilters = applyFilters;

    function exportPdf() {
        var startDate = swToYmd($('#start_date').val());
        var endDate = swToYmd($('#end_date').val());
        var supplierId = $('#supplier_id').val();

        var url = "{{ route('supplier.withdrawals.export-pdf') }}";
        var params = [];
        
        if (startDate) {
            params.push('start_date=' + encodeURIComponent(startDate));
        }
        if (endDate) {
            params.push('end_date=' + encodeURIComponent(endDate));
        }
        if (supplierId) {
            params.push('supplier_id=' + encodeURIComponent(supplierId));
        }
        
        if (params.length > 0) {
            url += '?' + params.join('&');
        }

        window.location.href = url;
    }

    window.exportPdf = exportPdf;
});
</script>
@endpush

