@extends('layouts.master')

@section('title')
    KRA Tax Report
@endsection

@section('breadcrumb')
    @parent
    <li class="active">KRA Tax Report</li>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
@endpush

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">KRA Tax Report - VAT Returns</h3>
                <button onclick="updatePeriode()" class="btn btn-primary btn-flat"><i class="fa fa-calendar"></i> Select Date Range</button>
                <button onclick="exportPdf()" class="btn btn-danger btn-flat"><i class="fa fa-file-pdf-o"></i> Export PDF</button>
                <button onclick="exportExcel()" class="btn btn-success btn-flat"><i class="fa fa-file-excel-o"></i> Export Excel</button>
            </div>
            <div class="box-body table-responsive">
                <table id="kraTaxTable" class="table table-stiped table-bordered table-hover">
                    <thead>
                        <th width="5%">#</th>
                        <th>Date</th>
                        <th>Receipt Number</th>
                        <th>Subtotal (Before Tax)</th>
                        <th>Tax Amount (16% VAT)</th>
                        <th>Total Sale Amount</th>
                        <th>Tax Rate</th>
                    </thead>
                    <tfoot>
                        <tr>
                            <th colspan="3" style="text-align: right;">TOTAL:</th>
                            <th id="totalSubtotal"></th>
                            <th id="totalTax"></th>
                            <th id="totalSales"></th>
                            <th>16%</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

@includeIf('reports.form')
@endsection

@push('scripts')
<script src="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>

<script>
    let table;

    $(function () {
        table = $('#kraTaxTable').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: {
                url: '{{ route('reports.kra-tax.data') }}',
                data: function (d) {
                    d.start_date = $('#start_date').val();
                    d.end_date = $('#end_date').val();
                },
            },
            columns: [
                { data: 'DT_RowIndex', searchable: false, sortable: false },
                { data: 'date', searchable: true },
                { data: 'receipt_number', searchable: true },
                { data: 'subtotal_before_tax', searchable: false },
                { data: 'tax_amount', searchable: false },
                { data: 'total_sale', searchable: false },
                { data: 'tax_rate', searchable: false },
            ],
            footerCallback: function (row, data, start, end, display) {
                var api = this.api();
                var intVal = function (i) {
                    return typeof i === 'string' ?
                        i.replace(/[^\d.-]/g, '') * 1 :
                        typeof i === 'number' ?
                        i : 0;
                };

                // Calculate totals
                var totalSubtotal = api.column(3).data().reduce(function (a, b) {
                    return intVal(a) + intVal(b);
                }, 0);
                
                var totalTax = api.column(4).data().reduce(function (a, b) {
                    return intVal(a) + intVal(b);
                }, 0);
                
                var totalSales = api.column(5).data().reduce(function (a, b) {
                    return intVal(a) + intVal(b);
                }, 0);

                $('#totalSubtotal').html('Ksh ' + totalSubtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                $('#totalTax').html('Ksh ' + totalTax.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                $('#totalSales').html('Ksh ' + totalSales.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            },
        });

        $('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true
        });
    });

    function updatePeriode() {
        $('#modal-form').modal('show');
    }

    function exportPdf() {
        // Get dates from modal form or use defaults
        var startDate = $('#modal-form #start_date').val() || $('#start_date').val() || '{{ $startDate ?? date('Y-m-01') }}';
        var endDate = $('#modal-form #end_date').val() || $('#end_date').val() || '{{ $endDate ?? date('Y-m-d') }}';
        
        if (!startDate || startDate === '') {
            startDate = '{{ date('Y-m-01') }}';
        }
        if (!endDate || endDate === '') {
            endDate = '{{ date('Y-m-d') }}';
        }
        
        var url = '{{ route('reports.kra-tax.export-pdf') }}?start_date=' + encodeURIComponent(startDate) + '&end_date=' + encodeURIComponent(endDate);
        window.open(url, '_blank');
    }

    function exportExcel() {
        // Get dates from modal form or use defaults
        var startDate = $('#modal-form #start_date').val() || $('#start_date').val() || '{{ $startDate ?? date('Y-m-01') }}';
        var endDate = $('#modal-form #end_date').val() || $('#end_date').val() || '{{ $endDate ?? date('Y-m-d') }}';
        
        if (!startDate || startDate === '') {
            startDate = '{{ date('Y-m-01') }}';
        }
        if (!endDate || endDate === '') {
            endDate = '{{ date('Y-m-d') }}';
        }
        
        var url = '{{ route('reports.kra-tax.export-excel') }}?start_date=' + encodeURIComponent(startDate) + '&end_date=' + encodeURIComponent(endDate);
        window.location.href = url;
    }

    $('#modal-form').on('shown.bs.modal', function() {
        $('#start_date').focus();
    }).on('submit', function(e) {
        if (!e.isDefaultPrevented()) {
            $('#start_date').val($('#start_date').val());
            $('#end_date').val($('#end_date').val());
            table.ajax.reload();
            $('#modal-form').modal('hide');
        }
        return false;
    });
</script>
@endpush

