@extends('layouts.master')
@include('partials.receipt_confirmation_modal_styles')

@section('title')
    Sales List
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Sales List</li>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
    <style>
        #penjualan-table-wrap.is-loading {
            opacity: 0.45;
            pointer-events: none;
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="box">
                <div class="box-header with-border">
                    <button onclick="updatePeriode()" class="btn btn-primary btn-flat"><i class="fa fa-filter"></i> Filter (Date, Payment, Receipt No, Item Code, Status)</button>
                    <button class="btn btn-warning btn-flat" id="printButton">Print Invoice</button>
                    <a href="#" id="exportPdfBtn" class="btn btn-danger btn-flat"><i class="fa fa-file-pdf-o"></i> Export to PDF</a>
                    <a href="#" id="exportExcelBtn" class="btn btn-success btn-flat"><i class="fa fa-file-excel-o"></i> Export to Excel</a>
                    <a href="{{ route('transaksi.baru') }}" class="btn btn-primary btn-flat">New Transaction</a>
                    <a href="{{ route('penjualan.import') }}" class="btn btn-success btn-flat"><i class="fa fa-upload"></i> Import Sales</a>
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
                                           placeholder="Type receipt no. — searches all dates"
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
                                           placeholder="Type item code — searches all dates"
                                           autocomplete="off">
                                    <span class="input-group-addon"><i class="fa fa-search"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="receipt-edit-initiated-alert" class="alert alert-warning" style="display: none; margin: 0 15px 12px;"></div>

                <div id="penjualan-search-loading" style="display: none; margin: 0 15px 12px;">
                    <div class="box box-info" style="margin-bottom: 0;">
                        <div class="box-body text-center" style="padding: 20px;">
                            <i class="fa fa-refresh fa-spin fa-3x text-info"></i>
                            <p class="lead" style="margin-top: 15px; margin-bottom: 0; font-weight: bold;">Searching...</p>
                            <p class="text-muted" style="margin-top: 5px; margin-bottom: 0;">Loading sales list</p>
                        </div>
                    </div>
                </div>

                <div class="box-body table-responsive" id="penjualan-table-wrap" style="padding-top: 0;">
                    <table id="yourDataTable" class="table table-stiped table-bordered table-penjualan table-hover">
                        <thead>
                            <th width="3%"><input type="checkbox" id="select-all-checkbox" title="Select All"></th>
                            <th width="5%">#</th>
                            <th>Date</th>
                            <th>Shop Codes</th>
                            <th>Receipt No</th>
                            <th>Quantity</th>
                            <th>Total Price</th>
                            <th>Discount</th>
                            <th>Total Pay</th>
                            <th>Payment Mode</th>
                            <th>Currency</th>
                            <th>Receipt Status</th>
                            <th>Cashier</th>
                            <th width="15%"><i class="fa fa-cog"></i></th>
                        </thead>
                        <tfoot>
                            <tr>
                                <th></th>
                                <th colspan="7">Total sales</th>
                                <th id="totalBayar"></th>
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
    @include('partials.receipt_confirmation_modal')
@endsection

@push('scripts')
    <script src="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>

    <script>
        let table, table1;
        let formattedTotalBayar;
        let penjualanEditInitiatedHint = null;

        function escapeHtml(text) {
            return $('<div>').text(text || '').html();
        }

        function renderEditInitiatedHintHtml(hint) {
            if (!hint) {
                return '';
            }
            var receipt = escapeHtml(hint.receiptno);
            return '<div class="penjualan-edit-initiated-empty" style="padding: 8px 4px;">' +
                '<p style="margin: 0 0 12px;"><i class="fa fa-info-circle"></i> ' +
                'Receipt <strong>' + receipt + '</strong> is not listed here because it has been ' +
                '<strong>initiated for sale edit</strong> and is waiting to be completed in POS.</p>' +
                '<p style="margin: 0;">' +
                '<a href="' + hint.resume_url + '" class="btn btn-success btn-sm"><i class="fa fa-edit"></i> Continue edit in POS</a> ' +
                '<a href="' + hint.initiated_edits_url + '" class="btn btn-default btn-sm">View all initiated edits</a>' +
                '</p></div>';
        }

        function updateReceiptEditInitiatedBanner() {
            var $alert = $('#receipt-edit-initiated-alert');
            if (!penjualanEditInitiatedHint) {
                $alert.hide().empty();
                return;
            }
            $alert.html(renderEditInitiatedHintHtml(penjualanEditInitiatedHint)).show();
        }

        function penjualanLiveSearchActive() {
            var receiptNo = ($('#filter_receipt_number_live').val() || $('#filter_receipt_number').val() || '').trim();
            var itemCode = ($('#filter_item_code_live').val() || $('#filter_item_code').val() || '').trim();
            return receiptNo !== '' || itemCode !== '';
        }

        $(function () {
            // No default end date — date range only when user sets it in Filter (not during receipt/item search).
            $('#start_date').val($('#start_date').val() || '');
            $('#end_date').val($('#end_date').val() || '');

            table = $('.table-penjualan').DataTable({
                responsive: true,
                processing: true,
                serverSide: true,
                autoWidth: false,
                pageLength: 50,
                order: [[2, 'desc']],
                ajax: {
                    url: '{{ route('penjualan.data') }}',
                    data: function (d) {
                        d.receipt_number = ($('#filter_receipt_number_live').val() || $('#filter_receipt_number').val() || '').trim();
                        d.item_code = ($('#filter_item_code_live').val() || $('#filter_item_code').val() || '').trim();
                        if (!penjualanLiveSearchActive()) {
                            d.start_date = $('#start_date').val();
                            d.end_date = $('#end_date').val();
                        }
                        d.payment_mode = $('#filter_payment_mode').val();
                        d.receipt_status = $('#filter_receipt_status').val();
                    },
                    dataSrc: function (json) {
                        penjualanEditInitiatedHint = json.edit_initiated_hint || null;
                        return json.data;
                    },
                },
                drawCallback: function () {
                    var api = this.api();
                    updateReceiptEditInitiatedBanner();
                    if (penjualanEditInitiatedHint && api.rows({ page: 'current' }).count() === 0) {
                        var colCount = api.columns().count();
                        var html = renderEditInitiatedHintHtml(penjualanEditInitiatedHint);
                        $(api.table().body()).html(
                            '<tr class="odd"><td valign="top" colspan="' + colCount + '" class="dataTables_empty text-left">' + html + '</td></tr>'
                        );
                    }
                },
                columns: [
                    { data: 'checkbox', searchable: false, sortable: false, orderable: false },
                    { data: 'DT_RowIndex', searchable: false, sortable: false },
                    { data: 'saledate' },
                    { data: 'shop_codes', searchable: false },
                    { data: 'receiptno' },
                    { data: 'total_item' },
                    { data: 'total_harga' },
                    { data: 'diskon' },
                    { data: 'bayar' },
                    { data: 'payment_method' },
                    { data: 'currency_type' },
                    { data: 'receipt_status' },
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
                    // Column indices: 0=checkbox, 1=#, 2=Date, 3=Shop Codes, 4=Receipt No, 5=Quantity, 6=Total Price, 7=Discount, 8=Total Pay (bayar)
                    totalBayar = api
                        .column(8) // Index of the bayar column (Total Pay)
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
                    { data: 'shop_code' },
                    { data: 'nama_produk' },
                    { data: 'harga_jual' },
                    { data: 'jumlah' },
                    { data: 'subtotal' },
                ]
            });

            $('.datepicker').datepicker({
                format: 'dd/mm/yyyy',
                autoclose: true
            });

            var penjualanSearchInProgress = false;
            function showPenjualanSearchLoading() {
                penjualanSearchInProgress = true;
                $('#penjualan-search-loading').show();
                $('#penjualan-table-wrap').addClass('is-loading');
            }
            function hidePenjualanSearchLoading() {
                if (!penjualanSearchInProgress) {
                    return;
                }
                penjualanSearchInProgress = false;
                $('#penjualan-search-loading').hide();
                $('#penjualan-table-wrap').removeClass('is-loading');
            }
            table.on('preXhr.dt', function () {
                showPenjualanSearchLoading();
            });
            table.on('xhr.dt error.dt', function () {
                hidePenjualanSearchLoading();
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

      function buildExportParams() {
        var params = new URLSearchParams();
        var receiptNo = ($('#filter_receipt_number_live').val() || $('#filter_receipt_number').val() || '').trim();
        var itemCode = ($('#filter_item_code_live').val() || $('#filter_item_code').val() || '').trim();
        var openSearch = receiptNo !== '' || itemCode !== '';
        if (!openSearch) {
            var start = $('#start_date').val();
            var end = $('#end_date').val();
            if (start) params.set('start_date', start);
            if (end) params.set('end_date', end);
        }
        var payment = $('#filter_payment_mode').val();
        var receiptStatus = $('#filter_receipt_status').val();
        if (payment) params.set('payment_mode', payment);
        if (receiptNo) params.set('receipt_number', receiptNo);
        if (itemCode) params.set('item_code', itemCode);
        if (receiptStatus) params.set('receipt_status', receiptStatus);
        return params.toString() ? '?' + params.toString() : '';
      }
      $('#exportPdfBtn').on('click', function (e) {
        e.preventDefault();
        var url = '{{ route("penjualan.export-pdf") }}' + buildExportParams();
        window.open(url, '_blank');
      });
      $('#exportExcelBtn').on('click', function (e) {
        e.preventDefault();
        var url = '{{ route("penjualan.export-excel") }}' + buildExportParams();
        window.location.href = url;
      });

      $('#printButton').on('click', function () {
        var dataTable = $('#yourDataTable').DataTable();
        if (dataTable.data().any()) {
            var data = dataTable.rows().data().toArray();
            var filteredData = data.map(function (row) {
                return [
                    row.DT_RowIndex,
                    row.saledate,
                    row.receiptno,
                    row.total_item,
                    row.total_harga,
                    row.diskon,
                    row.bayar,
                ];
            });

            var tableHtml = '<table border="1" style="border-collapse: collapse;">';
            tableHtml += '<thead><tr><th>#</th><th>Date</th><th>Receipt No</th><th>Quantity</th><th>Total Price</th><th>Discount</th><th>Total Pay</th></tr></thead>';
            tableHtml += '<tbody>';

            filteredData.forEach(function (row) {
                tableHtml += '<tr>';
                row.forEach(function (cell) {
                    tableHtml += '<td>' + cell + '</td>';
                });
                tableHtml += '</tr>';
            });

            tableHtml += '</tbody>';
            // Append the footer section
            tableHtml += '<tfoot><tr><th colspan="5">Total sales</th><th colspan="2">Ksh ' + formattedTotalBayar + '</th></tr></tfoot>';
            tableHtml += '</table>';
            
          


            var printWindow = window.open('', '_blank');
            printWindow.document.write('<html><head><title>SALES LIST</title></head><body>');
            var imagePath = '{{ asset('images/utamaduni.png') }}';
           var startDate = $('#start_date').val();
            var endDate = $('#end_date').val();
            
            printWindow.document.write('<div style="display: inline-block;margin: 20px;">');
            printWindow.document.write('<img src="' + imagePath + '" style="width: 60px; height: 60px;" alt="Example Image">');
            printWindow.document.write('<h2 style="display: inline-block; margin-left: 10px;">UTAMADUNI CRAFT CENTRE</h2>');
            printWindow.document.write('</div>');
             printWindow.document.write('</br>');
            printWindow.document.write('<div style="display: inline-block;">');
            printWindow.document.write('<p>Start Date: ' + startDate + '</p> <p>End Date: ' + endDate + '</p>');
           // printWindow.document.write('<p>End Date: ' + endDate + '</p>');
            printWindow.document.write('</div>');
            //printWindow.document.write('');
            printWindow.document.write(tableHtml);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.print();
        } else {
            alert('No data available to print.');
        }
    });

        });

        function showDetail(url) {
            $('#modal-detail').modal('show');
            table1.ajax.url(url);
            table1.ajax.reload();
            
            // Extract sale ID from URL
            var saleId = url.split('/').pop();
            if (saleId) {
                // Fetch payment information
                $.get('{{ url("/penjualan") }}/' + saleId + '/info')
                    .done(function(response) {
                        if (response) {
                            var paymentMethod = response.payment_method || 'Cash';
                            $('#payment-method-display').text(paymentMethod);
                            
                            // Show split payment details if applicable
                            if (paymentMethod === 'Split' && response.split_details) {
                                var splitDetails = response.split_details;
                                var splitList = $('#split-payment-list');
                                splitList.empty();
                                
                                if (splitDetails.cash && parseFloat(splitDetails.cash) > 0) {
                                    splitList.append('<li>Cash: Ksh ' + parseFloat(splitDetails.cash).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</li>');
                                }
                                if (splitDetails.mpesa && parseFloat(splitDetails.mpesa) > 0) {
                                    splitList.append('<li>Mpesa: Ksh ' + parseFloat(splitDetails.mpesa).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</li>');
                                }
                                if (splitDetails.card && parseFloat(splitDetails.card) > 0) {
                                    splitList.append('<li>Card: Ksh ' + parseFloat(splitDetails.card).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</li>');
                                }
                                
                                $('#split-payment-details').show();
                            } else {
                                $('#split-payment-details').hide();
                            }
                        }
                    })
                    .fail(function() {
                        $('#payment-method-display').text('N/A');
                        $('#split-payment-details').hide();
                    });
            }
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

        // Checkbox selection functionality for receipts
        $(document).on('change', '#select-all-checkbox', function() {
            var isChecked = $(this).prop('checked');
            $('.row-checkbox').prop('checked', isChecked);
        });

        $(document).on('change', '.row-checkbox', function() {
            var totalCheckboxes = $('.row-checkbox').length;
            var checkedCheckboxes = $('.row-checkbox:checked').length;
            $('#select-all-checkbox').prop('checked', totalCheckboxes === checkedCheckboxes);
        });

        // Function to get selected receipt IDs
        function getSelectedReceipts() {
            var selected = [];
            $('.row-checkbox:checked').each(function() {
                selected.push($(this).val());
            });
            return selected;
        }
    </script>
    @include('partials.receipt_confirmation_modal_scripts')
@endpush
