@php
    $missingBuyingPriceCount = (int) ($missingBuyingPriceCount ?? 0);
    $missingBuyContext = $missingBuyContext ?? 'consignment';
    $consignmentSuppliers = $consignmentSuppliers ?? collect();
    $cashSuppliersForFix = $cashSuppliersForFix ?? collect();
    $mbConsignmentSuppliersJson = $consignmentSuppliers->map(fn ($s) => ['id' => (int) $s->id_supplier, 'nama' => $s->nama])->values();
    $mbCashSuppliersJson = $cashSuppliersForFix->map(fn ($s) => ['id' => (int) $s->id_supplier, 'nama' => $s->nama])->values();
@endphp

<div class="alert alert-{{ $missingBuyingPriceCount > 0 ? 'warning' : 'info' }} missing-buying-price-banner" style="margin-bottom: 15px;">
    <strong><i class="fa fa-exclamation-triangle"></i> Sale lines missing buying price ({{ $missingBuyContext === 'cash' ? 'Cash Generated Sales' : 'Consignment' }})</strong>
    <span id="missing-buying-price-count-badge" class="label label-{{ $missingBuyingPriceCount > 0 ? 'warning' : 'default' }}" style="margin-left:6px;">{{ $missingBuyingPriceCount }}</span>
    <span class="hidden-xs text-muted" style="margin-left:8px;">Until cost is set, these lines stay out of supplier / cash-purchase totals.</span>
    <button type="button" class="btn btn-sm btn-{{ $missingBuyingPriceCount > 0 ? 'warning' : 'default' }} pull-right" data-toggle="modal" data-target="#modalMissingBuyingPrice">
        <i class="fa fa-list"></i> Open list
    </button>
    <div class="clearfix"></div>
</div>

<div class="modal fade" id="modalMissingBuyingPrice" tabindex="-1" role="dialog" aria-labelledby="modalMissingBuyingPriceLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="modalMissingBuyingPriceLabel">
                    Fix missing buying price — {{ $missingBuyContext === 'cash' ? 'Cash Generated Sales' : 'Consignment' }}
                </h4>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table id="missingBuyingPriceTable" class="table table-bordered table-striped table-condensed" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Receipt</th>
                                <th>Sale date</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Sale price</th>
                                <th>Buying price</th>
                                <th>Supplier / owner</th>
                                <th style="width:120px;">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>

                <div id="missing-buying-price-fix-panel" class="well hide" style="margin-top: 15px;">
                    <h4 style="margin-top:0;">Edit / assign</h4>
                    <p class="text-muted" id="mbpf_summary"></p>
                    <input type="hidden" id="mbpf_detail_id" value="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Record as</label><br>
                                <label class="radio-inline">
                                    <input type="radio" name="mbpf_accounting" value="consignment"> Consignment
                                </label>
                                <label class="radio-inline">
                                    <input type="radio" name="mbpf_accounting" value="cash"> Cash Generated Sale
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="mbpf_supplier">Supplier / owner</label>
                                <select id="mbpf_supplier" class="form-control"></select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="mbpf_harga_beli">Buying price (per unit)</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" id="mbpf_harga_beli" placeholder="Enter cost price">
                    </div>
                    <button type="button" class="btn btn-primary" id="mbpf_save"><i class="fa fa-save"></i> Save &amp; update ledgers</button>
                    <button type="button" class="btn btn-default" id="mbpf_cancel">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var mbContext = @json($missingBuyContext);
    var mbConsignmentSuppliers = @json($mbConsignmentSuppliersJson);
    var mbCashSuppliers = @json($mbCashSuppliersJson);
    var mbDataUrl = @json(route('payment.missing-buying-price.data'));
    var mbCountUrl = @json(route('payment.missing-buying-price.count'));
    var mbFixUrl = @json(route('payment.missing-buying-price.fix'));
    var mbTable = null;

    function mbRefreshBadge() {
        $.getJSON(mbCountUrl, { context: mbContext })
            .done(function (r) {
                var n = parseInt(r.count, 10) || 0;
                $('#missing-buying-price-count-badge').text(n);
            });
    }

    function mbFillSupplierSelect(accounting) {
        var $sel = $('#mbpf_supplier').empty();
        var list = accounting === 'cash' ? mbCashSuppliers : mbConsignmentSuppliers;
        $sel.append($('<option></option>').val('').text('— Select —'));
        $.each(list, function (_, s) {
            $sel.append($('<option></option>').attr('value', s.id).text(s.nama));
        });
    }

    $('#modalMissingBuyingPrice').on('shown.bs.modal', function () {
        if (!mbTable) {
            mbTable = $('#missingBuyingPriceTable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                ordering: false,
                ajax: {
                    url: mbDataUrl,
                    data: function (d) {
                        d.context = mbContext;
                    }
                },
                columns: [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                    { data: 'receiptno', name: 'receiptno', orderable: false },
                    { data: 'sale_date', name: 'sale_date', orderable: false },
                    { data: 'product_name', name: 'product_name', orderable: false },
                    { data: 'qty', name: 'qty', orderable: false },
                    { data: 'sale_price', name: 'sale_price', orderable: false },
                    { data: 'buying_price_status', name: 'buying_price_status', orderable: false, searchable: false },
                    { data: 'owner', name: 'owner', orderable: false, searchable: false },
                    { data: 'aksi', name: 'aksi', orderable: false, searchable: false }
                ],
                pageLength: 25
            });
        } else {
            mbTable.ajax.reload(null, false);
        }
    });

    $(document).on('click', '.btn-missing-buying-fix', function () {
        var $b = $(this);
        $('#mbpf_detail_id').val($b.data('id'));
        var $sum = $('#mbpf_summary').empty();
        $sum.append($('<strong>').text($b.data('product') || ''));
        $sum.append(document.createTextNode(' · Qty ' + $b.data('qty') + ' · Sale price Ksh ' + Number($b.data('sale-price') || 0).toLocaleString()));
        var presetAcc = mbContext === 'cash' ? 'cash' : 'consignment';
        $('input[name="mbpf_accounting"][value="' + presetAcc + '"]').prop('checked', true);
        mbFillSupplierSelect(presetAcc);
        var sid = parseInt($b.data('supplier-id'), 10) || 0;
        if (sid) {
            $('#mbpf_supplier').val(String(sid));
        }
        $('#mbpf_harga_beli').val('');
        $('#missing-buying-price-fix-panel').removeClass('hide');
        $('html, body').animate({
            scrollTop: $('#missing-buying-price-fix-panel').offset().top - 100
        }, 200);
    });

    $('input[name="mbpf_accounting"]').on('change', function () {
        mbFillSupplierSelect($(this).val());
    });

    $('#mbpf_cancel').on('click', function () {
        $('#missing-buying-price-fix-panel').addClass('hide');
    });

    $('#mbpf_save').on('click', function () {
        var id = $('#mbpf_detail_id').val();
        var harga = $('#mbpf_harga_beli').val();
        var sup = $('#mbpf_supplier').val();
        var acc = $('input[name="mbpf_accounting"]:checked').val();
        if (!id || !harga || !sup || !acc) {
            alert('Please fill buying price, supplier, and accounting type.');
            return;
        }
        var $btn = $(this).prop('disabled', true);
        $.ajax({
            url: mbFixUrl,
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                id_penjualan_detail: id,
                harga_beli: harga,
                id_supplier: sup,
                accounting_type: acc
            }
        }).done(function (res) {
            alert(res.message || 'Saved.');
            $('#missing-buying-price-fix-panel').addClass('hide');
            if (mbTable) {
                mbTable.ajax.reload(null, false);
            }
            mbRefreshBadge();
        }).fail(function (xhr) {
            var msg = 'Unable to save.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
            alert(msg);
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });
})();
</script>
@endpush
