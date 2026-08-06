@extends('layouts.master')

@section('title')
    Supplier Withdrawal
@endsection

@section('breadcrumb')
    @parent
    <li><a href="{{ route('supplier.index') }}">Supplier List</a></li>
    <li class="active">Supplier Withdrawal</li>
@endsection

@push('css')
<style>
    .sw-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0,0,0,.08);
        border: 1px solid #e8e8e8;
        overflow: hidden;
        margin-bottom: 20px;
    }
    .sw-card__head {
        padding: 20px 24px 16px;
        border-bottom: 1px solid #eee;
        display: flex;
        align-items: flex-start;
        gap: 16px;
    }
    .sw-card__icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        background: linear-gradient(135deg, #1976d2, #2196f3);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        flex-shrink: 0;
    }
    .sw-card__title { margin: 0 0 4px; font-size: 22px; font-weight: 600; color: #1a1a1a; }
    .sw-card__sub { margin: 0; color: #777; font-size: 14px; }
    .sw-meta-row { padding: 16px 24px; display: flex; flex-wrap: wrap; gap: 16px; }
    .sw-meta-row .form-group { margin-bottom: 0; min-width: 200px; flex: 1; }
    .sw-meta-row label { font-weight: 600; color: #444; }
    .sw-table-wrap { padding: 0 24px 16px; overflow-x: auto; }
    .sw-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .sw-table thead th {
        background: #f5f7fa;
        color: #333;
        font-weight: 600;
        padding: 10px 8px;
        text-align: left;
        border-bottom: 2px solid #e0e0e0;
        white-space: nowrap;
    }
    .sw-table tbody td { padding: 8px; border-bottom: 1px solid #eee; vertical-align: middle; }
    .sw-table tbody tr:hover { background: #fafafa; }
    .sw-table .num { text-align: right; }
    .sw-table input.item-code-input { min-width: 160px; max-width: 280px; }
    .sw-table input.qty-input { width: 72px; text-align: right; }
    .sw-add-row { padding: 8px 24px 16px; }
    .sw-add-row a { font-weight: 600; cursor: pointer; }
    .sw-footer-bar {
        padding: 16px 24px 24px;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 16px;
        border-top: 1px solid #eee;
    }
    .sw-totals { text-align: right; }
    .sw-totals .items-count { color: #888; font-size: 13px; margin-bottom: 4px; }
    .sw-totals .grand-total { font-size: 28px; font-weight: 700; color: #1976d2; }
    .sw-actions .btn-record { padding: 12px 28px; font-size: 16px; font-weight: 600; }
    .sw-muted { color: #999; }
    .sw-line-error { background: #fff5f5 !important; }
    .sw-lookup-err { display: block; max-width: 280px; line-height: 1.3; }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="sw-card">
            <div class="sw-card__head">
                <div class="sw-card__icon"><i class="fa fa-cube"></i></div>
                <div>
                    <h1 class="sw-card__title">Supplier Withdrawal</h1>
                    <p class="sw-card__sub">Record items withdrawn from supplier stock</p>
                    @if(!empty($supplierContext))
                        <p class="sw-muted" style="margin-top:8px;margin-bottom:0;">
                            <i class="fa fa-truck"></i> Context: <strong>{{ $supplierContext->nama }}</strong> — use <strong>SHOP-ITEM</strong> in the item code field (same as POS).
                        </p>
                    @endif
                </div>
                <div style="margin-left:auto;">
                    <a href="{{ route('supplier.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Suppliers</a>
                    <a href="{{ route('supplier.withdrawals') }}" class="btn btn-default btn-sm"><i class="fa fa-list"></i> Withdrawals list</a>
                </div>
            </div>

            <form id="withdrawal-form" method="POST" action="{{ route('supplier.withdrawal.process') }}">
                @csrf

                <div class="sw-meta-row">
                    <div class="form-group">
                        <label for="withdrawal_date"><i class="fa fa-calendar text-primary"></i> Date <span class="text-danger">*</span></label>
                        <input type="date" name="withdrawal_date" id="withdrawal_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label for="receipt_no"><i class="fa fa-file-text-o text-primary"></i> Receipt No. <span class="text-danger">*</span></label>
                        <input type="text" name="receipt_no" id="receipt_no" class="form-control" placeholder="e.g. 55555" maxlength="120" required autocomplete="off">
                    </div>
                </div>

                <div class="sw-table-wrap">
                    <table class="sw-table" id="withdrawal-lines-table">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Item Name</th>
                                <th>Supplier</th>
                                <th class="num">Buying Price</th>
                                <th class="num">Selling Price</th>
                                <th class="num">Current Stock</th>
                                <th class="num">Qty</th>
                                <th class="num">Remaining</th>
                                <th class="num">Total</th>
                                <th style="width:48px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="withdrawal-lines-body">
                            {{-- rows added by JS --}}
                        </tbody>
                    </table>
                </div>

                <div class="sw-add-row">
                    <a type="button" id="btn-add-withdrawal-line" class="text-primary"><i class="fa fa-plus-circle"></i> Add Item</a>
                </div>

                <div class="sw-footer-bar">
                    <div class="text-muted small" style="max-width: 420px;">
                        Type <strong>shop code</strong> first — a <strong>-</strong> is added automatically (same as POS), then the <strong>original item code</strong> (not the long suffix). Example: <code>KILEO-MB31</code> finds <code>MB31_1774004393_158</code> in that shop. <strong>Remaining</strong> shows stock after withdrawal.
                        <input type="hidden" name="reason" id="reason" value="Supplier withdrawal">
                        <textarea name="notes" id="notes" style="display:none;"></textarea>
                    </div>
                    <div class="sw-totals">
                        <div class="items-count"><span id="sw-item-count">0</span> items</div>
                        <div class="grand-total">KES <span id="sw-grand-total">0.00</span></div>
                    </div>
                    <div class="sw-actions">
                        <button type="submit" class="btn btn-primary btn-record" id="btn-submit-withdrawal">
                            Record Withdrawal
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<template id="withdrawal-line-template">
    <tr class="withdrawal-line" data-line-index="__INDEX__">
        <td>
            <input type="text" class="form-control input-sm item-code-input" autocomplete="off" placeholder="SHOP-ITEM (e.g. KILEO-MB31)" data-index="__INDEX__" title="Shop code, then dash, then original item code (base code only)">
            <span class="sw-lookup-err text-danger small" style="display:none;"></span>
            <input type="hidden" class="produk-id-input" value="">
        </td>
        <td class="cell-name sw-muted">—</td>
        <td class="cell-supplier sw-muted">—</td>
        <td class="cell-buy num sw-muted">—</td>
        <td class="cell-sell num sw-muted">—</td>
        <td class="cell-current-stock num sw-muted">—</td>
        <td class="num">
            <input type="number" class="form-control input-sm qty-input" min="0" value="0" step="1" placeholder="0" disabled data-index="__INDEX__">
        </td>
        <td class="cell-remaining num sw-muted">—</td>
        <td class="cell-line-total num sw-muted">—</td>
        <td>
            <button type="button" class="btn btn-link btn-xs text-danger btn-remove-line" title="Remove"><i class="fa fa-trash"></i></button>
        </td>
    </tr>
</template>
@endsection

@push('scripts')
<script>
(function () {
    var lineIndex = 0;
    var lookupUrl = @json(route('supplier.withdrawal.lookup'));
    var csrf = $('meta[name="csrf-token"]').attr('content');
    /** Same parsing rules as POS (penjualan_detail): SHOP-ITEM or SHOP ITEM */
    var dashTimeouts = {};
    var lookupBlurTimers = {};

    function parseShopCodeAndProduct(input) {
        if (!input || String(input).trim() === '') {
            return { shopCode: '', productSearch: '' };
        }
        var trimmedInput = String(input).trim();
        if (trimmedInput.indexOf('-') !== -1) {
            var dashIndex = trimmedInput.indexOf('-');
            return {
                shopCode: trimmedInput.substring(0, dashIndex).trim(),
                productSearch: trimmedInput.substring(dashIndex + 1).trim()
            };
        }
        var parts = trimmedInput.split(/\s+/).filter(function (p) { return p.length; });
        if (parts.length === 0) {
            return { shopCode: '', productSearch: '' };
        }
        if (parts.length < 2) {
            return { shopCode: parts[0], productSearch: '' };
        }
        return {
            shopCode: parts[0],
            productSearch: parts.slice(1).join(' ')
        };
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function formatMoney(n) {
        var x = parseFloat(n) || 0;
        return x.toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function addLine() {
        var tpl = document.getElementById('withdrawal-line-template');
        var html = tpl.innerHTML.replace(/__INDEX__/g, String(lineIndex));
        $('#withdrawal-lines-body').append(html);
        lineIndex++;
        updateTotals();
    }

    function rowData($tr) {
        return {
            produkId: $tr.find('.produk-id-input').val(),
            qty: parseInt($tr.find('.qty-input').val(), 10) || 0,
            buy: parseFloat($tr.data('harga-beli')) || 0
        };
    }

    function updateTotals() {
        var count = 0;
        var sum = 0;
        $('.withdrawal-line').each(function () {
            var $tr = $(this);
            var d = rowData($tr);
            if (!d.produkId || d.qty < 1) return;
            count++;
            sum += d.buy * d.qty;
        });
        $('#sw-item-count').text(count);
        $('#sw-grand-total').text(formatMoney(sum));
    }

    function setLookupError($tr, msg) {
        var $e = $tr.find('.sw-lookup-err');
        if (!msg) {
            $e.hide().text('');
            return;
        }
        $e.text(msg).show();
    }

    function clearResolvedProduct($tr) {
        $tr.find('.produk-id-input').val('');
        setLookupError($tr, '');
        $tr.find('.cell-name,.cell-supplier,.cell-current-stock,.cell-buy,.cell-sell').addClass('sw-muted').text('—');
        $tr.find('.cell-remaining').addClass('sw-muted').text('—');
        $tr.find('.qty-input').prop('disabled', true).val(0);
        $tr.find('.cell-line-total').addClass('sw-muted').text('—');
        $tr.removeData('harga-beli').removeData('stok');
        $tr.removeClass('sw-line-error');
        updateTotals();
    }

    function updateRemainingStock($tr) {
        var pid = $tr.find('.produk-id-input').val();
        var stok = parseInt($tr.data('stok'), 10) || 0;
        var qty = parseInt($tr.find('.qty-input').val(), 10) || 0;
        if (!pid) {
            $tr.find('.cell-remaining').addClass('sw-muted').text('—');
            return;
        }
        var rem = Math.max(0, stok - qty);
        $tr.find('.cell-remaining').removeClass('sw-muted').text(String(rem));
    }

    function fillRow($tr, p) {
        setLookupError($tr, '');
        $tr.find('.produk-id-input').val(p.id_produk);
        $tr.data('harga-beli', p.harga_beli);
        $tr.data('stok', p.stok);
        $tr.find('.cell-name').removeClass('sw-muted').text(p.nama_produk);
        $tr.find('.cell-supplier').removeClass('sw-muted').text(p.supplier_name || '—');
        $tr.find('.cell-current-stock').removeClass('sw-muted').text(String(parseInt(p.stok, 10) || 0));
        $tr.find('.cell-buy').removeClass('sw-muted').text(formatMoney(p.harga_beli));
        $tr.find('.cell-sell').removeClass('sw-muted').text(formatMoney(p.harga_jual));
        var $qty = $tr.find('.qty-input');
        $qty.attr('max', p.stok);
        if (p.stok < 1) {
            $qty.prop('disabled', true).val(0);
            $tr.addClass('sw-line-error');
        } else {
            $qty.prop('disabled', false).val(0);
            $tr.removeClass('sw-line-error');
        }
        updateRemainingStock($tr);
        updateLineTotal($tr);
    }

    function updateLineTotal($tr) {
        var d = rowData($tr);
        var tot = (d.produkId && d.qty > 0) ? d.buy * d.qty : 0;
        if (d.produkId) {
            var max = parseInt($tr.data('stok'), 10) || 0;
            if (d.qty > max) {
                $tr.find('.qty-input').val(max);
                d.qty = max;
                tot = d.buy * d.qty;
            }
            $tr.find('.cell-line-total').removeClass('sw-muted').text('KES ' + formatMoney(tot));
            updateRemainingStock($tr);
        } else {
            $tr.find('.cell-line-total').addClass('sw-muted').text('—');
            $tr.find('.cell-remaining').addClass('sw-muted').text('—');
        }
        updateTotals();
    }

    function lookupFromRow($tr) {
        var $inp = $tr.find('.item-code-input');
        var combined = $.trim($inp.val());
        if (!combined) {
            setLookupError($tr, '');
            clearResolvedProduct($tr);
            return;
        }
        // If user tabs away before the auto-dash runs, add "-" after shop-only input (same as POS)
        if (combined.indexOf('-') === -1 && combined.indexOf(' ') === -1) {
            var shopOnly = /^[A-Za-z0-9]+$/;
            if (shopOnly.test(combined)) {
                combined = combined + '-';
                $inp.val(combined);
            }
        }
        var parsed = parseShopCodeAndProduct(combined);
        if (!parsed.shopCode || !parsed.productSearch) {
            setLookupError($tr, '');
            clearResolvedProduct($tr);
            return;
        }

        $tr.data('lookup-requesting', 1);
        setLookupError($tr, '');

        $.ajax({
            url: lookupUrl,
            dataType: 'json',
            data: { code: combined }
        })
            .done(function (res) {
                $tr.removeData('lookup-requesting');
                if (res.found && res.produk) {
                    fillRow($tr, res.produk);
                } else {
                    var m = res.message || 'Product not found';
                    clearResolvedProduct($tr);
                    setLookupError($tr, m);
                }
            })
            .fail(function (xhr) {
                $tr.removeData('lookup-requesting');
                var msg = 'Lookup failed. Check your connection.';
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    else if (xhr.responseJSON.errors && xhr.responseJSON.errors.code) msg = xhr.responseJSON.errors.code[0];
                }
                clearResolvedProduct($tr);
                setLookupError($tr, msg);
            });
    }

    /** Auto-insert "-" after shop code (same behaviour as POS product search). */
    $(document).on('input', '.item-code-input', function (e) {
        var $input = $(this);
        var idx = $input.data('index');
        if (dashTimeouts[idx]) {
            clearTimeout(dashTimeouts[idx]);
        }
        var el = this;
        var currentValue = $input.val();
        var cursorPos = el.selectionStart;
        if (cursorPos === currentValue.length && currentValue.trim()) {
            var shopCodePattern = /^[A-Za-z0-9]+$/;
            if (shopCodePattern.test(currentValue.trim()) && currentValue.indexOf('-') === -1 && currentValue.indexOf(' ') === -1) {
                dashTimeouts[idx] = setTimeout(function () {
                    var value = $input.val();
                    if (value && value.indexOf('-') === -1 && value.indexOf(' ') === -1 && shopCodePattern.test(value.trim())) {
                        var newValue = value + '-';
                        $input.val(newValue);
                        var newPos = newValue.length;
                        el.setSelectionRange(newPos, newPos);
                    }
                }, 250);
            }
        }
    });

    $('#btn-add-withdrawal-line').on('click', function (e) {
        e.preventDefault();
        addLine();
    });

    $(document).on('blur', '.item-code-input', function () {
        var $inp = $(this);
        var $tr = $inp.closest('tr');
        var idx = $inp.data('index');
        if (lookupBlurTimers[idx]) {
            clearTimeout(lookupBlurTimers[idx]);
        }
        // Debounce so auto-dash can finish; avoids duplicate lookups / focus loops after alerts
        lookupBlurTimers[idx] = setTimeout(function () {
            lookupFromRow($tr);
        }, 280);
    });

    $(document).on('focus', '.item-code-input', function () {
        var idx = $(this).data('index');
        if (lookupBlurTimers[idx]) {
            clearTimeout(lookupBlurTimers[idx]);
            lookupBlurTimers[idx] = null;
        }
    });

    $(document).on('keydown', '.item-code-input', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            $(this).blur();
        }
    });

    $(document).on('change input', '.qty-input', function () {
        var $tr = $(this).closest('tr');
        var raw = $(this).val();
        var q = raw === '' || raw === null ? 0 : parseInt(raw, 10);
        if (isNaN(q)) q = 0;
        var max = parseInt($tr.data('stok'), 10) || 0;
        if (q < 0) {
            $(this).val(0);
            q = 0;
        }
        if (max > 0 && q > max) {
            $(this).val(max);
            q = max;
            alert('Quantity cannot exceed current stock (' + max + ').');
        }
        updateLineTotal($tr);
    });

    $(document).on('click', '.btn-remove-line', function () {
        $(this).closest('tr').remove();
        updateTotals();
    });

    $('#withdrawal-form').on('submit', function (e) {
        e.preventDefault();
        var payload = [];
        $('.withdrawal-line').each(function () {
            var $tr = $(this);
            var pid = $tr.find('.produk-id-input').val();
            var qty = parseInt($tr.find('.qty-input').val(), 10) || 0;
            if (pid && qty > 0) {
                payload.push({ produk_id: pid, quantity: qty });
            }
        });
        if (payload.length === 0) {
            alert('Add at least one item: resolve the item code and enter a quantity of 1 or more (quantity defaults to 0 until you type it).');
            return;
        }
        var receiptNo = ($('#receipt_no').val() || '').trim();
        if (!receiptNo) {
            alert('Please enter a receipt number before recording this withdrawal.');
            $('#receipt_no').trigger('focus');
            return;
        }
        if (!confirm('Record this withdrawal? Stock will be reduced.')) return;

        var $btn = $('#btn-submit-withdrawal').prop('disabled', true);
        var postData = {
            _token: csrf,
            withdrawal_date: $('#withdrawal_date').val(),
            receipt_no: receiptNo,
            reason: $('#reason').val(),
            notes: $('#notes').val()
        };
        payload.forEach(function (row, i) {
            postData['withdrawals[' + i + '][produk_id]'] = row.produk_id;
            postData['withdrawals[' + i + '][quantity]'] = row.quantity;
        });
        $.ajax({
            url: $('#withdrawal-form').attr('action'),
            type: 'POST',
            data: postData,
            success: function (res) {
                alert('Withdrawal saved. Number: ' + (res.withdrawal_number || ''));
                window.location.reload();
            },
            error: function (xhr) {
                var msg = 'Could not save withdrawal.';
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    if (xhr.status === 422 && xhr.responseJSON.errors) {
                        var errs = xhr.responseJSON.errors;
                        var first = [];
                        Object.keys(errs).forEach(function (k) {
                            if (Array.isArray(errs[k])) {
                                first = first.concat(errs[k]);
                            }
                        });
                        if (first.length) {
                            msg = first.join(' ');
                        }
                    }
                }
                alert(msg);
            },
            complete: function () {
                $btn.prop('disabled', false);
            }
        });
    });

    $(function () {
        addLine();
    });
})();
</script>
@endpush
