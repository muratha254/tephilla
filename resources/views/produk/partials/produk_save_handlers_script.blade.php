<script>
(function () {
    if (window.__produkSaveHandlersInit) {
        return;
    }
    window.__produkSaveHandlersInit = true;

    var __priceRetroPending = null;
    var __supplierSalesPending = null;

    function escapeHtml(text) {
        return $('<div>').text(text || '').html();
    }

    window.formatPriceRetroAppliedSummary = function (c) {
        if (!c || typeof c !== 'object') {
            return '';
        }
        var num = function (x) {
            var v = parseInt(x, 10);
            return isNaN(v) ? 0 : v;
        };
        var lines = [];
        if (num(c.sales_lines) > 0) {
            lines.push('POS sale lines updated: ' + num(c.sales_lines));
        }
        if (num(c.penjualan_recalculated) > 0) {
            lines.push('Sale receipts recalculated: ' + num(c.penjualan_recalculated));
        }
        if (num(c.consignment_items) > 0) {
            lines.push('Consignment invoice lines updated: ' + num(c.consignment_items));
        }
        if (num(c.cash_details) > 0) {
            lines.push('Cash purchase lines updated: ' + num(c.cash_details));
        }
        if (num(c.cash_masters) > 0) {
            lines.push('Cash purchase totals refreshed: ' + num(c.cash_masters));
        }
        if (lines.length === 0) {
            return 'No matching past rows were updated in the selected range.';
        }
        return lines.join('\n');
    }

    function formatSupplierSalesReassignedSummary(counts) {
        if (!counts) {
            return '';
        }
        return 'Past sales reassigned: ' + (counts.sale_lines_processed || 0) + ' sale line(s); ' +
            'consignment removed ' + (counts.consignment_removed || 0) + ', created ' + (counts.consignment_created || 0) + '; ' +
            'cash removed ' + (counts.cash_removed || 0) + ', created ' + (counts.cash_created || 0) +
            ((counts.skipped_paid || 0) > 0 ? '; skipped ' + counts.skipped_paid + ' paid line(s)' : '');
    }

    window.produkParseXhrJson = function (xhr) {
        if (xhr.responseJSON && typeof xhr.responseJSON === 'object') {
            return xhr.responseJSON;
        }
        var raw = xhr.responseText || '';
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch (e1) {
            var start = raw.indexOf('{');
            if (start === -1) {
                return null;
            }
            var depth = 0;
            for (var i = start; i < raw.length; i++) {
                var c = raw.charAt(i);
                if (c === '{') {
                    depth++;
                } else if (c === '}') {
                    depth--;
                    if (depth === 0) {
                        try {
                            return JSON.parse(raw.substring(start, i + 1));
                        } catch (e2) {
                            return null;
                        }
                    }
                }
            }
            return null;
        }
    };

    window.handleProdukSaveResponse = function (response, pending, onSuccess) {
        if (response && response.requires_supplier_sales_choice) {
            window.openSupplierSalesModal(response, pending);
            return true;
        }
        if (response && response.requires_price_retro_choice) {
            window.openPriceRetroModal(response, pending);
            return true;
        }
        if (typeof onSuccess === 'function') {
            onSuccess(response);
        }
        return false;
    };

    window.openSupplierSalesModal = function (serverResp, pending) {
        __supplierSalesPending = pending;
        var p = serverResp.preview || {};
        $('#supplier-sales-summary').html(
            'Change supplier from <strong>' + escapeHtml(p.old_supplier_name || 'None') + '</strong> to ' +
            '<strong>' + escapeHtml(p.new_supplier_name || '') + '</strong> (' + escapeHtml(p.new_supplier_mop || '') + ')?'
        );
        var lines = [];
        lines.push('<strong>' + (p.sale_lines || 0) + '</strong> completed sale line(s) for this product.');
        if ((p.consignment_unpaid_lines || 0) > 0) {
            lines.push('<strong>' + p.consignment_unpaid_lines + '</strong> unpaid consignment row(s) can be moved.');
        }
        if ((p.cash_unpaid_lines || 0) > 0) {
            lines.push('<strong>' + p.cash_unpaid_lines + '</strong> unpaid cash-generated row(s) can be moved.');
        }
        $('#supplier-sales-detail').html(lines.join('<br>'));
        $('#modal-supplier-sales').modal('show');
    };

    function clearSupplierSalesPending(revertInline) {
        var p = __supplierSalesPending;
        __supplierSalesPending = null;
        if (revertInline && p && p.$inlineInput && p.$inlineInput.length) {
            p.$inlineInput.val(String(p.$inlineInput.attr('data-original')));
        }
    }

    function priceRetroDefaultDates() {
        var now = new Date();
        var pad = function (n) { return n < 10 ? '0' + n : String(n); };
        var fd = new Date(now.getFullYear(), now.getMonth(), 1);
        var fromStr = fd.getFullYear() + '-' + pad(fd.getMonth() + 1) + '-' + pad(fd.getDate());
        var toStr = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
        $('#price_retro_date_from').val(fromStr);
        $('#price_retro_date_to').val(toStr);
    }

    window.openPriceRetroModal = function (serverResp, pending) {
        __priceRetroPending = pending;
        var parts = [];
        if (serverResp.beli_changed) {
            parts.push('Buying price: ' + serverResp.old_harga_beli + ' → <strong>' + serverResp.new_harga_beli + '</strong>');
        }
        if (serverResp.jual_changed) {
            parts.push('Selling price: ' + serverResp.old_harga_jual + ' → <strong>' + serverResp.new_harga_jual + '</strong>');
        }
        $('#price-retro-summary').html(parts.join('<br>'));
        $('#price_retro_chk_sales').prop('checked', !!serverResp.jual_changed).prop('disabled', !serverResp.jual_changed);
        $('#price_retro_chk_consignment').prop('checked', !!serverResp.beli_changed).prop('disabled', !serverResp.beli_changed);
        $('#price_retro_chk_cash').prop('checked', !!serverResp.beli_changed).prop('disabled', !serverResp.beli_changed);
        priceRetroDefaultDates();
        $('#modal-price-retro').modal('show');
    };

    function clearPriceRetroPending(revertInline) {
        var p = __priceRetroPending;
        __priceRetroPending = null;
        if (revertInline && p && p.$inlineInput && p.$inlineInput.length) {
            p.$inlineInput.val(String(p.$inlineInput.attr('data-original')));
        }
    }

    window.handleDuplicateMergeFromXhr = function (xhr, onMerged) {
        var json = window.produkParseXhrJson(xhr);
        if (!(xhr && xhr.status === 422 && json && json.merge)) {
            return false;
        }
        var m = json.merge;
        var intro = json.message || 'This item code exists on another product.';
        var detail = 'Merge will keep product #' + m.keep_id + ' (' + (m.keep_name || '') + ') and remove incomplete #' + m.remove_id
            + '. All past sales and remaining stock move to the kept product.';
        if (!confirm(intro + '\n\n' + detail + '\n\nProceed with merge?')) {
            return true;
        }
        $.ajax({
            url: '{{ route('produk.merge_incomplete_duplicate') }}',
            type: 'POST',
            dataType: 'json',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                keep_id: m.keep_id,
                remove_id: m.remove_id
            }
        }).done(function (resp) {
            if (typeof onMerged === 'function') {
                onMerged(resp);
                return;
            }
            if (typeof window.table !== 'undefined' && window.table && window.table.ajax) {
                window.table.ajax.reload(null, false);
            }
            if (typeof updateIncompleteCount === 'function') {
                updateIncompleteCount();
            }
            alert((resp && resp.message) ? resp.message : 'Merged successfully.');
        }).fail(function (x2) {
            var err = 'Merge failed.';
            if (x2.responseJSON && x2.responseJSON.message) {
                err = x2.responseJSON.message;
            }
            alert(err);
        });
        return true;
    };

    $(document).on('click', '#btn-supplier-sales-cancel, #btn-supplier-sales-close-x', function () {
        $('#modal-supplier-sales').modal('hide');
        clearSupplierSalesPending(true);
    });

    $(document).on('click', '#btn-supplier-sales-skip', function () {
        if (!__supplierSalesPending) {
            return;
        }
        var p = __supplierSalesPending;
        var data = $.extend({}, p.baseData, { supplier_sales_decision: 'skip' });
        $('#modal-supplier-sales').modal('hide');
        p.submitFn(data);
    });

    $(document).on('click', '#btn-supplier-sales-apply', function () {
        if (!__supplierSalesPending) {
            return;
        }
        var p = __supplierSalesPending;
        var data = $.extend({}, p.baseData, { supplier_sales_decision: 'apply' });
        $('#modal-supplier-sales').modal('hide');
        p.submitFn(data);
    });

    $(document).on('click', '#btn-price-retro-cancel, #btn-price-retro-close-x', function () {
        $('#modal-price-retro').modal('hide');
        clearPriceRetroPending(true);
    });

    $(document).on('click', '#btn-price-retro-future-only', function () {
        if (!__priceRetroPending) {
            return;
        }
        var p = __priceRetroPending;
        var data = $.extend({}, p.baseData, { price_retro_decision: 'skip' });
        $('#modal-price-retro').modal('hide');
        p.submitFn(data);
    });

    $(document).on('click', '#btn-price-retro-apply', function () {
        if (!__priceRetroPending) {
            return;
        }
        var from = $('#price_retro_date_from').val();
        var to = $('#price_retro_date_to').val();
        if (!from || !to) {
            alert('Please choose both start and end dates.');
            return;
        }
        var sales = $('#price_retro_chk_sales').is(':checked');
        var cons = $('#price_retro_chk_consignment').is(':checked');
        var cash = $('#price_retro_chk_cash').is(':checked');
        if (!sales && !cons && !cash) {
            alert('Select at least one: past sale lines, consignment, or cash purchases.');
            return;
        }
        var p = __priceRetroPending;
        var data = $.extend({}, p.baseData, {
            price_retro_decision: 'apply',
            retro_date_from: from,
            retro_date_to: to,
            retro_update_sales: sales ? 1 : 0,
            retro_update_consignment: cons ? 1 : 0,
            retro_update_cash_pembelian: cash ? 1 : 0
        });
        $('#modal-price-retro').modal('hide');
        p.submitFn(data);
    });
})();
</script>
