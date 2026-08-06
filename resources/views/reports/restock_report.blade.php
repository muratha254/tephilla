@extends('layouts.master')

@section('title')
    Restock Report
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Restock Report</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Restock Report</h3>
                <div class="box-tools">
                    <a href="{{ route('reports.restock.export-pdf') }}" class="btn btn-success btn-sm btn-flat">
                        <i class="fa fa-file-pdf-o"></i> Export PDF
                    </a>
                </div>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="form-group row">
                            <label for="date_range" class="col-lg-2">Date Range</label>
                            <div class="col-lg-4">
                                <input type="text" name="date_range" id="date_range" class="form-control" 
                                    value="{{ request('date_range') }}" placeholder="Select date range">
                            </div>
                        </div>
                    </div>
                </div>
                <table class="table table-stiped table-bordered table-hover">
                    <thead>
                        <th width="5%">#</th>
                        <th>Date</th>
                        <th>Product Code</th>
                        <th>Product Name</th>
                        <th>Supplier</th>
                        <th>Quantity Added</th>
                        <th>Unit Price</th>
                        <th>Total Value</th>
                    </thead>
                    <tbody></tbody>
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
                url: '{{ route("reports.restock.data") }}',
                data: function(d) {
                    d.date_range = $('#date_range').val();
                }
            },
            columns: [
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'date'},
                {data: 'product_code'},
                {data: 'product_name'},
                {data: 'supplier_name'},
                {data: 'quantity_added'},
                {data: 'unit_price'},
                {data: 'total_value'}
            ],
            dom: 'Brt',
            bSort: true,
            bPaginate: true,
        });

        $('#date_range').daterangepicker({
            locale: {
                format: 'YYYY-MM-DD'
            },
            drops: 'down',
            opens: 'right'
        });

        $('#date_range').on('change', function() {
            table.ajax.reload();
        });
    });
</script>
@endpush
