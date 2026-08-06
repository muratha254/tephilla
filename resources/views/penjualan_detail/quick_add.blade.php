<div class="modal fade" id="modal-quick-produk" tabindex="-1" role="dialog" aria-labelledby="modal-quick-produk" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="form-quick-produk" class="form-horizontal">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title">Quick Add New Product</h4>
                </div>
                <div class="modal-body">
                    <div id="quickadd-error" class="alert alert-danger hide"></div>
                    <div id="quickadd-info" class="alert alert-info hide"></div>

                    <div class="form-group">
                        <label for="quick_item_code" class="col-lg-4 control-label">Item Code / Barcode</label>
                        <div class="col-lg-7">
                            <input type="text" class="form-control" id="quick_item_code" name="item_code" required>
                            <span class="help-block">Start with shop code (example: <code>A-ITEM001</code> or <code>SHOP1-ITEM001</code>).</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="quick_nama_produk" class="col-lg-4 control-label">Commodity (Product Name)</label>
                        <div class="col-lg-7">
                            <input type="text" class="form-control" id="quick_nama_produk" name="nama_produk" placeholder="Auto-populates if item exists">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="quick_shop_name" class="col-lg-4 control-label">Shop</label>
                        <div class="col-lg-7">
                            <input type="text" class="form-control" id="quick_shop_name" value="" placeholder="Auto-detected from item code" readonly>
                            <input type="hidden" id="quick_shop_id" name="shop_id" value="">
                        </div>
                    </div>

                    <input type="hidden" id="quick_harga_beli" name="harga_beli" value="0">
                    <div class="form-group">
                        <label class="col-lg-4 control-label">Purchase Price</label>
                        <div class="col-lg-7">
                            <p class="form-control-static text-muted" style="margin-bottom: 0;">0 — set later in Products (cashiers cannot enter this here).</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="quick_harga_jual" class="col-lg-4 control-label">Selling Price</label>
                        <div class="col-lg-7">
                            <input type="number" class="form-control" id="quick_harga_jual" name="harga_jual" min="0" step="0.01" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="quick_stok" class="col-lg-4 control-label">Initial Stock</label>
                        <div class="col-lg-7">
                            <input type="number" class="form-control" id="quick_stok" name="stok" min="1" step="1" value="1">
                            <span class="help-block">You can adjust stock and other details later in Products.</span>
                        </div>
                    </div>

                    <p class="text-muted" style="margin-top: 10px;">
                        This will create a minimal product so you can complete this sale.
                        Please remember to review and complete its details later in the Products menu.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default btn-flat" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-flat">
                        <i class="fa fa-save"></i> Save &amp; Add to Sale
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@push('scripts')
<script>
$(function() {
    const rawQuickShops = @json($shops ?? []);
    const quickShops = (rawQuickShops || []).map(function (s) {
        return {
            id: s.id,
            shop_code: String((s.shop_code || '')).trim().toUpperCase(),
            shop_name: String((s.shop_name || ''))
        };
    });

    function parseShopCodeFromItemCode(itemCode) {
        const raw = (itemCode || '').trim();
        if (!raw) return '';
        if (raw.includes('-')) {
            return raw.split('-')[0].trim().toUpperCase();
        }
        // Legacy short format where first character is shop code
        return raw.substring(0, 1).toUpperCase();
    }

    function syncQuickAddShopFromItemCode() {
        const code = $('#quick_item_code').val();
        const shopCode = parseShopCodeFromItemCode(code);
        const match = quickShops.find(s => s.shop_code === shopCode);

        if (match) {
            $('#quick_shop_id').val(match.id);
            $('#quick_shop_name').val((match.shop_name || '-') + ' (' + (match.shop_code || '-') + ')');
        } else {
            $('#quick_shop_id').val('');
            if (shopCode) {
                $('#quick_shop_name').val('Unknown shop code: ' + shopCode);
            } else {
                $('#quick_shop_name').val('');
            }
        }
    }

    /** Clear commodity / prices / stock when the code (or shop prefix) no longer matches the last lookup. */
    function resetQuickAddProductFieldsForNewCode(code) {
        const c = (code || '').trim();
        $('#quick_nama_produk').val(c);
        $('#quick_harga_beli').val('0');
        $('#quick_harga_jual').val('');
        $('#quick_stok').val(1);
    }

    let quickLookupTimer = null;
    let quickLookupXhr = null;
    let previousQuickAddShopPrefix = null;

    function runQuickLookup() {
        const itemCode = ($('#quick_item_code').val() || '').trim();
        const requestCode = itemCode;
        const infoBox = $('#quickadd-info');
        const errorBox = $('#quickadd-error');
        errorBox.addClass('hide').text('');
        infoBox.addClass('hide').text('');

        if (!itemCode) {
            return;
        }

        if (quickLookupXhr && quickLookupXhr.readyState !== 4) {
            quickLookupXhr.abort();
        }

        quickLookupXhr = $.ajax({
            url: '{{ route("produk.quick_lookup") }}',
            method: 'GET',
            dataType: 'json',
            data: { item_code: itemCode }
        }).done(function(resp) {
            const current = ($('#quick_item_code').val() || '').trim();
            if (current !== requestCode) {
                return;
            }

            if (resp && resp.shop_id) {
                $('#quick_shop_id').val(resp.shop_id);
                $('#quick_shop_name').val((resp.shop_name || '-') + ' (' + (resp.shop_code || '-') + ')');
            }

            if (resp && resp.found) {
                $('#quick_nama_produk').val(resp.nama_produk || '');
                $('#quick_harga_beli').val('0');
                $('#quick_harga_jual').val(resp.harga_jual || 0);
                $('#quick_stok').val(typeof resp.stok !== 'undefined' ? resp.stok : 1);
                infoBox.removeClass('hide').text('Existing item found. Commodity, selling price and stock auto-populated.');
            } else {
                resetQuickAddProductFieldsForNewCode(itemCode);
                if (resp && resp.message) {
                    infoBox.removeClass('hide').text(resp.message);
                }
            }
        }).fail(function(xhr) {
            if (xhr && xhr.status === 0) return; // aborted
            const current = ($('#quick_item_code').val() || '').trim();
            if (current !== requestCode) {
                return;
            }
            let msg = 'Could not auto-lookup item code.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
            errorBox.removeClass('hide').text(msg);
        });
    }

    $(document).on('input', '#quick_item_code', function() {
        const code = ($('#quick_item_code').val() || '').trim();
        const prefix = parseShopCodeFromItemCode(code);
        if (previousQuickAddShopPrefix !== null && prefix !== previousQuickAddShopPrefix) {
            if (quickLookupXhr && quickLookupXhr.readyState !== 4) {
                quickLookupXhr.abort();
            }
            resetQuickAddProductFieldsForNewCode(code);
        }
        previousQuickAddShopPrefix = prefix || null;

        syncQuickAddShopFromItemCode();
        if (quickLookupTimer) clearTimeout(quickLookupTimer);
        quickLookupTimer = setTimeout(runQuickLookup, 220);
    });
    $(document).on('shown.bs.modal', '#modal-quick-produk', function() {
        const code = ($('#quick_item_code').val() || '').trim();
        previousQuickAddShopPrefix = parseShopCodeFromItemCode(code) || null;
        syncQuickAddShopFromItemCode();
        $('#quickadd-info').addClass('hide').text('');
    });
});
</script>
@endpush



