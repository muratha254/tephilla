<script src="{{ asset('AdminLTE-2/bower_components/select2/dist/js/select2.full.min.js') }}"></script>
<script>
    function rcRefreshReceiptConfirmationBadge() {
        $.get('{{ route("receipt-confirmation.pending-badge-count") }}')
            .done(function (res) {
                var n = parseInt(res.count, 10) || 0;
                $('.receipt-confirmation-badge-wrap').each(function () {
                    $(this).toggle(n > 0);
                });
                $('.receipt-confirmation-badge-count').text(n);
            });
    }

    function rcReloadReceiptRelatedTables() {
        try {
            if (typeof table !== 'undefined' && table && table.ajax && typeof table.ajax.reload === 'function') {
                table.ajax.reload(null, false);
            }
        } catch (e) {}
        try {
            if (typeof $ !== 'undefined' && $.fn.DataTable && $.fn.DataTable.isDataTable('#receipt-table')) {
                $('#receipt-table').DataTable().ajax.reload(null, false);
            }
        } catch (e2) {}
        rcRefreshReceiptConfirmationBadge();
    }

    function rcEscAttr(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;');
    }

    function rcEscHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function rcDestroyLineProductSelect($row) {
        var $sel = $row.find('.item-product-code-select');
        if ($sel.length && $sel.hasClass('select2-hidden-accessible')) {
            try {
                $sel.select2('destroy');
            } catch (err) {}
        }
    }

    function rcFormatProductSearchOption(state) {
        if (!state.id || state.loading) {
            return state.text;
        }
        var buy = state.harga_beli != null ? Number(state.harga_beli).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) : '0';
        var sell = state.harga_jual != null ? Number(state.harga_jual).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) : '0';
        var code = rcEscHtml(state.item_code || '');
        var title = rcEscHtml(state.nama_produk || '');
        var shop = rcEscHtml(state.shop_name || '');
        return $('<div class="rc-prod-opt"><div class="rc-prod-opt-line1"><strong>' + code + '</strong> — ' + title + '</div><div class="rc-prod-opt-line2 text-muted">' + shop + ' · Buy Ksh ' + buy + ' · Sell Ksh ' + sell + '</div></div>');
    }

    function rcApplyPickedProductToRow(row, d) {
        if (!d || !row || !row.length) {
            return;
        }
        var code = (d.item_code || '').trim();
        row.data('rc-picked-code', code);
        if (d.shop_id) {
            row.find('.item-shop').val(String(d.shop_id));
        }
        var $name = row.find('.item-product-name');
        if ($name.length && d.nama_produk) {
            $name.val(d.nama_produk);
        }
        if (d.harga_jual != null && d.harga_jual !== '') {
            row.find('.item-price').val(d.harga_jual);
        }
        rcRecalcReceiptLineSubtotal(row);
    }

    function rcApplyQuickAddConfirmUi(waitingUpdate) {
        var blocked = !!waitingUpdate;
        $('#modal-receipt-confirmation').data('waiting-update', blocked);
        $('#btn-confirm-all-items').prop('disabled', blocked);
        $('#select-all-items').prop('disabled', blocked);
        if (blocked) {
            $('#waiting-update-alert').show();
        } else {
            $('#waiting-update-alert').hide();
        }
    }

    function rcQuickAddConfirmBlockedMessage() {
        return 'This receipt has item(s) still in Quick Add. Complete or remove them in Products → Quick Add before confirming.';
    }

    function rcIsQuickAddBlockedRow($row) {
        if (!$row || !$row.length) {
            return false;
        }
        return !!$('#modal-receipt-confirmation').data('waiting-update') && parseInt($row.data('is-quick-add'), 10) === 1;
    }

    function rcEditButtonHtml(receiptId, itemId, isQuickAdd, waitingUpdate) {
        if (isQuickAdd && waitingUpdate) {
            return '<button type="button" class="btn btn-xs btn-default" disabled title="Complete or remove this item in Products → Quick Add first"><i class="fa fa-edit"></i> Edit</button>';
        }
        return '<button onclick="editItem(' + receiptId + ', ' + itemId + ')" class="btn btn-xs btn-warning"><i class="fa fa-edit"></i> Edit</button>';
    }

    function rcConfirmActionHtml(receiptId, itemId, itemStatus, isQuickAdd, waitingUpdate) {
        if (itemStatus === 'confirmed') {
            return '';
        }
        if (isQuickAdd && waitingUpdate) {
            return '<span class="text-muted small"><i class="fa fa-clock-o"></i> Quick Add</span>';
        }
        if (!waitingUpdate) {
            return '<button onclick="confirmItem(' + receiptId + ', ' + itemId + ')" class="btn btn-xs btn-success"><i class="fa fa-check"></i> Confirm</button>';
        }
        return '';
    }

    function confirmReceipt(receiptId) {
        $.get('{{ url("/penjualan") }}/' + receiptId + '/confirmation-data')
            .done(function(response) {
                if (response.success) {
                    $('#receipt-number-modal').text(response.receipt.receiptno);
                    $('#modal-receipt-no').text(response.receipt.receiptno);
                    $('#modal-sale-date').text(response.receipt.saledate || response.receipt.created_at);
                    $('#modal-total-items').text(response.receipt.total_item);
                    $('#modal-total-amount').text('Ksh ' + parseFloat(response.receipt.bayar || 0).toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0}));
                    $('#modal-cashier').text(response.receipt.cashier || 'N/A');
                    $('#modal-member').text(response.receipt.member || 'Walk-in Customer');

                    var status = response.receipt.confirmation_status || 'pending';
                    var statusHtml = '';
                    if (status === 'confirmed') {
                        statusHtml = '<span class="label label-success">Confirmed</span>';
                    } else if (status === 'defect') {
                        statusHtml = '<span class="label label-danger">Defect</span>';
                    } else if (status === 'review') {
                        statusHtml = '<span class="label label-info">Review</span>';
                    } else {
                        statusHtml = '<span class="label label-warning">Pending</span>';
                    }
                    $('#modal-receipt-status').html(statusHtml);

                    rcApplyQuickAddConfirmUi(response.waitingUpdate);

                    var itemsHtml = '';
                    response.items.forEach(function(item, index) {
                        var itemStatus = item.item_confirmation_status || 'pending';
                        var statusBadge = '';
                        if (itemStatus === 'confirmed') {
                            statusBadge = '<span class="label label-success">Confirmed</span>';
                        } else if (itemStatus === 'defect') {
                            statusBadge = '<span class="label label-danger">Defect</span>';
                        } else {
                            statusBadge = '<span class="label label-warning">Pending</span>';
                        }

                        var shopSelect = '<select class="form-control input-sm item-shop" data-id="' + item.id + '" style="width: 150px;">';
                        response.shops.forEach(function(shop) {
                            var selected = (item.shop_id == shop.id) ? 'selected' : '';
                            shopSelect += '<option value="' + shop.id + '" ' + selected + '>' + shop.shop_name + '</option>';
                        });
                        shopSelect += '</select>';

                        var confirmBtn = rcConfirmActionHtml(receiptId, item.id, itemStatus, item.is_quick_add, response.waitingUpdate);
                        var editBtn = rcEditButtonHtml(receiptId, item.id, item.is_quick_add, response.waitingUpdate);

                        var checkboxChecked = (itemStatus === 'confirmed') ? 'checked' : '';
                        var checkboxDisabled = (itemStatus === 'confirmed' || response.waitingUpdate) ? 'disabled' : '';

                        var productNameCell = (item.id_produk)
                            ? '<td id="item-name-cell-' + item.id + '"><span class="label label-success item-name-display">' +
                            '<input type="text" class="form-control input-sm item-product-name" data-id="' + item.id + '" value="' + rcEscAttr(item.product_name || '') + '" maxlength="255" title="Edit name; saves when you click outside">' +
                            '</span></td>'
                            : '<td id="item-name-cell-' + item.id + '"><span class="label label-success item-name-display">' + rcEscHtml(item.product_name || 'N/A') + '</span></td>';

                        var productCodeCell = '<td id="item-code-cell-' + item.id + '"><span class="item-code-display">' + rcEscHtml(item.product_code || 'N/A') + '</span></td>';

                        var rowClass = (item.is_quick_add && response.waitingUpdate) ? ' class="warning"' : '';
                        var rowQuickAddAttr = ' data-is-quick-add="' + (item.is_quick_add ? '1' : '0') + '"';
                        itemsHtml += '<tr id="item-row-' + item.id + '"' + rowClass + rowQuickAddAttr + '>' +
                            '<td><input type="checkbox" class="item-checkbox" data-item-id="' + item.id + '" ' + checkboxChecked + ' ' + checkboxDisabled + '></td>' +
                            '<td>' + (index + 1) + '</td>' +
                            productNameCell +
                            '<td id="item-shop-cell-' + item.id + '">' + shopSelect + '</td>' +
                            productCodeCell +
                            '<td id="item-price-cell-' + item.id + '"><input type="number" class="form-control input-sm item-price" data-id="' + item.id + '" value="' + (item.price || 0) + '" step="0.01" min="0" style="width: 100px;"></td>' +
                            '<td id="item-quantity-cell-' + item.id + '"><input type="number" class="form-control input-sm item-quantity" data-id="' + item.id + '" value="' + (item.quantity || 0) + '" min="1" style="width: 80px;"></td>' +
                            '<td id="item-discount-cell-' + item.id + '"><input type="number" class="form-control input-sm item-discount" data-id="' + item.id + '" value="' + (item.discount || 0) + '" min="0" max="100" style="width: 80px;"></td>' +
                            '<td id="item-subtotal-cell-' + item.id + '">Ksh ' + parseFloat(item.subtotal || 0).toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0}) + '</td>' +
                            '<td id="item-status-' + item.id + '">' + statusBadge + '</td>' +
                            '<td id="item-actions-' + item.id + '"><div class="btn-group">' + confirmBtn + editBtn + '</div></td>' +
                            '</tr>';
                    });
                    $('#modal-items-body').html(itemsHtml);
                    response.items.forEach(function (it) {
                        if (it.id_produk) {
                            var $pn = $('#item-row-' + it.id + ' .item-product-name');
                            if ($pn.length) {
                                $pn.data('original-name', (it.product_name || '').trim());
                            }
                        }
                        rcRecalcReceiptLineSubtotal($('#item-row-' + it.id));
                    });
                    $('#modal-receipt-confirmation').data('receipt-id', receiptId);
                    $('#modal-receipt-confirmation').modal('show');
                }
            })
            .fail(function() {
                alert('Failed to load receipt data.');
            });
    }

    function confirmItem(receiptId, itemId) {
        if ($('#modal-receipt-confirmation').data('waiting-update')) {
            alert(rcQuickAddConfirmBlockedMessage());
            return;
        }

        $.post('{{ url("/receipt-confirmation") }}/' + receiptId + '/items/' + itemId + '/confirm', {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            if (response.success || response.message || (response && !response.error)) {
                $('#item-status-' + itemId).html('<span class="label label-success">Confirmed</span>');
                $('#item-row-' + itemId + ' .item-checkbox').prop('checked', true).prop('disabled', true);
                var $row = $('#item-row-' + itemId);
                var actionsCell = $('#item-actions-' + itemId);
                actionsCell.html(
                    '<div class="btn-group">' +
                    rcEditButtonHtml(receiptId, itemId, parseInt($row.data('is-quick-add'), 10) === 1, $('#modal-receipt-confirmation').data('waiting-update')) +
                    '</div>'
                );

                rcReloadReceiptRelatedTables();
                if (response.all_confirmed) {
                    alert('All items confirmed! Receipt is now confirmed.');
                    $('#modal-receipt-confirmation').modal('hide');
                } else {
                    alert(response.success || 'Item confirmed successfully.');
                }
            } else {
                alert('Error: ' + (response.error || 'Unknown error'));
            }
        })
        .fail(function(xhr) {
            var errorMsg = 'Failed to confirm item.';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
            } else if (xhr.status === 404) {
                errorMsg = 'Route not found. Please check if the route is properly configured.';
            } else if (xhr.status === 403) {
                errorMsg = 'You do not have permission to perform this action.';
            }
            alert(errorMsg);
            console.error('Error details:', xhr);
        });
    }

    function markItemDefect(receiptId, itemId) {
        if (!confirm('Mark this item as defect?')) {
            return;
        }
        $.post('{{ url("/receipt-confirmation") }}/' + receiptId + '/items/' + itemId + '/mark-defect', {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            if (response.success || response.message) {
                $('#item-status-' + itemId).html('<span class="label label-danger">Defect</span>');
                var actionsCell = $('#item-actions-' + itemId);
                var waitingUpdate = $('#modal-receipt-confirmation').data('waiting-update');
                var $row = $('#item-row-' + itemId);
                var isQuickAdd = parseInt($row.data('is-quick-add'), 10) === 1;
                var confirmBtn = rcConfirmActionHtml(receiptId, itemId, 'pending', isQuickAdd, waitingUpdate);
                actionsCell.html(
                    '<div class="btn-group">' +
                    confirmBtn +
                    rcEditButtonHtml(receiptId, itemId, isQuickAdd, waitingUpdate) +
                    '</div>'
                );
                alert('Item marked as defect.');
                rcReloadReceiptRelatedTables();
            } else {
                alert('Error: ' + (response.error || 'Unknown error'));
            }
        })
        .fail(function(xhr) {
            var errorMsg = 'Failed to mark item as defect.';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
            }
            alert(errorMsg);
        });
    }

    function confirmAllItems() {
        var receiptId = $('#modal-receipt-confirmation').data('receipt-id');
        if (!receiptId) {
            return;
        }

        if ($('#modal-receipt-confirmation').data('waiting-update')) {
            alert(rcQuickAddConfirmBlockedMessage());
            return;
        }

        var itemIds = [];
        $('#modal-receipt-confirmation .item-checkbox:checked:not(:disabled)').each(function () {
            var id = parseInt($(this).data('item-id'), 10);
            if (id) {
                itemIds.push(id);
            }
        });

        if (itemIds.length === 0) {
            alert('Select at least one pending item (use the checkboxes), then click Confirm All Items.');
            return;
        }

        if (!confirm('Confirm ' + itemIds.length + ' selected item(s)?')) {
            return;
        }

        var $btn = $('#btn-confirm-all-items');
        $btn.prop('disabled', true);

        $.post('{{ url("/receipt-confirmation") }}/' + receiptId + '/confirm-all-items', {
            _token: '{{ csrf_token() }}',
            item_ids: itemIds
        })
        .done(function (response) {
            if (!response || !response.success) {
                alert((response && response.error) ? response.error : 'Failed to confirm items.');
                return;
            }

            var confirmedLabel = '<span class="label label-success">Confirmed</span>';
            (response.confirmed_ids || itemIds).forEach(function (itemId) {
                $('#item-status-' + itemId).html(confirmedLabel);
                var $row = $('#item-row-' + itemId);
                $row.find('.item-checkbox').prop('checked', true).prop('disabled', true);
                $('#item-actions-' + itemId).html(
                    '<div class="btn-group">' +
                    rcEditButtonHtml(receiptId, itemId, parseInt($row.data('is-quick-add'), 10) === 1, $('#modal-receipt-confirmation').data('waiting-update')) +
                    '</div>'
                );
            });

            var receiptStatus = response.receipt_status || '';
            if (receiptStatus === 'confirmed') {
                $('#modal-receipt-status').html('<span class="label label-success">Confirmed</span>');
            } else if (receiptStatus === 'defect') {
                $('#modal-receipt-status').html('<span class="label label-danger">Defect</span>');
            } else if (receiptStatus === 'review') {
                $('#modal-receipt-status').html('<span class="label label-info">Review</span>');
            }

            rcReloadReceiptRelatedTables();
            alert(response.message || 'Items confirmed.');

            if (response.all_confirmed) {
                $('#modal-receipt-confirmation').modal('hide');
            }
        })
        .fail(function (xhr) {
            var msg = 'Failed to confirm items.';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                msg = xhr.responseJSON.error;
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
            alert(msg);
        })
        .always(function () {
            rcApplyQuickAddConfirmUi($('#modal-receipt-confirmation').data('waiting-update'));
        });
    }

    var rcLineLookupTimer = {};
    var rcLineLookupSeq = {};

    function rcRecalcReceiptLineSubtotal($row) {
        if (!$row || !$row.length) {
            return;
        }
        var rid = $row.attr('id');
        if (!rid || rid.indexOf('item-row-') !== 0) {
            return;
        }
        var itemId = rid.replace('item-row-', '');
        var price = parseFloat($row.find('.item-price').val()) || 0;
        var quantity = parseInt($row.find('.item-quantity').val(), 10) || 0;
        var discount = parseFloat($row.find('.item-discount').val()) || 0;
        var subtotal = price * quantity;
        if (discount > 0) {
            subtotal = subtotal - (subtotal * discount / 100);
        }
        subtotal = Math.round(subtotal);
        var $cell = $('#item-subtotal-cell-' + itemId);
        if ($cell.length) {
            $cell.text('Ksh ' + subtotal.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0}));
        }
    }

    function rcScheduleReceiptLineLookup(itemId) {
        if (rcLineLookupTimer[itemId]) {
            clearTimeout(rcLineLookupTimer[itemId]);
        }
        rcLineLookupTimer[itemId] = setTimeout(function () {
            rcRunReceiptLineLookup(itemId);
        }, 280);
    }

    function rcRunReceiptLineLookup(itemId) {
        var row = $('#item-row-' + itemId);
        var $inp = row.find('.item-product-code');
        if (!$inp.length) {
            return;
        }
        var code = ($inp.val() || '').trim();
        var receiptId = $('#modal-receipt-confirmation').data('receipt-id');
        if (!code || !receiptId) {
            return;
        }
        var shopId = parseInt(row.find('.item-shop').val(), 10) || 0;
        rcLineLookupSeq[itemId] = (rcLineLookupSeq[itemId] || 0) + 1;
        var seq = rcLineLookupSeq[itemId];
        $inp.removeClass('has-error');
        $.get('{{ route('produk.quick_lookup') }}', {
            item_code: code,
            shop_id: shopId,
            strict_shop: 1
        })
            .done(function (resp) {
                if (seq !== rcLineLookupSeq[itemId]) {
                    return;
                }
                if (!$('#item-row-' + itemId).find('.item-product-code').length) {
                    return;
                }
                row = $('#item-row-' + itemId);
                $inp = row.find('.item-product-code');
                if (resp.found) {
                    if (resp.shop_id) {
                        row.find('.item-shop').val(String(resp.shop_id));
                    }
                    var $pn = row.find('.item-product-name');
                    if ($pn.length) {
                        $pn.val(resp.nama_produk || '');
                    }
                    row.find('.item-price').val(resp.harga_jual != null ? resp.harga_jual : '');
                    $inp.attr('title', '');
                } else {
                    var $pn2 = row.find('.item-product-name');
                    if ($pn2.length) {
                        $pn2.val('');
                    }
                    row.find('.item-price').val('');
                    var hint = (resp && resp.message) ? resp.message : 'No matching product for this code and shop.';
                    $inp.attr('title', hint);
                    if (resp && resp.no_match_in_selected_shop) {
                        $inp.addClass('has-error');
                    }
                }
                rcRecalcReceiptLineSubtotal(row);
            })
            .fail(function (xhr) {
                if (seq !== rcLineLookupSeq[itemId]) {
                    return;
                }
                if (!$('#item-row-' + itemId).find('.item-product-code').length) {
                    return;
                }
                row = $('#item-row-' + itemId);
                $inp = row.find('.item-product-code');
                var msg = 'Lookup failed.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                $inp.attr('title', msg);
                rcRecalcReceiptLineSubtotal(row);
            });
    }

    $(document).on('input', '#modal-receipt-confirmation .item-product-code', function () {
        var id = $(this).closest('tr').attr('id');
        if (!id || id.indexOf('item-row-') !== 0) {
            return;
        }
        rcScheduleReceiptLineLookup(id.replace('item-row-', ''));
    });

    $(document).on('blur', '#modal-receipt-confirmation .item-product-code', function () {
        var id = $(this).closest('tr').attr('id');
        if (!id || id.indexOf('item-row-') !== 0) {
            return;
        }
        var itemId = id.replace('item-row-', '');
        if (rcLineLookupTimer[itemId]) {
            clearTimeout(rcLineLookupTimer[itemId]);
        }
        rcRunReceiptLineLookup(itemId);
    });

    $(document).on('change', '#modal-receipt-confirmation .item-shop', function () {
        var row = $(this).closest('tr');
        if (row.find('.item-product-code').length) {
            var rid = row.attr('id');
            if (rid && rid.indexOf('item-row-') === 0) {
                rcScheduleReceiptLineLookup(rid.replace('item-row-', ''));
            }
        }
    });

    function editItem(receiptId, itemId) {
        var row = $('#item-row-' + itemId);
        if (rcIsQuickAddBlockedRow(row)) {
            alert('Complete or remove this item in Products → Quick Add before editing here.');
            return;
        }
        var actionsCell = $('#item-actions-' + itemId);

        rcDestroyLineProductSelect(row);

        row.data('rc-orig-price', row.find('.item-price').val());
        row.data('rc-orig-quantity', row.find('.item-quantity').val());
        row.data('rc-orig-discount', row.find('.item-discount').val());
        row.data('rc-orig-shop', row.find('.item-shop').val());
        var $name = row.find('.item-product-name');
        row.data('rc-orig-product-name', $name.length ? $name.val() : '');
        var origCode = row.find('.item-code-display').text().trim() || '';
        row.data('rc-orig-code', origCode);
        row.removeData('rc-picked-code');

        var shopId = row.find('.item-shop').val() || '';

        $('#item-code-cell-' + itemId).empty();
        var $sel = $('<select class="form-control input-sm item-product-code-select" style="width:100%" data-item-id="' + itemId + '"></select>');
        $('#item-code-cell-' + itemId).append($sel);

        $sel.select2({
            dropdownParent: $('#modal-receipt-confirmation'),
            placeholder: 'Type code, SKU, or description…',
            allowClear: true,
            minimumInputLength: 1,
            ajax: {
                url: '{{ route("receipt-confirmation.product-search") }}',
                dataType: 'json',
                delay: 280,
                data: function (params) {
                    return { q: params.term || '', shop_id: row.find('.item-shop').val() || '' };
                },
                processResults: function (data) {
                    var list = (data && data.results) ? data.results : [];
                    return {
                        results: $.map(list, function (x) {
                            return $.extend({
                                id: String(x.id_produk),
                                text: (x.item_code || '') + ' — ' + (x.nama_produk || '')
                            }, x);
                        })
                    };
                }
            },
            templateResult: rcFormatProductSearchOption,
            templateSelection: function (data) {
                if (!data.item_code) {
                    return data.text || '';
                }
                var name = (data.nama_produk || '');
                if (name.length > 45) {
                    name = name.substring(0, 45) + '…';
                }
                return (data.item_code || '') + ' — ' + name;
            }
        });

        $sel.on('select2:select', function (e) {
            rcApplyPickedProductToRow(row, e.params.data);
        });

        $sel.on('select2:clear', function () {
            row.removeData('rc-picked-code');
        });

        if (origCode && origCode !== 'N/A') {
            $.get('{{ route("receipt-confirmation.product-search") }}', { q: origCode, shop_id: shopId || '' })
                .done(function (data) {
                    var list = (data && data.results) ? data.results : [];
                    var pick = null;
                    var i;
                    for (i = 0; i < list.length; i++) {
                        var x = list[i];
                        if (String(x.shop_id) === String(shopId) && (x.item_code || '').toUpperCase() === origCode.toUpperCase()) {
                            pick = x;
                            break;
                        }
                    }
                    if (!pick) {
                        for (i = 0; i < list.length; i++) {
                            var y = list[i];
                            if ((y.item_code || '').toUpperCase() === origCode.toUpperCase()) {
                                pick = y;
                                break;
                            }
                        }
                    }
                    if (!pick && list.length > 0) {
                        pick = list[0];
                    }
                    if (pick) {
                        var label = (pick.item_code || '') + ' — ' + (pick.nama_produk || '');
                        $sel.append(new Option(label, String(pick.id_produk), true, true));
                        $sel.val(String(pick.id_produk)).trigger('change');
                        rcApplyPickedProductToRow(row, pick);
                    } else {
                        row.data('rc-picked-code', origCode);
                    }
                });
        }

        rcRecalcReceiptLineSubtotal(row);

        actionsCell.html(
            '<button onclick="updateItem(' + receiptId + ', ' + itemId + ')" class="btn btn-xs btn-success"><i class="fa fa-save"></i> Update</button> ' +
            '<button onclick="cancelEdit(' + receiptId + ', ' + itemId + ')" class="btn btn-xs btn-default"><i class="fa fa-times"></i> Cancel</button>'
        );
    }

    function cancelEdit(receiptId, itemId) {
        var row = $('#item-row-' + itemId);
        rcDestroyLineProductSelect(row);
        row.removeData('rc-picked-code');
        row.find('.item-price').val(row.data('rc-orig-price'));
        row.find('.item-quantity').val(row.data('rc-orig-quantity'));
        row.find('.item-discount').val(row.data('rc-orig-discount'));
        row.find('.item-shop').val(row.data('rc-orig-shop'));
        var $name = row.find('.item-product-name');
        if ($name.length) {
            $name.val(row.data('rc-orig-product-name') || '');
        }
        var origCode = row.data('rc-orig-code');
        $('#item-code-cell-' + itemId).empty().append(
            $('<span class="item-code-display"></span>').text(origCode != null && origCode !== '' ? origCode : 'N/A')
        );

        var itemStatus = $('#item-status-' + itemId).find('.label').text().toLowerCase();
        var waitingUpdate = $('#modal-receipt-confirmation').data('waiting-update');
        var isQuickAdd = parseInt($('#item-row-' + itemId).data('is-quick-add'), 10) === 1;
        var confirmBtn = rcConfirmActionHtml(receiptId, itemId, itemStatus, isQuickAdd, waitingUpdate);
        if (itemStatus !== 'confirmed') {
            $('#item-row-' + itemId + ' .item-checkbox').prop('checked', false).prop('disabled', !!waitingUpdate);
        } else {
            $('#item-row-' + itemId + ' .item-checkbox').prop('checked', true).prop('disabled', true);
        }
        var editBtn = rcEditButtonHtml(receiptId, itemId, isQuickAdd, waitingUpdate);
        $('#item-actions-' + itemId).html('<div class="btn-group">' + confirmBtn + editBtn + '</div>');
        rcRecalcReceiptLineSubtotal($('#item-row-' + itemId));
    }

    function updateItem(receiptId, itemId) {
        var row = $('#item-row-' + itemId);
        var price = parseFloat(row.find('.item-price').val()) || 0;
        var quantity = parseInt(row.find('.item-quantity').val(), 10) || 0;
        var discount = parseFloat(row.find('.item-discount').val()) || 0;
        var shopId = row.find('.item-shop').val();
        var $sel = row.find('.item-product-code-select');
        var itemCode = '';
        if ($sel.length) {
            itemCode = (row.data('rc-picked-code') || '').trim();
            if (!itemCode && $sel.val()) {
                var sd = $sel.select2('data');
                if (sd && sd.length && sd[0].item_code) {
                    itemCode = (sd[0].item_code || '').trim();
                }
            }
        } else {
            var $codeInput = row.find('.item-product-code');
            itemCode = $codeInput.length ? ($codeInput.val() || '').trim() : (row.find('.item-code-display').text() || '').trim();
        }

        if (!itemCode) {
            alert('Please choose a product from the dropdown (search by code or description).');
            return;
        }

        if (!price || price <= 0) {
            alert('Please enter a valid price.');
            return;
        }

        if (!quantity || quantity <= 0) {
            alert('Please enter a valid quantity.');
            return;
        }

        $.ajax({
            url: '{{ url("/receipt-confirmation") }}/' + receiptId + '/items/' + itemId + '/update',
            method: 'POST',
            data: {
                '_token': '{{ csrf_token() }}',
                'harga_jual': price,
                'jumlah': quantity,
                'diskon': discount || 0,
                'shop_id': shopId,
                'item_code': itemCode
            },
            success: function(response) {
                if (response.success) {
                    alert('Item updated successfully.');
                    confirmReceipt(receiptId);
                    rcReloadReceiptRelatedTables();
                } else {
                    alert('Error: ' + (response.error || 'Unknown error'));
                }
            },
            error: function(xhr) {
                var errorMsg = 'Unable to update item';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alert(errorMsg);
            }
        });
    }

    $(document).on('blur', '#modal-receipt-confirmation .item-product-name', function () {
        var $inp = $(this);
        if ($inp.data('saving')) {
            return;
        }
        var itemId = $inp.data('id');
        var receiptId = $('#modal-receipt-confirmation').data('receipt-id');
        if (!receiptId || !itemId) {
            return;
        }
        var newName = ($inp.val() || '').trim();
        var orig = ($inp.data('original-name') || '').trim();
        if (newName === orig) {
            return;
        }
        if (!newName) {
            $inp.val(orig);
            alert('Product name cannot be empty.');
            return;
        }
        $inp.data('saving', true);
        $.post('{{ url("/receipt-confirmation") }}/' + receiptId + '/items/' + itemId + '/update-product-name', {
            _token: '{{ csrf_token() }}',
            nama_produk: newName
        })
            .done(function (res) {
                var saved = (res && res.nama_produk != null) ? String(res.nama_produk).trim() : newName;
                $inp.val(saved);
                $inp.data('original-name', saved);
            })
            .fail(function (xhr) {
                $inp.val(orig);
                var msg = 'Failed to update product name.';
                var j = xhr.responseJSON;
                if (j) {
                    if (j.message) {
                        msg = j.message;
                    } else if (j.error) {
                        msg = j.error;
                    } else if (j.errors && j.errors.nama_produk) {
                        msg = j.errors.nama_produk[0] || msg;
                    }
                }
                alert(msg);
            })
            .always(function () {
                $inp.data('saving', false);
            });
    });

    $(document).on('input change', '#modal-receipt-confirmation .item-price, #modal-receipt-confirmation .item-quantity, #modal-receipt-confirmation .item-discount', function () {
        rcRecalcReceiptLineSubtotal($(this).closest('tr'));
    });

    $(document).on('change', '#modal-receipt-confirmation #select-all-items', function () {
        var isChecked = $(this).prop('checked');
        $('#modal-receipt-confirmation .item-checkbox:not(:disabled)').prop('checked', isChecked);
    });

    $(document).on('change', '.item-checkbox', function () {
        if (!$(this).closest('#modal-receipt-confirmation').length) {
            return;
        }
        var totalCheckboxes = $('#modal-receipt-confirmation .item-checkbox:not(:disabled)').length;
        var checkedCheckboxes = $('#modal-receipt-confirmation .item-checkbox:not(:disabled):checked').length;
        $('#modal-receipt-confirmation #select-all-items').prop('checked', totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
    });
</script>
