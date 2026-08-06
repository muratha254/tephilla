@extends('layouts.master')

@section('title')
    Detailed Sales Report
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Detailed Sales Report</li>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
@endpush

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Detailed Sales Report</h3>
            </div>
            <div class="box-body">
                <form id="reportForm" method="GET" action="{{ route('penjualan.detailed-report.export') }}">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="period">Select Period</label>
                                <select name="period" id="period" class="form-control" required>
                                    <option value="today">Today</option>
                                    <option value="week">This Week</option>
                                    <option value="month">This Month</option>
                                    <option value="annual">This Year</option>
                                    <option value="custom">Custom Date Range</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="supplier_type">Sales Type</label>
                                <select name="supplier_type" id="supplier_type" class="form-control">
                                    <option value="all">All Items</option>
                                    <option value="consignment">Consignment Only</option>
                                    <option value="cash">Cash Only</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="button" id="btnViewReport" class="btn btn-primary btn-block btn-flat">
                                    <i class="fa fa-eye"></i> View Report
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-danger btn-block btn-flat">
                                    <i class="fa fa-file-pdf-o"></i> Print Detailed Report
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row" id="customDateRange" style="display: none;">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="text" name="start_date" id="start_date" class="form-control datepicker" 
                                    value="{{ date('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="text" name="end_date" id="end_date" class="form-control datepicker" 
                                    value="{{ date('Y-m-d') }}">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row" id="paymentCardsSection" style="display: none; margin-bottom: 20px;">
    <div class="col-md-4">
        <div class="info-box bg-green">
            <span class="info-box-icon"><i class="fa fa-money"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Cash</span>
                <span class="info-box-number" id="cardCash">Ksh 0.00</span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="info-box bg-blue">
            <span class="info-box-icon"><i class="fa fa-mobile"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Mpesa</span>
                <span class="info-box-number" id="cardMpesa">Ksh 0.00</span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="info-box bg-yellow">
            <span class="info-box-icon"><i class="fa fa-credit-card"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Card</span>
                <span class="info-box-number" id="cardCard">Ksh 0.00</span>
            </div>
        </div>
    </div>
</div>

<div class="row" id="reportTableSection" style="display: none;">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Sales Report Details</h3>
            </div>
            <div class="box-body">
                <table class="table table-bordered table-striped" id="sales-report-table">
                    <thead>
                        <tr>
                            <th width="2%" class="text-center">
                                <input type="checkbox" id="selectAllReportRows" title="Select all">
                            </th>
                            <th width="3%">#</th>
                            <th width="8%">Date</th>
                            <th width="10%">Receipt No</th>
                            <th width="12%">Product Code</th>
                            <th width="25%">Product Name</th>
                            <th class="text-right" width="8%">Qty</th>
                            <th class="text-right" width="10%">Unit Price</th>
                            <th class="text-right" width="8%">Discount(%)</th>
                            <th class="text-right" width="10%">Discount</th>
                            <th class="text-right" width="14%">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th></th>
                            <th colspan="5" class="text-right"><strong>TOTAL:</strong></th>
                            <th class="text-right" id="totalQuantity"></th>
                            <th colspan="3" class="text-right"><strong>Total Amount:</strong></th>
                            <th class="text-right" id="totalAmount"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
<script>
    $(function () {
        $('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true
        });

        $('#period').on('change', function() {
            if ($(this).val() === 'custom') {
                $('#customDateRange').show();
                $('#start_date').prop('required', true);
                $('#end_date').prop('required', true);
            } else {
                $('#customDateRange').hide();
                $('#start_date').prop('required', false);
                $('#end_date').prop('required', false);
            }
        });

        $('#reportForm').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var url = form.attr('action');
            var formData = form.serialize(); // includes period, start_date, end_date, supplier_type
            
            // Open PDF in new window
            window.open(url + '?' + formData, '_blank');
        });

        let salesTable = null;

        $('#btnViewReport').on('click', function() {
            var period = $('#period').val();
            var startDate = $('#start_date').val();
            var endDate = $('#end_date').val();
            var supplierType = $('#supplier_type').val();

            if (period === 'custom' && (!startDate || !endDate)) {
                alert('Please select start date and end date for custom period.');
                return;
            }

            // Destroy existing table if it exists
            if (salesTable) {
                salesTable.destroy();
            }

            // Show table and cards sections
            $('#reportTableSection').show();
            $('#paymentCardsSection').show();

            // Initialize DataTable
            salesTable = $('#sales-report-table').DataTable({
                responsive: true,
                processing: true,
                serverSide: false,
                autoWidth: false,
                ajax: {
                    url: '{{ route('penjualan.detailed-report.data') }}',
                    data: function(d) {
                        d.period = period;
                        d.start_date = startDate;
                        d.end_date = endDate;
                        d.supplier_type = supplierType;
                    },
                    dataSrc: function(json) {
                        if (json.payment_totals) {
                            $('#cardCash').text('Ksh ' + parseFloat(json.payment_totals.Cash || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                            $('#cardMpesa').text('Ksh ' + parseFloat(json.payment_totals.Mpesa || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                            $('#cardCard').text('Ksh ' + parseFloat(json.payment_totals.Card || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                        }
                        return json.data || json;
                    }
                },
                columns: [
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        render: function() {
                            return '<input type="checkbox" class="report-row-check">';
                        }
                    },
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', searchable: false, sortable: false },
                    { data: 'date', name: 'date' },
                    { data: 'receiptno', name: 'receiptno' },
                    { data: 'product_code', name: 'product_code' },
                    { data: 'product_name', name: 'product_name' },
                    { data: 'quantity', name: 'quantity', className: 'text-right' },
                    { data: 'unit_price', name: 'unit_price', className: 'text-right' },
                    { data: 'discount_percentage', name: 'discount_percentage', className: 'text-right' },
                    { data: 'discount', name: 'discount', className: 'text-right' },
                    { data: 'subtotal', name: 'subtotal', className: 'text-right' },
                ],
                footerCallback: function (row, data, start, end, display) {
                    var api = this.api();
                    
                    var intVal = function (i) {
                        if (typeof i === 'string') {
                            return parseFloat(i.replace(/[^0-9.]/g, '')) || 0;
                        }
                        return typeof i === 'number' ? i : 0;
                    };

                    // Total quantity (column 6) - all pages
                    var totalQuantity = api
                        .column(6)
                        .data()
                        .reduce(function (a, b) {
                            return intVal(a) + intVal(b);
                        }, 0);

                    // Total amount (column 10) - all pages
                    var totalAmount = api
                        .column(10)
                        .data()
                        .reduce(function (a, b) {
                            return intVal(a) + intVal(b);
                        }, 0);

                    $(api.column(6).footer()).html('<strong>' + totalQuantity.toLocaleString() + '</strong>');
                    $(api.column(10).footer()).html('<strong>Ksh ' + totalAmount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + '</strong>');
                },
                order: [[2, 'asc']],
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]]
            });

            // Select all / deselect all (current page)
            $('#sales-report-table').on('change', '#selectAllReportRows', function() {
                var checked = $(this).prop('checked');
                $('#sales-report-table').find('.report-row-check').prop('checked', checked);
            });

            // If any row checkbox is unchecked, uncheck "select all"
            $('#sales-report-table').on('change', '.report-row-check', function() {
                var total = $('#sales-report-table').find('.report-row-check').length;
                var checked = $('#sales-report-table').find('.report-row-check:checked').length;
                $('#selectAllReportRows').prop('checked', total > 0 && total === checked);
            });
        });
    });
</script>
@endpush



