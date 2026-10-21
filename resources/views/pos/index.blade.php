@extends('layouts.pos')

@section('content')
<div class="pos-wrap">
    <div class="pos-left">
        <div class="pos-filters">
            <form action="{{ route('branch.switch') }}" method="post" class="pos-branch-form">
                @csrf
                <select name="branch_id" class="form-control" onchange="this.form.submit()">
                    @foreach($branches as $option)
                        <option value="{{ $option->id }}" @if(!empty($branch) && (int) $branch->id === (int) $option->id) selected @endif>{{ $option->name }}</option>
                    @endforeach
                </select>
            </form>
            <div class="pos-customer-wrap">
                <select id="sx-pos-customer" class="form-control">
                    @foreach($customers as $customer)
                        <option
                            value="{{ $customer->id }}"
                            data-walk-in="{{ $customer->is_walk_in ? '1' : '0' }}"
                            data-credit-limit="{{ (float) $customer->credit_limit }}"
                            data-credit-used="{{ $customer->creditAmount() }}"
                            data-points="{{ (float) $customer->loyalty_points }}"
                            @if((int) $customer->id === (int) $walkInId) selected @endif
                        >
                            {{ $customer->is_walk_in ? 'WALK-IN' : $customer->name }}{{ $customer->phone ? ' - '.$customer->phone : '' }}
                        </option>
                    @endforeach
                </select>
                @if($canCreateCustomer)
                    <button type="button" class="pos-cust-btn" id="sx-pos-add-cust" title="Add customer"><i class="fa fa-user-plus"></i></button>
                @endif
            </div>
        </div>
        <div class="pos-filters">
            <div class="pos-date-wrap">
                <span class="pos-search-icon"><i class="fa fa-calendar"></i></span>
                <input type="date" id="sx-pos-date" class="form-control" value="{{ now()->toDateString() }}">
            </div>
            <div class="pos-search-wrap">
                <span class="pos-search-icon"><i class="fa fa-th"></i></span>
                <input type="text" id="sx-pos-search" class="form-control" placeholder="Scan barcode or type item name (F10)" autocomplete="off">
                <div id="sx-pos-results" class="pos-search-results" hidden></div>
            </div>
        </div>

        <div class="pos-cart-wrap">
            <table class="table pos-cart">
                <thead>
                    <tr>
                        <th class="pos-col-name">ITEM NAME</th>
                        <th class="pos-col-qty">ITEM QTY</th>
                        <th class="pos-col-price">PRICE<br>INC. TAX</th>
                        <th class="pos-col-type">TYPE</th>
                        <th class="pos-col-sub">SUBTOTAL</th>
                        <th class="pos-col-tax">TAX</th>
                        <th class="pos-x"><span class="pos-x-icon"><i class="fa fa-times"></i></span></th>
                    </tr>
                </thead>
                <tbody id="sx-pos-rows"></tbody>
            </table>
        </div>

        <div class="pos-footer">
            <div class="pos-totals">
                <div>
                    <span>Total Item Qty:</span>
                    <strong id="sx-pos-qty">0</strong>
                </div>
                <div>
                    <span>Total Amount:</span>
                    <strong>Ksh <span id="sx-pos-amount">0.00</span></strong>
                </div>
                <div>
                    <span>Total Discount: <a href="#" id="sx-pos-disc-btn" title="Discount">Disc. <i class="fa fa-edit"></i></a></span>
                    <strong>Ksh <span id="sx-pos-discount">0.00</span></strong>
                </div>
                <div>
                    <span>Grand Total:</span>
                    <strong>Ksh <span id="sx-pos-grand">0.00</span></strong>
                </div>
            </div>

            <div class="pos-meta">
                <div class="pos-meta-field">
                    <label for="sx-pos-due">Due Date</label>
                    <input type="date" id="sx-pos-due" class="form-control" value="{{ now()->toDateString() }}">
                </div>
                <div class="pos-meta-field">
                    <label for="sx-pos-doctype">Inv No</label>
                    <select id="sx-pos-doctype" class="form-control">
                        <option value="pos" @if(($documentType ?? 'pos') !== 'invoice') selected @endif>Receipt</option>
                        <option value="invoice" @if(($documentType ?? '') === 'invoice') selected @endif>Invoice</option>
                    </select>
                </div>
            </div>

            <div class="pos-actions">
                <button type="button" class="btn pos-btn-hold" id="sx-pos-hold"><i class="fa fa-hand-stop-o"></i> HOLD (F4)</button>
                <button type="button" class="btn pos-btn-cust" id="sx-pos-cust"><i class="fa fa-user"></i> CUST (F7)</button>
            </div>
            <div class="pos-links">
                <a href="{{ route('stock.conversion') }}"><i class="fa fa-barcode"></i> Stock Conversion</a>
                <a href="{{ route('stock.manager') }}"><i class="fa fa-balance-scale"></i> +/- ADJUST</a>
                <a href="#" id="sx-pos-holds-link"><i class="fa fa-list"></i> HOLD LIST</a>
            </div>
        </div>
    </div>

    <div class="pos-right">
        <div class="pos-receive-head">
            <i class="fa fa-money"></i> RECEIVE PAYMENT
        </div>
        <form id="sx-pos-pay-form" class="pos-receive">
            <div class="pos-receive-label">PAYMENT METHOD</div>
            <div class="pos-methods">
                <button type="button" class="pos-method is-active" data-method="cash">
                    <i class="fa fa-money"></i>
                    <span>Cash</span>
                </button>
                <button type="button" class="pos-method" data-method="mpesa">
                    <i class="fa fa-mobile"></i>
                    <span>M-Pesa</span>
                </button>
                <button type="button" class="pos-method" data-method="card">
                    <i class="fa fa-credit-card"></i>
                    <span>Card</span>
                </button>
            </div>

            <label class="pos-check">
                <input type="checkbox" id="sx-pay-split">
                <span>
                    <strong>Split payment</strong>
                    <small>Cash, M-Pesa and Card together</small>
                </span>
            </label>

            <div id="sx-pay-single">
                <div class="pos-receive-label" id="sx-pay-amount-label">CASH RECEIVED</div>
                <div class="pos-receive-box">
                    <span>Ksh</span>
                    <input type="number" step="0.01" min="0" id="sx-pay-received-input" value="0.00">
                </div>
                <div class="pos-change-row">
                    <span>Change to return</span>
                    <strong>Ksh <span id="sx-pay-change">0.00</span></strong>
                </div>
            </div>

            <div id="sx-pay-split-fields" hidden>
                <div class="pos-split-row">
                    <label for="sx-pay-cash">Cash</label>
                    <input type="number" step="0.01" min="0" id="sx-pay-cash" class="form-control pos-tender" data-method="cash" value="0.00">
                </div>
                <div class="pos-split-row">
                    <label for="sx-pay-mpesa">M-Pesa</label>
                    <input type="number" step="0.01" min="0" id="sx-pay-mpesa" class="form-control pos-tender" data-method="mpesa" value="0.00">
                </div>
                <div class="pos-split-row">
                    <label for="sx-pay-card">Card</label>
                    <input type="number" step="0.01" min="0" id="sx-pay-card" class="form-control pos-tender" data-method="card" value="0.00">
                </div>
                <div class="pos-change-row">
                    <span>Change to return</span>
                    <strong>Ksh <span id="sx-pay-change-split">0.00</span></strong>
                </div>
            </div>

            <div class="pos-pay-extra" id="sx-mpesa-extra" hidden>
                <label for="sx-pay-mpesa-code">Trans Code</label>
                <input type="text" id="sx-pay-mpesa-code" class="form-control" placeholder="Transaction Code">
            </div>

            <label class="pos-check">
                <input type="checkbox" id="sx-pay-points">
                <span>
                    <strong>Pay with points</strong>
                    <small id="sx-pay-points-hint">Select a customer account</small>
                </span>
            </label>

            <div class="pos-receive-label">NOTE (OPTIONAL)</div>
            <input type="text" id="sx-pay-note" class="form-control pos-note-input" placeholder="What's this payment for?">

            <div class="pos-pay-credit-note" id="sx-pay-credit-note" hidden>
                Credit sale allowed for this customer. Leave unpaid amount as account balance.
            </div>

            <div class="pos-receive-actions">
                <button type="button" class="pos-pay-credit" id="sx-pay-credit" hidden><i class="fa fa-book"></i> Charge Account</button>
                <button type="submit" class="pos-pay-complete"><i class="fa fa-check"></i> Complete transaction <i class="fa fa-arrow-right"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="sx-pos-customer-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Add Customer</h4>
            </div>
            <div class="modal-body">
                <form id="sx-pos-customer-form">
                    <div class="form-group">
                        <label class="sx-req">Name*</label>
                        <input type="text" name="name" class="form-control" required placeholder="Customer name">
                    </div>
                    <div class="form-group">
                        <label>Mobile</label>
                        <input type="text" name="phone" class="form-control" placeholder="Mobile Number">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" placeholder="Email Address">
                    </div>
                    <div class="sx-form-actions">
                        <button type="submit" class="btn btn-success">Save</button>
                        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="sx-pos-holds-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Hold List</h4>
            </div>
            <div class="modal-body">
                <table class="table table-bordered sx-gold-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Number</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Held at</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="sx-pos-holds-rows">
                        <tr><td colspan="6">No held sales.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function ($) {
    var searchUrl = @json(route('pos.search'));
    var customerUrl = @json(route('pos.customers.store'));
    var holdUrl = @json(route('pos.hold'));
    var orderUrl = @json(route('pos.order'));
    var holdsUrl = @json(route('pos.holds'));
    var token = $('meta[name="csrf-token"]').attr('content');
    var cart = [];
    var checkoutKey = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : String(Date.now());
    var saleBusy = false;
    var discount = 0;
    var holdId = null;
    var searchTimer = null;
    var lastAutoReceived = 0;
    var payMethod = 'cash';

    function money(n) {
        return (Math.round((n || 0) * 100) / 100).toFixed(2);
    }
    function escapeHtml(v) {
        return $('<div>').text(v == null ? '' : v).html();
    }

    function linePrice(row) {
        var inc = row.price;
        if (row.type === 'W' && row.wholesale_price > 0) {
            inc = row.tax_inclusive ? row.wholesale_price : row.wholesale_price * (1 + (row.tax_rate || 0) / 100);
        }
        return row.apply_tax ? inc : Math.max(0, inc - (row.tax_amount || 0));
    }
    function lineTax(row) {
        return row.apply_tax ? (row.tax_amount || 0) : 0;
    }

    function totals() {
        var qty = 0, amount = 0, tax = 0;
        cart.forEach(function (row) {
            qty += row.quantity;
            amount += linePrice(row) * row.quantity;
            tax += lineTax(row) * row.quantity;
        });
        var disc = Math.min(amount, discount);
        return {
            qty: qty,
            amount: amount,
            exclusive: Math.max(0, amount - tax),
            tax: tax,
            discount: disc,
            grand: Math.max(0, amount - disc)
        };
    }

    function renderCart() {
        var html = '';
        cart.forEach(function (row, i) {
            var price = linePrice(row);
            html += '<tr class="pos-line-row">';
            html += '<td class="pos-line-name">' + escapeHtml(row.name) + '</td>';
            html += '<td class="pos-line-qty">';
            html += '<div class="pos-stepper">';
            html += '<button type="button" class="pos-step" data-i="' + i + '" data-d="-1">&minus;</button>';
            html += '<input type="number" min="0.0001" step="1" class="pos-qty" data-i="' + i + '" value="' + row.quantity + '">';
            html += '<button type="button" class="pos-step" data-i="' + i + '" data-d="1">+</button>';
            html += '</div>';
            html += '<input type="text" class="pos-serial" data-i="' + i + '" placeholder="Enter Serial/Key/Unit" value="' + escapeHtml(row.serial || '') + '">';
            html += '</td>';
            html += '<td>' + money(price) + '</td>';
            html += '<td><select class="pos-type" data-i="' + i + '"><option value="R"' + (row.type === 'R' ? ' selected' : '') + '>R</option><option value="W"' + (row.type === 'W' ? ' selected' : '') + '>W</option></select></td>';
            html += '<td>' + money(price * row.quantity) + '</td>';
            html += '<td class="pos-tax-cell"><input type="checkbox" class="pos-tax" data-i="' + i + '"' + (row.apply_tax ? ' checked' : '') + '></td>';
            html += '<td><button type="button" class="pos-remove" data-i="' + i + '" title="Remove"><i class="fa fa-trash-o"></i></button></td>';
            html += '</tr>';
        });
        $('#sx-pos-rows').html(html);
        var t = totals();
        $('#sx-pos-qty').text(t.qty);
        $('#sx-pos-amount').text(money(t.amount));
        $('#sx-pos-discount').text(money(t.discount));
        $('#sx-pos-grand').text(money(t.grand));
        if (!$('#sx-pay-split').is(':checked')) {
            var current = Math.round((parseFloat($('#sx-pay-received-input').val()) || 0) * 100) / 100;
            if (current <= 0 || current === lastAutoReceived) {
                $('#sx-pay-received-input').val(money(t.grand));
                lastAutoReceived = t.grand;
            }
        }
        refreshPayPanel();
    }

    function priceMissing(item) {
        return !((parseFloat(item.selling_price) || 0) > 0) && !((parseFloat(item.price) || 0) > 0);
    }

    function withSellingPrice(item, selling) {
        selling = Math.round(parseFloat(selling) * 100) / 100;
        var rate = parseFloat(item.tax_rate) || 0;
        var tax = 0;
        var price = selling;
        if (rate > 0) {
            if (item.tax_inclusive) {
                tax = Math.round(selling * rate / (100 + rate) * 100) / 100;
            } else {
                tax = Math.round(selling * rate / 100 * 100) / 100;
                price = Math.round(selling * (1 + rate / 100) * 100) / 100;
            }
        }
        var line = $.extend({}, item);
        line.selling_price = selling;
        line.price = price;
        line.tax_amount = tax;
        return line;
    }

    function addProduct(item, qty) {
        qty = qty || 1;
        if (item.variants && item.variants.length && !item.variant_id) {
            var options = {};
            item.variants.forEach(function (variant) {
                options[String(variant.id)] = variant.name + ' — ' + variant.qty;
            });
            Swal.fire({
                title: 'Select colour',
                text: item.name,
                input: 'select',
                inputOptions: options,
                inputPlaceholder: 'Choose a colour',
                showCancelButton: true
            }).then(function (result) {
                var chosen = result && (result.value || (result.isConfirmed ? result.value : null));
                if (!chosen) {
                    return;
                }
                var variant = null;
                item.variants.forEach(function (row) {
                    if (String(row.id) === String(chosen)) {
                        variant = row;
                    }
                });
                if (!variant) {
                    return;
                }
                var line = $.extend({}, item);
                line.variant_id = variant.id;
                line.name = item.name + ' (' + variant.name + ')';
                line.qty = variant.qty;
                addProduct(line, qty);
            });
            return;
        }
        var key = String(item.id) + ':' + String(item.variant_id || 0);
        var existing = cart.filter(function (row) { return row.key === key; })[0];
        if (priceMissing(item) && !(existing && !priceMissing(existing))) {
            Swal.fire({
                title: 'Selling price',
                text: item.name,
                input: 'number',
                inputAttributes: { min: 0.01, step: 0.01 },
                inputPlaceholder: 'Enter selling price',
                showCancelButton: true,
                confirmButtonText: 'Add',
                inputValidator: function (value) {
                    if (!(parseFloat(value) > 0)) {
                        return 'Enter a selling price';
                    }
                }
            }).then(function (result) {
                var amount = result && parseFloat(result.value);
                if (!(amount > 0)) {
                    return;
                }
                addProduct(withSellingPrice(item, amount), qty);
            });
            return;
        }
        if (existing) {
            existing.quantity += qty;
        } else {
            cart.push({
                key: key,
                id: item.id,
                variant_id: item.variant_id || 0,
                name: item.name,
                price: item.price,
                selling_price: item.selling_price || item.price,
                wholesale_price: item.wholesale_price || 0,
                tax_inclusive: !!item.tax_inclusive,
                tax_rate: item.tax_rate || 0,
                tax_amount: item.tax_amount || 0,
                apply_tax: true,
                type: 'R',
                serial: '',
                quantity: qty
            });
        }
        renderCart();
    }

    function cartPayload() {
        return {
            hold_id: holdId,
            customer_id: $('#sx-pos-customer').val(),
            sale_date: $('#sx-pos-date').val(),
            due_date: $('#sx-pos-due').val(),
            document_type: $('#sx-pos-doctype').val(),
            discount_amount: discount,
            idempotency_key: checkoutKey,
            items: cart.map(function (row) {
                return {
                    product_id: row.id,
                    product_variant_id: row.variant_id || 0,
                    quantity: row.quantity,
                    selling_price: row.selling_price || 0
                };
            })
        };
    }

    function resetCart() {
        cart = [];
        discount = 0;
        holdId = null;
        lastAutoReceived = 0;
        checkoutKey = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : String(Date.now());
        $('#sx-pay-received-input').val('0.00');
        $('#sx-pay-cash, #sx-pay-mpesa, #sx-pay-card').val('0.00');
        $('#sx-pay-mpesa-code, #sx-pay-note').val('');
        $('#sx-pay-points').prop('checked', false);
        $('#sx-mpesa-extra').prop('hidden', true);
        renderCart();
    }

    $('#sx-pos-search').on('input', function () {
        var q = $.trim($(this).val());
        clearTimeout(searchTimer);
        if (!q) {
            $('#sx-pos-results').prop('hidden', true).empty();
            return;
        }
        searchTimer = setTimeout(function () {
            $.getJSON(searchUrl, { q: q }).done(function (items) {
                if (!items.length) {
                    $('#sx-pos-results').prop('hidden', true).empty();
                    return;
                }
                var html = '';
                items.forEach(function (item) {
                    html += '<button type="button" class="pos-search-item" data-item=\'' + JSON.stringify(item).replace(/'/g, '&#39;') + '\'>' + escapeHtml(item.name) + ' <small>' + money(item.price) + '</small></button>';
                });
                $('#sx-pos-results').html(html).prop('hidden', false);
            });
        }, 200);
    });
    $(document).on('click', '.pos-search-item', function () {
        var item = $(this).data('item');
        if (typeof item === 'string') item = JSON.parse(item.replace(/&#39;/g, "'"));
        addProduct(item, 1);
        $('#sx-pos-search').val('');
        $('#sx-pos-results').prop('hidden', true).empty();
    });
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.pos-search-wrap').length) {
            $('#sx-pos-results').prop('hidden', true);
        }
    });

    $(document).on('click', '.pos-step', function () {
        var i = $(this).data('i');
        cart[i].quantity += parseInt($(this).data('d'), 10);
        if (cart[i].quantity <= 0) cart.splice(i, 1);
        renderCart();
    });
    $(document).on('change', '.pos-qty', function () {
        var i = $(this).data('i');
        var qty = parseFloat($(this).val());
        if (!(qty > 0)) {
            cart.splice(i, 1);
        } else {
            cart[i].quantity = qty;
        }
        renderCart();
    });
    $(document).on('input', '.pos-serial', function () {
        cart[$(this).data('i')].serial = $(this).val();
    });
    $(document).on('change', '.pos-type', function () {
        cart[$(this).data('i')].type = $(this).val();
        renderCart();
    });
    $(document).on('change', '.pos-tax', function () {
        cart[$(this).data('i')].apply_tax = this.checked;
        renderCart();
    });
    $(document).on('click', '.pos-remove', function () {
        cart.splice($(this).data('i'), 1);
        renderCart();
    });

    $('#sx-pos-new').on('click', resetCart);
    $('#sx-pos-disc-btn').on('click', function (e) {
        e.preventDefault();
        var value = window.prompt('Discount amount', discount || 0);
        if (value === null) return;
        discount = Math.max(0, parseFloat(value) || 0);
        renderCart();
    });

    function openCustomer() {
        $('#sx-pos-customer-form')[0].reset();
        $('#sx-pos-customer-modal').modal('show');
    }
    $('#sx-pos-add-cust, #sx-pos-cust').on('click', openCustomer);
    $('#sx-pos-customer-form').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: customerUrl,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            data: $(this).serialize()
        }).done(function (data) {
            var $opt = $('<option>', {
                value: data.id,
                text: data.name + (data.phone ? ' - ' + data.phone : ''),
                selected: true
            });
            $opt.attr('data-walk-in', '0');
            $opt.attr('data-credit-limit', data.credit_limit != null ? data.credit_limit : 0);
            $opt.attr('data-credit-used', data.credit_used != null ? data.credit_used : 0);
            $opt.attr('data-points', data.loyalty_points != null ? data.loyalty_points : 0);
            $('#sx-pos-customer').append($opt);
            updateCreditUi();
            $('#sx-pos-customer-modal').modal('hide');
        }).fail(function () {
            Swal.fire({ icon: 'error', title: 'Could not save customer' });
        });
    });

    function requireCart() {
        if (!cart.length) {
            Swal.fire({ icon: 'warning', title: 'Add an item' });
            return false;
        }
        var missing = cart.filter(function (row) { return !(linePrice(row) > 0); })[0];
        if (missing) {
            Swal.fire({ icon: 'warning', title: 'Selling price', text: 'Enter a selling price for ' + missing.name + '.' });
            return false;
        }
        return true;
    }

    $('#sx-pos-hold').on('click', function () {
        if (!requireCart()) return;
        $.ajax({
            url: holdUrl,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            data: cartPayload()
        }).done(function (data) {
            $('#sx-pos-hold-count').text(data.held_count || 0);
            resetCart();
            Swal.fire({ icon: 'success', title: 'Held', text: data.number, timer: 1400, showConfirmButton: false });
        }).fail(function (xhr) {
            Swal.fire({ icon: 'error', title: 'Hold failed', text: (xhr.responseJSON && xhr.responseJSON.message) || 'Could not hold sale.' });
        });
    });

    var walkInId = @json($walkInId);

    function selectedCustomerOption() {
        return $('#sx-pos-customer option:selected');
    }
    function canSellOnCredit() {
        var $opt = selectedCustomerOption();
        if (!$opt.length) return false;
        if (String($opt.data('walk-in')) === '1') return false;
        if (walkInId && String($opt.val()) === String(walkInId)) return false;
        return true;
    }
    function updateCreditUi() {
        var creditOk = canSellOnCredit();
        $('#sx-pay-credit-note').prop('hidden', !creditOk);
        $('#sx-pay-credit').prop('hidden', !creditOk);
        var $opt = selectedCustomerOption();
        var walkIn = String($opt.data('walk-in')) === '1' || (walkInId && String($opt.val()) === String(walkInId));
        var points = parseFloat($opt.data('points')) || 0;
        if (walkIn) {
            $('#sx-pay-points-hint').text('Select a customer account');
        } else if (points > 0) {
            $('#sx-pay-points-hint').text('Available points: ' + money(points));
        } else {
            $('#sx-pay-points-hint').text('This customer has no points yet');
        }
    }

    function roundMoney(n) {
        return Math.round((n || 0) * 100) / 100;
    }
    function isSplit() {
        return $('#sx-pay-split').is(':checked');
    }
    function syncSingleTenders() {
        var amount = roundMoney(parseFloat($('#sx-pay-received-input').val()) || 0);
        $('#sx-pay-cash, #sx-pay-mpesa, #sx-pay-card').val('0.00');
        if (payMethod === 'mpesa') {
            $('#sx-pay-mpesa').val(money(amount));
        } else if (payMethod === 'card') {
            $('#sx-pay-card').val(money(amount));
        } else {
            $('#sx-pay-cash').val(money(amount));
        }
    }
    function tendered($except) {
        if (!isSplit()) {
            syncSingleTenders();
        }
        var total = 0;
        $('.pos-tender').each(function () {
            if ($except && this === $except[0]) return;
            total += parseFloat($(this).val()) || 0;
        });
        return roundMoney(total);
    }
    function remainingDue($except) {
        return roundMoney(Math.max(0, totals().grand - tendered($except)));
    }
    function refreshPayPanel() {
        var t = totals();
        var received = tendered();
        var change = money(Math.max(0, received - t.grand));
        $('#sx-pay-change').text(change);
        $('#sx-pay-change-split').text(change);
        var labels = { cash: 'CASH RECEIVED', mpesa: 'M-PESA RECEIVED', card: 'CARD RECEIVED' };
        $('#sx-pay-amount-label').text(labels[payMethod] || 'CASH RECEIVED');
        $('#sx-mpesa-extra').prop('hidden', payMethod !== 'mpesa' && !(isSplit() && (parseFloat($('#sx-pay-mpesa').val()) || 0) > 0));
        updateCreditUi();
    }
    function setPayMethod(method) {
        payMethod = method || 'cash';
        $('.pos-method').removeClass('is-active');
        $('.pos-method[data-method="' + payMethod + '"]').addClass('is-active');
        if (!isSplit()) {
            var t = totals();
            $('#sx-pay-received-input').val(money(t.grand));
            lastAutoReceived = t.grand;
        }
        refreshPayPanel();
    }
    function toggleSplitUi() {
        var split = isSplit();
        $('#sx-pay-single').prop('hidden', split);
        $('#sx-pay-split-fields').prop('hidden', !split);
        $('.pos-method').toggleClass('is-split', split);
        if (split) {
            $('.pos-method').addClass('is-active');
            var due = remainingDue($('#sx-pay-cash'));
            if ((parseFloat($('#sx-pay-cash').val()) || 0) <= 0 && due > 0) {
                $('#sx-pay-cash').val(money(due));
            }
        } else {
            setPayMethod(payMethod);
            return;
        }
        refreshPayPanel();
    }
    $('#sx-pos-customer').on('change', updateCreditUi);
    $('.pos-method').on('click', function () {
        if (isSplit()) {
            return;
        }
        setPayMethod($(this).data('method'));
    });
    $('#sx-pay-split').on('change', toggleSplitUi);
    $('#sx-pay-points').on('change', function () {
        if (!this.checked) {
            return;
        }
        if (!canSellOnCredit()) {
            this.checked = false;
            $('#sx-pos-customer').focus();
            Swal.fire({ icon: 'info', title: 'Select a customer account', text: 'Walk-in customers cannot pay with points.' });
            return;
        }
        var points = parseFloat(selectedCustomerOption().data('points')) || 0;
        if (points <= 0) {
            this.checked = false;
            Swal.fire({ icon: 'info', title: 'No points', text: 'This customer has no loyalty points yet.' });
        }
    });
    $('#sx-pay-received-input').on('input', function () {
        lastAutoReceived = -1;
        refreshPayPanel();
    });
    function fillTender($input) {
        if ((parseFloat($input.val()) || 0) <= 0) {
            $input.val(money(remainingDue($input)));
            refreshPayPanel();
        }
    }
    $(document).on('focus click', '#sx-pay-cash, #sx-pay-mpesa, #sx-pay-card', function () {
        fillTender($(this));
    });
    $(document).on('input', '.pos-tender', refreshPayPanel);

    function buildPaymentsFromTenders() {
        if (!isSplit()) {
            syncSingleTenders();
        }
        var t = totals();
        var remaining = t.grand;
        var payments = [];
        var adjusted = false;
        $('.pos-tender').each(function () {
            var amount = roundMoney(parseFloat($(this).val()) || 0);
            if (amount <= 0) return;
            var method = String($(this).data('method') || 'cash');
            var isCash = method === 'cash';
            if (!isCash) {
                if (amount - remaining > 0.009) {
                    amount = remaining;
                    adjusted = true;
                }
                remaining = roundMoney(Math.max(0, remaining - amount));
            } else {
                remaining = roundMoney(Math.max(0, remaining - Math.min(amount, remaining)));
            }
            if (amount <= 0) return;
            var row = { method: method, amount: amount };
            if ($(this).is('#sx-pay-mpesa')) {
                row.reference = $.trim($('#sx-pay-mpesa-code').val());
            }
            payments.push(row);
        });
        return { payments: payments, adjusted: adjusted, grand: t.grand };
    }

    function submitSale(payments, opts) {
        opts = opts || {};
        var t = totals();
        var paySum = payments.reduce(function (sum, row) { return sum + row.amount; }, 0);
        var balanceDue = roundMoney(Math.max(0, t.grand - paySum));
        var creditOk = canSellOnCredit();

        if (balanceDue > 0.009 && !creditOk) {
            Swal.fire({
                icon: 'warning',
                title: 'Full payment required',
                text: 'Walk-in customers cannot buy on credit. Select a customer account or receive full payment.'
            });
            return;
        }

        if (balanceDue > 0.009 && creditOk && !opts.confirmedCredit) {
            var $opt = selectedCustomerOption();
            var limit = parseFloat($opt.data('credit-limit')) || 0;
            var used = parseFloat($opt.data('credit-used')) || 0;
            var nextUsed = roundMoney(used + balanceDue);
            var limitNote = limit > 0
                ? (' Credit used after this sale: ' + money(nextUsed) + ' / limit ' + money(limit) + '.')
                : '';
            Swal.fire({
                icon: 'question',
                title: 'Charge ' + money(balanceDue) + ' to account?',
                text: 'Customer will owe the unpaid balance.' + limitNote,
                showCancelButton: true,
                confirmButtonText: 'Yes, charge account',
                cancelButtonText: 'Cancel'
            }).then(function (result) {
                if (result.isConfirmed) {
                    submitSale(payments, { confirmedCredit: true });
                }
            });
            return;
        }

        if (saleBusy) {
            return;
        }
        saleBusy = true;
        var payload = cartPayload();
        payload.notes = $('#sx-pay-note').val();
        payload.payments = payments;
        payload.payment_amount = Math.min(t.grand, paySum);
        payload.payment_method = payments[0] ? payments[0].method : 'cash';
        payload.on_credit = balanceDue > 0.009 ? 1 : 0;
        if (balanceDue > 0.009) {
            payload.document_type = 'invoice';
        }
        $.ajax({
            url: orderUrl,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            data: payload
        }).done(function (data) {
            if (balanceDue > 0.009) {
                var $opt = selectedCustomerOption();
                var used = parseFloat($opt.data('credit-used')) || 0;
                $opt.attr('data-credit-used', roundMoney(used + balanceDue));
            }
            resetCart();
            if (data.receipt_url) {
                window.open(data.receipt_url, 'sx-pos-receipt', 'width=380,height=720');
            }
            var msg = data.receipt_number || data.invoice_number || data.number;
            if (balanceDue > 0.009) {
                msg = (msg || 'Sale') + ' — balance due ' + money(balanceDue);
            }
            Swal.fire({ icon: 'success', title: 'Sale saved', text: msg, timer: 1800, showConfirmButton: false });
        }).fail(function (xhr) {
            Swal.fire({ icon: 'error', title: 'Order failed', text: (xhr.responseJSON && xhr.responseJSON.message) || 'Could not complete order.' });
        }).always(function () {
            saleBusy = false;
        });
    }

    $('#sx-pay-credit').on('click', function () {
        if (!canSellOnCredit()) {
            Swal.fire({ icon: 'warning', title: 'Select a customer account', text: 'Walk-in cannot be charged on credit.' });
            return;
        }
        $('.pos-tender').val('0.00');
        refreshPayPanel();
        submitSale([], { confirmedCredit: false });
    });

    $('#sx-pos-pay-form').on('submit', function (e) {
        e.preventDefault();
        if (!requireCart()) return;
        var built = buildPaymentsFromTenders();
        var paySum = built.payments.reduce(function (sum, row) { return sum + row.amount; }, 0);
        if (paySum <= 0 && !canSellOnCredit()) {
            Swal.fire({ icon: 'warning', title: 'Enter a payment amount' });
            return;
        }
        if (built.adjusted) {
            Swal.fire({
                icon: 'info',
                title: 'Split payment adjusted',
                text: 'Card/M-Pesa was limited to the unpaid balance. Extra change must be cash only.'
            });
        }
        submitSale(built.payments);
    });

    function openHolds() {
        $('#sx-pos-holds-rows').html('<tr><td colspan="6">Loading...</td></tr>');
        $('#sx-pos-holds-modal').modal('show');
        $.getJSON(holdsUrl).done(function (rows) {
            if (!rows.length) {
                $('#sx-pos-holds-rows').html('<tr><td colspan="6">No held sales.</td></tr>');
                return;
            }
            var html = '';
            rows.forEach(function (row, i) {
                html += '<tr><td>' + (i + 1) + '</td><td>' + escapeHtml(row.number) + '</td><td>' + escapeHtml(row.customer) + '</td><td>' + row.total + '</td><td>' + row.held_at + '</td>';
                html += '<td><button type="button" class="btn btn-primary btn-xs sx-resume-hold" data-url="' + row.url + '">Resume</button></td></tr>';
            });
            $('#sx-pos-holds-rows').html(html);
        });
    }
    $('#sx-pos-holds-top, #sx-pos-holds-link').on('click', function (e) {
        e.preventDefault();
        openHolds();
    });
    function loadHold(url) {
        $.getJSON(url).done(function (data) {
            holdId = data.id;
            discount = data.discount_amount || 0;
            if (data.customer_id) $('#sx-pos-customer').val(data.customer_id);
            if (data.due_date) $('#sx-pos-due').val(data.due_date);
            cart = (data.items || []).map(function (item) {
                return {
                    key: String(item.id) + ':' + String(item.variant_id || 0),
                    id: item.id,
                    variant_id: item.variant_id || 0,
                    name: item.name,
                    price: item.price,
                    selling_price: item.selling_price || item.price,
                    wholesale_price: item.wholesale_price || 0,
                    tax_inclusive: !!item.tax_inclusive,
                    tax_rate: item.tax_rate || 0,
                    tax_amount: item.tax_amount || 0,
                    apply_tax: true,
                    type: 'R',
                    serial: '',
                    quantity: item.qty_sold || 1
                };
            });
            renderCart();
            $('#sx-pos-holds-modal').modal('hide');
        });
    }

    $(document).on('click', '.sx-resume-hold', function () {
        loadHold($(this).data('url'));
    });

    var holdParam = new URLSearchParams(window.location.search).get('hold');
    if (holdParam) {
        loadHold(@json(url('/pos/holds')) + '/' + encodeURIComponent(holdParam));
    }

    $(document).on('keydown', function (e) {
        if (e.key === 'F1') { e.preventDefault(); setPayMethod('cash'); $('#sx-pay-received-input').focus().select(); }
        if (e.key === 'F2') { e.preventDefault(); setPayMethod('mpesa'); $('#sx-pay-received-input').focus().select(); }
        if (e.key === 'F3') { e.preventDefault(); setPayMethod('card'); $('#sx-pay-received-input').focus().select(); }
        if (e.key === 'F10') {
            e.preventDefault();
            $('#sx-pos-search').focus();
        }
        if (e.key === 'F4') {
            e.preventDefault();
            $('#sx-pos-hold').click();
        }
        if (e.key === 'F7') {
            e.preventDefault();
            openCustomer();
        }
    });

    renderCart();
})(jQuery);
</script>
@endpush
