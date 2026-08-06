@extends('layouts.master')
@include('partials.receipt_confirmation_modal_styles')

@section('title')
    Receipt Confirmation
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Receipt Confirmation</li>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
@endpush

@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Receipt Confirmation</h3>
                </div>

                <div class="box-body">
                    <!-- Filters -->
                    <div class="row" style="margin-bottom: 20px;">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="filter_status">Status</label>
                                <select class="form-control" id="filter_status">
                                    <option value="">All Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="confirmed">Confirmed</option>
                                    <option value="defect">Defect</option>
                                    <option value="review">Review</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="filter_start_date">Start Date</label>
                                <input type="text" class="form-control datepicker" id="filter_start_date" placeholder="Start Date">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="filter_end_date">End Date</label>
                                <input type="text" class="form-control datepicker" id="filter_end_date" placeholder="End Date">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="filter_receipt_number">Receipt Number</label>
                                <input type="text" class="form-control" id="filter_receipt_number" placeholder="Receipt Number">
                            </div>
                        </div>
                    </div>
                    <div class="row" style="margin-bottom: 20px;">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="filter_shop_name">Shop Name</label>
                                <input type="text" class="form-control" id="filter_shop_name" placeholder="Shop Name">
                            </div>
                        </div>
                        <div class="col-md-3" style="margin-top: 25px;">
                            <button type="button" class="btn btn-primary" onclick="applyFilters()">
                                <i class="fa fa-filter"></i> Apply Filters
                            </button>
                            <button type="button" class="btn btn-default" onclick="clearFilters()">
                                <i class="fa fa-refresh"></i> Clear
                            </button>
                            <button type="button" class="btn btn-success" onclick="exportToPdf()">
                                <i class="fa fa-file-pdf-o"></i> Export PDF
                            </button>
                        </div>
                    </div>

                    <!-- DataTable -->
                    <div class="table-responsive">
                        <table id="receipt-table" class="table table-striped table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th width="5%">#</th>
                                    <th>Receipt Number</th>
                                    <th>Shop Name</th>
                                    <th width="8%">Items Sold</th>
                                    <th width="25%">Items Name</th>
                                    <th>Total Amount</th>
                                    <th>Date of Sale</th>
                                    <th>Current Status</th>
                                    <th width="20%"><i class="fa fa-cog"></i> Actions</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('partials.receipt_confirmation_modal')
@endsection

@push('scripts')
    <script src="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <script>
        let table;

        $(function () {
            // Initialize datepicker (dd/mm/yyyy)
            $('.datepicker').datepicker({
                format: 'dd/mm/yyyy',
                autoclose: true
            });

            // Initialize DataTable
            table = $('#receipt-table').DataTable({
                responsive: true,
                processing: true,
                serverSide: true,
                autoWidth: false,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                ajax: {
                    url: '{{ route('receipt-confirmation.data') }}',
                    data: function (d) {
                        d.confirmation_status = $('#filter_status').val();
                        d.start_date = toYmd($('#filter_start_date').val());
                        d.end_date = toYmd($('#filter_end_date').val());
                        d.receipt_number = $('#filter_receipt_number').val();
                        d.shop_name = $('#filter_shop_name').val();
                    }
                },
                columns: [
                    { data: 'DT_RowIndex', searchable: false, sortable: false },
                    { data: 'receipt_number' },
                    { data: 'shop_name' },
                    { data: 'items_sold' },
                    { data: 'items_name' },
                    { data: 'total_amount' },
                    { data: 'date_of_sale' },
                    { data: 'current_status' },
                    { data: 'aksi', searchable: false, sortable: false },
                ],
                order: [[0, 'desc']],
                deferRender: true,
                scrollY: false,
                paging: true
            });

            var params = new URLSearchParams(window.location.search);
            var confirmId = params.get('confirm');
            if (confirmId) {
                confirmReceipt(confirmId);
                params.delete('confirm');
                var qs = params.toString();
                var newUrl = window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
                window.history.replaceState({}, '', newUrl);
            }
        });

        function applyFilters() {
            table.ajax.reload();
        }

        function clearFilters() {
            $('#filter_status').val('');
            $('#filter_start_date').val('');
            $('#filter_end_date').val('');
            $('#filter_receipt_number').val('');
            $('#filter_shop_name').val('');
            table.ajax.reload();
        }

        function toYmd(ddmmyyyy) {
            if (!ddmmyyyy) return '';
            var parts = ddmmyyyy.split('/');
            if (parts.length === 3) {
                return parts[2] + '-' + parts[1] + '-' + parts[0];
            }
            return ddmmyyyy;
        }

        function exportToPdf() {
            // Get current filter values (convert dd/mm/yyyy to yyyy-mm-dd for API)
            var status = $('#filter_status').val() || '';
            var startDate = toYmd($('#filter_start_date').val()) || '';
            var endDate = toYmd($('#filter_end_date').val()) || '';
            var receiptNumber = $('#filter_receipt_number').val() || '';
            var shopName = $('#filter_shop_name').val() || '';

            // Build URL with query parameters
            var url = '{{ route('receipt-confirmation.export-pdf') }}?';
            var params = [];

            if (status === 'all') {
                params.push('confirmation_status=all');
            } else if (status) {
                params.push('confirmation_status=' + encodeURIComponent(status));
            }
            if (startDate) {
                params.push('start_date=' + encodeURIComponent(startDate));
            }
            if (endDate) {
                params.push('end_date=' + encodeURIComponent(endDate));
            }
            if (receiptNumber) {
                params.push('receipt_number=' + encodeURIComponent(receiptNumber));
            }
            if (shopName) {
                params.push('shop_name=' + encodeURIComponent(shopName));
            }

            url += params.join('&');

            // Open PDF in new window
            window.open(url, '_blank');
        }
    </script>
    @include('partials.receipt_confirmation_modal_scripts')
@endpush

