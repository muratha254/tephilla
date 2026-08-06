@extends('layouts.master')

@section('title')
    Service Fee Report
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Service Fee</li>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
@endpush

@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Service Fee Report</h3>
                    <button onclick="updatePeriode()" class="btn btn-primary btn-flat"><i class="fa fa-calendar"></i> Filter By Date</button>
                </div>

                <div class="box-body">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <h4><i class="icon fa fa-info-circle"></i> Summary</h4>
                                <div id="summary-info">
                                    <p><strong>Total Service Fee:</strong> <span id="total-commission">Ksh 0.00</span></p>
                                    <p><strong>Total Subtotal (Before VAT):</strong> <span id="total-subtotal">Ksh 0.00</span></p>
                                    <p><strong>Commission Rate:</strong> <span id="commission-rate">0%</span></p>
                                    <p><strong>Total Sales:</strong> <span id="total-sales">0</span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-12">
                            <table id="service-fee-table" class="table table-stiped table-bordered table-hover">
                                <thead>
                                    <th width="5%">#</th>
                                    <th>Date</th>
                                    <th>Receipt No</th>
                                    <th>Subtotal (Before VAT)</th>
                                    <th>Service Fee</th>
                                    <th>Commission Rate</th>
                                    <th>Total Payable</th>
                                    <th>Cashier</th>
                                    <th width="10%"><i class="fa fa-cog"></i></th>
                                </thead>
                                <tfoot>
                                    <tr>
                                        <th colspan="3">Total</th>
                                        <th id="footer-subtotal"></th>
                                        <th id="footer-commission"></th>
                                        <th></th>
                                        <th id="footer-total"></th>
                                        <th></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @includeIf('penjualan.form')
@endsection

@push('scripts')
    <script src="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>

    <script>
        let table;

        $(function () {
            table = $('#service-fee-table').DataTable({
                responsive: true,
                processing: true,
                serverSide: true,
                autoWidth: false,
                ajax: {
                    url: '{{ route('driver-commission.data') }}',
                    data: function (d) {
                        d.start_date = $('#start_date').val();
                        d.end_date = $('#end_date').val();
                    },
                },
                columns: [
                    { data: 'DT_RowIndex', searchable: false, sortable: false },
                    { data: 'saledate' },
                    { data: 'receiptno' },
                    { data: 'subtotal' },
                    { data: 'driver_commission' },
                    { data: 'commission_rate' },
                    { data: 'total_payable' },
                    { data: 'kasir' },
                    { data: 'aksi', searchable: false, sortable: false },
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
                    var totalSubtotal = api
                        .column(3)
                        .data()
                        .reduce(function (a, b) {
                            return intVal(a) + intVal(b);
                        }, 0);

                    var totalCommission = api
                        .column(4)
                        .data()
                        .reduce(function (a, b) {
                            return intVal(a) + intVal(b);
                        }, 0);

                    var totalPayable = api
                        .column(6)
                        .data()
                        .reduce(function (a, b) {
                            return intVal(a) + intVal(b);
                        }, 0);

                    $(api.column(3).footer()).html('Ksh ' + totalSubtotal.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'));
                    $(api.column(4).footer()).html('Ksh ' + totalCommission.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'));
                    $(api.column(6).footer()).html('Ksh ' + totalPayable.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'));

                    // Update summary
                    updateSummary(totalSubtotal, totalCommission);
                }
            });

            $('.datepicker').datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true
            });

            // Load initial summary
            loadSummary();
        });

        function updateSummary(totalSubtotal, totalCommission) {
            $('#total-commission').text('Ksh ' + totalCommission.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'));
            $('#total-subtotal').text('Ksh ' + totalSubtotal.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'));
        }

        function loadSummary() {
            $.get('{{ route('driver-commission.summary') }}', {
                filter_type: 'monthly'
            })
            .done(function(response) {
                $('#total-commission').text('Ksh ' + parseFloat(response.total_commission).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'));
                $('#total-subtotal').text('Ksh ' + parseFloat(response.total_subtotal).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'));
                $('#commission-rate').text(response.commission_rate + '%');
                $('#total-sales').text(response.total_sales);
            });
        }

        function updatePeriode() {
            $('#modal-form').modal('show');
        }

        function showDetail(url) {
            $('#modal-detail').modal('show');
            // Load the detail content via AJAX
            $.get(url)
                .done(function(response) {
                    $('#modal-detail-body').html(response);
                })
                .fail(function() {
                    $('#modal-detail-body').html('<div class="alert alert-danger">Unable to load details</div>');
                });
        }
    </script>
@endpush

@includeIf('driver_commission.detail')






