@extends('layouts.master')

@section('title')
    Management Sales List
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Management Sales List</li>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
@endpush

@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-gift"></i> Management Sales List</h3>
                    <div class="box-tools pull-right">
                        <button onclick="updatePeriode()" class="btn btn-primary btn-flat"><i class="fa fa-plus-circle"></i> Search By Date</button>
                        <button class="btn btn-danger btn-flat" id="printButton"><i class="fa fa-file-pdf-o"></i> Export to PDF</button>
                        <button class="btn btn-warning btn-flat" id="printCostButton"><i class="fa fa-file-pdf-o"></i> Export Cost Price PDF</button>
                        <a href="{{ route('transaksi.baru.management') }}" class="btn btn-success btn-flat"><i class="fa fa-plus"></i> New Management Sale</a>
                    </div>
                </div>

                <div class="box-body" style="padding-bottom: 0;">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label for="filter_receipt_number_live">Receipt number</label>
                                <div class="input-group">
                                    <input type="text"
                                           id="filter_receipt_number_live"
                                           class="form-control"
                                           value="{{ request('receipt_number') }}"
                                           placeholder="Type receipt no. — list updates as you type"
                                           autocomplete="off">
                                    <span class="input-group-addon"><i class="fa fa-search"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label for="filter_item_code_live">Item code</label>
                                <div class="input-group">
                                    <input type="text"
                                           id="filter_item_code_live"
                                           class="form-control"
                                           value="{{ request('item_code') }}"
                                           placeholder="Type item code — list updates as you type"
                                           autocomplete="off">
                                    <span class="input-group-addon"><i class="fa fa-search"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="box-body table-responsive" style="padding-top: 0;">
                    <table id="yourDataTable" class="table table-stiped table-bordered table-penjualan table-hover">
                        <thead>
                            <th width="3%"><input type="checkbox" id="checkAllManagementRows" title="Select all visible"></th>
                            <th width="5%">#</th>
                            <th>Date</th>
                            <th>Receipt No</th>
                            <th>Quantity</th>
                            <th>Total Price</th>
                            <th>Discount</th>
                            <th>Total Pay</th>
                            <th>Payment Mode</th>
                            <th>Product Name(s)</th>
                            <th>Shop(s)</th>
                            <th>Cashier</th>
                            <th width="15%"><i class="fa fa-cog"></i></th>
                        </thead>
                        <tfoot>
                            <tr>
                                <th colspan="6">Total management sales</th>
                                <th id="totalBayar"></th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @includeIf('penjualan.detail')
    @includeIf('penjualan.form')
@endsection

@push('scripts')
    <script src="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>

    <script>
        let table, table1;
        let formattedTotalBayar;

        $(function () {
            // Shared filter modal defaults end_date to today (sales list). Management list should show all dates until the user applies a filter.
            $('#start_date').val('');
            $('#end_date').val('');

            table = $('.table-penjualan').DataTable({
                responsive: true,
                processing: true,
                serverSide: true,
                autoWidth: false,
                order: [[2, 'desc']],
                ajax: {
                    url: '{{ route('penjualan.management.data') }}',
                    data: function (d) {
                        d.receipt_number = ($('#filter_receipt_number_live').val() || $('#filter_receipt_number').val() || '').trim();
                        d.item_code = ($('#filter_item_code_live').val() || $('#filter_item_code').val() || '').trim();
                        if (d.receipt_number === '' && d.item_code === '') {
                            d.start_date = ($('#start_date').val() || '').trim();
                            d.end_date = ($('#end_date').val() || '').trim();
                        }
                    },
                },
                columns: [
                    { data: 'select_row', searchable: false, sortable: false },
                    { data: 'DT_RowIndex', searchable: false, sortable: false },
                    { data: 'saledate' },
                    { data: 'receiptno' },
                    { data: 'total_item' },
                    { data: 'total_harga' },
                    { data: 'diskon' },
                    { data: 'bayar' },
                    { data: 'payment_method', searchable: false },
                    { data: 'product_names', defaultContent: '-' },
                    { data: 'shop_names', defaultContent: '-' },
                    { data: 'kasir', searchable: false },
                    { data: 'aksi', searchable: false, sortable: false },
                ],
                footerCallback: function (row, data, start, end, display) {
                    var api = this.api(), data;
                    // Convert the bayar column data to floats for summation
                    var intVal = function (i) {
                        return typeof i === 'string' ?
                            i.replace(/[^\d.-]/g, '') * 1 :
                            typeof i === 'number' ?
                            i : 0;
                    };
                    // Total over all pages
                    totalBayar = api
                        .column(7) // Index of the bayar column
                        .data()
                        .reduce(function (a, b) {
                            // Handle non-numeric values
                            var numA = intVal(a);
                            var numB = intVal(b);
                            if (!isNaN(numA) && !isNaN(numB)) {
                                return numA + numB;
                            } else if (!isNaN(numA)) {
                                return numA;
                            } else if (!isNaN(numB)) {
                                return numB;
                            } else {
                                return 0;
                            }
                        }, 0);

                    // Update the total bayar cell in the footer
                    // Assuming totalBayar is a number
                    formattedTotalBayar = totalBayar.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                    $('#totalBayar').html('Ksh ' + formattedTotalBayar);
                }
            });

            table1 = $('.table-detail').DataTable({
                processing: true,
                bSort: false,
                dom: 'Brt',
                columns: [
                    { data: 'DT_RowIndex', searchable: false, sortable: false },
                    { data: 'kode_produk' },
                    { data: 'shop_code', defaultContent: 'N/A' },
                    { data: 'nama_produk' },
                    { data: 'harga_jual' },
                    { data: 'jumlah' },
                    { data: 'subtotal' },
                ]
            });

            $('.datepicker').datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true
            });

            var receiptLiveSearchTimer = null;
            var itemCodeLiveSearchTimer = null;
            function syncReceiptSearchFields(fromLive) {
                if (fromLive) {
                    $('#filter_receipt_number').val($('#filter_receipt_number_live').val());
                } else {
                    $('#filter_receipt_number_live').val($('#filter_receipt_number').val());
                }
            }
            function syncItemCodeSearchFields(fromLive) {
                if (fromLive) {
                    $('#filter_item_code').val($('#filter_item_code_live').val());
                } else {
                    $('#filter_item_code_live').val($('#filter_item_code').val());
                }
            }
            if ($('#filter_receipt_number').val()) {
                syncReceiptSearchFields(false);
            }
            if ($('#filter_item_code').val()) {
                syncItemCodeSearchFields(false);
            }
            $('#filter_receipt_number_live').on('input', function () {
                syncReceiptSearchFields(true);
                clearTimeout(receiptLiveSearchTimer);
                receiptLiveSearchTimer = setTimeout(function () {
                    table.draw();
                }, 350);
            });
            $('#filter_item_code_live').on('input', function () {
                syncItemCodeSearchFields(true);
                clearTimeout(itemCodeLiveSearchTimer);
                itemCodeLiveSearchTimer = setTimeout(function () {
                    table.draw();
                }, 350);
            });
            $('#filter-form').on('submit', function (e) {
                e.preventDefault();
                syncReceiptSearchFields(false);
                syncItemCodeSearchFields(false);
                table.draw();
                $('#modal-form').modal('hide');
            });

            function buildManagementExportParams() {
                var params = [];
                var startDate = $('#start_date').val();
                var endDate = $('#end_date').val();
                var receiptNo = ($('#filter_receipt_number_live').val() || $('#filter_receipt_number').val() || '').trim();
                var itemCode = ($('#filter_item_code_live').val() || $('#filter_item_code').val() || '').trim();
                var selectedIds = getSelectedManagementSaleIds();

                if (startDate) {
                    params.push('start_date=' + encodeURIComponent(startDate));
                }
                if (endDate) {
                    params.push('end_date=' + encodeURIComponent(endDate));
                }
                if (receiptNo) {
                    params.push('receipt_number=' + encodeURIComponent(receiptNo));
                }
                if (itemCode) {
                    params.push('item_code=' + encodeURIComponent(itemCode));
                }
                if (selectedIds.length) {
                    params.push('selected_ids=' + encodeURIComponent(selectedIds.join(',')));
                }
                return params;
            }

            $('#printButton').on('click', function () {
                var url = '{{ route('penjualan.management.export-pdf') }}';
                var params = buildManagementExportParams();
                
                if (params.length) {
                    url += '?' + params.join('&');
                }
                
                // Open PDF in new window
                window.open(url, '_blank');
            });

            $('#printCostButton').on('click', function () {
                var url = '{{ route('penjualan.management.export-cost-pdf') }}';
                var params = buildManagementExportParams();

                if (params.length) {
                    url += '?' + params.join('&');
                }

                window.open(url, '_blank');
            });

            $('#checkAllManagementRows').on('change', function () {
                $('.management-sale-check').prop('checked', $(this).is(':checked'));
            });

            $('#yourDataTable').on('draw.dt', function () {
                $('#checkAllManagementRows').prop('checked', false);
            });

        });

        function getSelectedManagementSaleIds() {
            return $('.management-sale-check:checked').map(function () {
                return $(this).val();
            }).get();
        }

        function showDetail(url) {
            $('#modal-detail').modal('show');
            table1.ajax.url(url);
            table1.ajax.reload();
        }

        function deleteData(url) {
            if (!confirm('Delete this sale? Stock will be restored for each line.')) {
                return;
            }
            var token = $('[name=csrf-token]').attr('content');

            function postDelete(confirmed) {
                var data = {
                    '_token': token,
                    '_method': 'delete'
                };
                if (confirmed) {
                    data.confirmed = '1';
                }
                $.post(url, data)
                    .done(function () {
                        table.ajax.reload();
                    })
                    .fail(function (xhr) {
                        if (xhr.status === 409 && xhr.responseJSON && xhr.responseJSON.requires_confirmation) {
                            var j = xhr.responseJSON;
                            var parts = [];
                            if (j.consignment) {
                                parts.push('supplier consignment (pending payout)');
                            }
                            if (j.cash) {
                                parts.push('cash-generated supplier purchase');
                            }
                            var extra = parts.length
                                ? '\n\nLinked records that will be removed or reduced: ' + parts.join(' and ') + '.'
                                : '';
                            var msg = (j.message || 'Linked supplier records will be updated.') + extra + '\n\nContinue?';
                            if (confirm(msg)) {
                                postDelete(true);
                            }
                            return;
                        }
                        var msg = 'Unable to delete data';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        alert(msg);
                    });
            }

            postDelete(false);
        }

        function reprintReceipt(url) {
            // Open receipt in new window and trigger print
            let printWindow = window.open(url, 'Print Receipt', 'height=600,width=500');
            printWindow.onload = function() {
                setTimeout(function() {
                    printWindow.print();
                }, 500);
            };
        }

        function updatePeriode() {
            $('#modal-form').modal('show');
        }
    </script>
@endpush



