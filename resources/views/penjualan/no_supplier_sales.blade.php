@extends('layouts.master')

@section('title')
    No Supplier Sales
@endsection

@section('breadcrumb')
    @parent
    <li class="active">No Supplier Sales</li>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/select2/dist/css/select2.min.css') }}">
@endpush

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-exclamation-triangle"></i> No Supplier Sales</h3>
            </div>
            <div class="box-body">
                <p class="text-muted" style="margin-bottom: 12px;">
                    Shows sold items where supplier allocation (consignment/cash generated) was not created at sale time, even if supplier was added later. Quick-added items are excluded.
                </p>
                <form id="noSupplierSalesFilterForm" class="form-inline">
                    <div class="form-group" style="margin-right: 10px;">
                        <label for="start_date" style="margin-right: 6px;">Start Date</label>
                        <input type="date" id="start_date" class="form-control" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <label for="end_date" style="margin-right: 6px;">End Date</label>
                        <input type="date" id="end_date" class="form-control" value="{{ date('Y-m-d') }}">
                    </div>
                    <button type="submit" class="btn btn-primary btn-flat">
                        <i class="fa fa-filter"></i> Filter
                    </button>
                    <button type="button" id="btnResetNoSupplierSalesFilter" class="btn btn-default btn-flat" style="margin-left: 6px;">
                        <i class="fa fa-refresh"></i> Reset
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-4">
        <div class="info-box bg-yellow">
            <span class="info-box-icon"><i class="fa fa-list"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Rows</span>
                <span class="info-box-number" id="ns-total-rows">0</span>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="info-box bg-aqua">
            <span class="info-box-icon"><i class="fa fa-cubes"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Qty</span>
                <span class="info-box-number" id="ns-total-qty">0</span>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="info-box bg-red">
            <span class="info-box-icon"><i class="fa fa-money"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Amount</span>
                <span class="info-box-number" id="ns-total-amount">KES 0.00</span>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-body table-responsive">
                <table id="no-supplier-sales-table" class="table table-striped table-bordered table-hover" width="100%">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Receipt No</th>
                            <th>Product Code</th>
                            <th>Product Name</th>
                            <th>Supplier</th>
                            <th>Shop</th>
                            <th>Cashier</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Buying Price</th>
                            <th class="text-right">Cost Price</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Subtotal</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('AdminLTE-2/bower_components/select2/dist/js/select2.min.js') }}"></script>
<script>
    $(function () {
        var suppliersSelectUrl = @json(route('penjualan.no-supplier-sales.suppliers-select'));
        var assignUrl = @json(route('penjualan.no-supplier-sales.assign'));
        var csrfToken = $('meta[name="csrf-token"]').attr('content');
        var assignBusyByDetail = {};

        function formatAmount(v) {
            var n = parseFloat(v || 0);
            if (isNaN(n)) n = 0;
            return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function initAssignSelect($sel) {
            if (!$sel.length || $sel.data('select2')) {
                return;
            }
            var detailId = parseInt($sel.data('detail-id'), 10);
            if (!detailId) {
                return;
            }
            $sel.select2({
                placeholder: 'Assign to supplier...',
                allowClear: false,
                width: '100%',
                dropdownParent: $('body'),
                ajax: {
                    url: suppliersSelectUrl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { q: params.term || '' };
                    },
                    processResults: function (data) {
                        return data;
                    }
                },
                minimumInputLength: 0
            });

            $sel.on('select2:select.noSupplierAssign', function (e) {
                var supplierId = parseInt(e.params.data.id, 10);
                if (!supplierId || !detailId) {
                    return;
                }
                if (assignBusyByDetail[detailId]) {
                    return;
                }
                assignBusyByDetail[detailId] = true;
                var $row = $sel.closest('tr');
                $row.find('.js-no-supplier-assign-current, .js-no-supplier-assign-different').prop('disabled', true);
                $sel.prop('disabled', true);
                $.ajax({
                    url: assignUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        _token: csrfToken,
                        detail_id: detailId,
                        supplier_id: supplierId,
                        use_current_supplier: 0
                    }
                }).done(function (resp) {
                    alert((resp && resp.message) ? resp.message : 'Sale line assigned successfully.');
                    table.ajax.reload(null, false);
                }).fail(function (xhr) {
                    var msg = 'Unable to assign this sale line.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    alert(msg);
                    $sel.val(null).trigger('change');
                }).always(function () {
                    assignBusyByDetail[detailId] = false;
                    $row.find('.js-no-supplier-assign-current, .js-no-supplier-assign-different').prop('disabled', false);
                    $sel.prop('disabled', false);
                });
            });
        }

        function assignToCurrentSupplier(detailId, supplierId) {
            if (!detailId) {
                return;
            }
            if (assignBusyByDetail[detailId]) {
                return;
            }
            assignBusyByDetail[detailId] = true;
            var $currentBtn = $('#no-supplier-sales-table .js-no-supplier-assign-current[data-detail-id="' + detailId + '"]');
            var $diffBtn = $('#no-supplier-sales-table .js-no-supplier-assign-different[data-detail-id="' + detailId + '"]');
            var originalCurrentHtml = $currentBtn.html();
            $currentBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Assigning...');
            $diffBtn.prop('disabled', true);
            $.ajax({
                url: assignUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    _token: csrfToken,
                    detail_id: detailId,
                    supplier_id: supplierId || '',
                    use_current_supplier: 1
                }
            }).done(function (resp) {
                alert((resp && resp.message) ? resp.message : 'Sale line assigned successfully.');
                table.ajax.reload(null, false);
            }).fail(function (xhr) {
                var msg = 'Unable to assign this sale line.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                alert(msg);
            }).always(function () {
                assignBusyByDetail[detailId] = false;
                $currentBtn.prop('disabled', false).html(originalCurrentHtml);
                $diffBtn.prop('disabled', false);
            });
        }

        var table = $('#no-supplier-sales-table').DataTable({
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: {
                url: '{{ route('penjualan.no-supplier-sales.data') }}',
                data: function (d) {
                    d.start_date = $('#start_date').val();
                    d.end_date = $('#end_date').val();
                },
                dataSrc: function (json) {
                    $('#ns-total-rows').text((json && json.total_rows) ? json.total_rows : 0);
                    $('#ns-total-qty').text((json && json.total_qty) ? Number(json.total_qty).toLocaleString() : '0');
                    $('#ns-total-amount').text('KES ' + formatAmount(json && json.total_amount ? json.total_amount : 0));
                    return (json && json.data) ? json.data : [];
                }
            },
            order: [[1, 'desc']],
            columns: [
                { data: 'DT_RowIndex', searchable: false, sortable: false },
                { data: 'saledate', name: 'penjualan.saledate' },
                { data: 'receiptno', name: 'penjualan.receiptno' },
                { data: 'product_code', name: 'produk.item_code' },
                { data: 'nama_produk', name: 'produk.nama_produk' },
                { data: 'supplier_name', name: 'supplier.nama' },
                { data: 'shop_name', name: 'shops.shop_name' },
                { data: 'cashier_name', name: 'users.name' },
                { data: 'jumlah', name: 'penjualan_detail.jumlah', className: 'text-right' },
                { data: 'harga_beli', name: 'produk.harga_beli', className: 'text-right',
                    render: function (d) { return 'KES ' + formatAmount(d); } },
                { data: null, name: 'cost_price', className: 'text-right', searchable: false,
                    render: function (_d, _t, row) {
                        var qty = parseFloat(row.jumlah || 0);
                        var buy = parseFloat(row.harga_beli || 0);
                        return 'KES ' + formatAmount(qty * buy);
                    } },
                { data: 'harga_jual', name: 'penjualan_detail.harga_jual', className: 'text-right',
                    render: function (d) { return 'KES ' + formatAmount(d); } },
                { data: 'subtotal', name: 'penjualan_detail.subtotal', className: 'text-right',
                    render: function (d) { return 'KES ' + formatAmount(d); } },
                { data: 'action', name: 'action', searchable: false, sortable: false }
            ],
            drawCallback: function () {
                $('#no-supplier-sales-table .js-no-supplier-sale-assign').each(function () {
                    initAssignSelect($(this));
                });
            }
        });

        $('#noSupplierSalesFilterForm').on('submit', function (e) {
            e.preventDefault();
            table.ajax.reload();
        });

        $('#btnResetNoSupplierSalesFilter').on('click', function () {
            var today = '{{ date('Y-m-d') }}';
            $('#start_date').val(today);
            $('#end_date').val(today);
            table.ajax.reload();
        });

        $('#no-supplier-sales-table').on('click', '.js-no-supplier-assign-current', function () {
            var detailId = parseInt($(this).data('detail-id'), 10);
            var supplierId = parseInt($(this).data('supplier-id'), 10) || 0;
            assignToCurrentSupplier(detailId, supplierId);
        });

        $('#no-supplier-sales-table').on('click', '.js-no-supplier-assign-different', function () {
            var detailId = parseInt($(this).data('detail-id'), 10);
            if (!detailId) {
                return;
            }
            if (assignBusyByDetail[detailId]) {
                return;
            }
            var $wrap = $('.js-no-supplier-assign-picker-wrap[data-detail-id="' + detailId + '"]');
            if (!$wrap.length) {
                return;
            }
            $wrap.toggle();
            var $sel = $wrap.find('.js-no-supplier-sale-assign');
            initAssignSelect($sel);
            if ($wrap.is(':visible')) {
                $sel.select2('open');
            }
        });
    });
</script>
@endpush
