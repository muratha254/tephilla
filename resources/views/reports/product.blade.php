@extends('layouts.master')

@section('title')
    Product Report
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Product Report</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Filter Report</h3>
            </div>
            <div class="box-body">
                <div class="form-group row">
                    <label for="shop_id" class="col-lg-2">Shop</label>
                    <div class="col-lg-4">
                        <select name="shop_id" id="shop_id" class="form-control">
                            <option value="">All Shops</option>
                            @foreach($shops as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="supplier_id" class="col-lg-2">Supplier</label>
                    <div class="col-lg-4">
                        <select name="supplier_id" id="supplier_id" class="form-control">
                            <option value="">All Suppliers</option>
                            @foreach($suppliers as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="item_code" class="col-lg-2">Item Code</label>
                    <div class="col-lg-4">
                        <input type="text" name="item_code" id="item_code" class="form-control" placeholder="Enter Item Code">
                    </div>
                </div>
            </div>
            <div class="box-footer">
                <button type="button" class="btn btn-primary" onclick="updateTable()"><i class="fa fa-search"></i> Filter</button>
                <button type="button" class="btn btn-success" onclick="exportPDF()"><i class="fa fa-file-pdf-o"></i> Export PDF</button>
            </div>
        </div>

        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Product List</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-stiped table-bordered">
                    <thead>
                        <th width="5%">No</th>
                        <th>Shop</th>
                        <th>Supplier</th>
                        <th>Item Code</th>
                        <th>Product Name</th>
                        <th>Purchase Price</th>
                        <th>Selling Price</th>
                        <th>Stock</th>
                        <th>MOP</th>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let table;

    $(function () {
        table = $('.table').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: {
                url: '{{ route('report.product.data') }}',
                data: function (d) {
                    d.shop_id = $('#shop_id').val();
                    d.supplier_id = $('#supplier_id').val();
                    d.item_code = $('#item_code').val();
                }
            },
            columns: [
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'shop_name'},
                {data: 'supplier_name'},
                {data: 'item_code'},
                {data: 'nama_produk'},
                {data: 'harga_beli'},
                {data: 'harga_jual'},
                {data: 'stok'},
                {data: 'mop'}
            ]
        });
    });

    function updateTable() {
        table.ajax.reload();
    }

    function exportPDF() {
        let params = new URLSearchParams({
            shop_id: $('#shop_id').val(),
            supplier_id: $('#supplier_id').val(),
            item_code: $('#item_code').val()
        });
        window.location.href = `{{ route('report.product.pdf') }}?${params.toString()}`;
    }
</script>
@endpush
