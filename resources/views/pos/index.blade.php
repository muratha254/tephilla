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
                        <option value="{{ $customer->id }}" @if((int) $customer->id === (int) $walkInId) selected @endif>
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
                <input type="text" id="sx-pos-search" class="form-control" placeholder="Item name/Barcode/Itemcode (F10)" autocomplete="off">
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
                        <option value="invoice">Invoice</option>
                        <option value="pos">Receipt</option>
                    </select>
                </div>
            </div>

            <div class="pos-actions">
                <button type="button" class="btn pos-btn-order" id="sx-pos-order"><i class="fa fa-check"></i> ORDER</button>
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
        <div class="pos-cats" id="sx-pos-cats"></div>
        <div class="pos-grid-wrap">
            <div class="pos-grid" id="sx-pos-grid"></div>
            <div class="pos-nav">
                <button type="button" class="pos-nav-btn" id="sx-pos-prev" title="Previous"><i class="fa fa-arrow-left"></i></button>
                <button type="button" class="pos-nav-btn" id="sx-pos-next" title="Next"><i class="fa fa-arrow-right"></i></button>
                <button type="button" class="pos-nav-btn" id="sx-pos-ok" title="Refresh"><i class="fa fa-thumbs-up"></i></button>
                <button type="button" class="pos-nav-btn" id="sx-pos-cust-nav" title="Customer"><i class="fa fa-user"></i></button>
                <button type="button" class="pos-nav-btn pos-nav-refresh" id="sx-pos-refresh"><i class="fa fa-refresh"></i></button>
            </div>
        </div>
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

<div class="modal fade" id="sx-pos-pay-modal" tabindex="-1">
    <div class="modal-dialog pos-pay-dialog">
        <div class="modal-content pos-pay-modal">
            <div class="pos-pay-head">
                <i class="fa fa-money"></i> RECEIVE PAYMENT
            </div>
            <form id="sx-pos-pay-form">
                <div class="pos-pay-body">
                    <div class="pos-pay-fields">
                        <div class="pos-pay-row">
                            <label for="sx-pay-cash">Cash (F1)</label>
                            <input type="number" step="0.01" min="0" id="sx-pay-cash" class="form-control pos-tender" data-method="cash" value="0.00">
                        </div>
                        <div class="pos-pay-row pos-pay-mpesa">
                            <label for="sx-pay-mpesa">Mpesa (F2)</label>
                            <div class="pos-pay-stack">
                                <input type="number" step="0.01" min="0" id="sx-pay-mpesa" class="form-control pos-tender" data-method="mpesa" value="0.00">
                                <div class="pos-pay-extra" id="sx-mpesa-extra" hidden>
                                    <label for="sx-pay-mpesa-code">Trans Code</label>
                                    <input type="text" id="sx-pay-mpesa-code" class="form-control" placeholder="Transaction Code">
                                </div>
                            </div>
                        </div>
                        <div class="pos-pay-row pos-pay-bank">
                            <label for="sx-pay-bank">Bank (F3)</label>
                            <div class="pos-pay-stack">
                                <input type="number" step="0.01" min="0" id="sx-pay-bank" class="form-control pos-tender" data-method="bank_transfer" value="0.00">
                                <div class="pos-pay-extra" id="sx-bank-extra" hidden>
                                    <label for="sx-pay-bank-name">Bank Name</label>
                                    <input type="text" id="sx-pay-bank-name" class="form-control" placeholder="Enter bank name">
                                    <label for="sx-pay-bank-ref">Reference</label>
                                    <input type="text" id="sx-pay-bank-ref" class="form-control" placeholder="Ref No">
                                </div>
                            </div>
                        </div>
                        <div class="pos-pay-row">
                            <label for="sx-pay-comp">COMPLEMENTARY</label>
                            <input type="number" step="0.01" min="0" id="sx-pay-comp" class="form-control pos-tender" data-method="complementary" placeholder="COMPLEMENTARY">
                        </div>
                        <div class="pos-pay-row">
                            <label for="sx-pay-advance">Advance pay</label>
                            <input type="number" step="0.01" min="0" id="sx-pay-advance" class="form-control pos-tender" data-method="advance" value="0.00">
                        </div>
                        <div class="pos-pay-row">
                            <label for="sx-pay-note">SALES NOTE</label>
                            <textarea id="sx-pay-note" class="form-control" rows="2" placeholder="SALES NOTE"></textarea>
                        </div>
                        <div class="pos-pay-row">
                            <label for="sx-pay-service">EAT IN / TAKE AWAY</label>
                            <select id="sx-pay-service" class="form-control">
                                <option value="">Select</option>
                                <option value="eat_in">Eat In</option>
                                <option value="take_away">Take Away</option>
                            </select>
                        </div>
                    </div>
                    <div class="pos-pay-summary">
                        <div><span>Total items:</span><strong id="sx-pay-items">0</strong></div>
                        <div><span>Total:</span><strong id="sx-pay-exclusive">0.00</strong></div>
                        <div><span>Discount (-):</span><strong id="sx-pay-discount">0.00</strong></div>
                        <div><span>Tax (+):</span><strong id="sx-pay-tax">0.00</strong></div>
                        <div class="is-red"><span>Cur payable amt:</span><strong id="sx-pay-payable">0.00</strong></div>
                        <div class="is-red"><span>Grand To payable:</span><strong id="sx-pay-grand-due">0.00</strong></div>
                        <div><span>Total Received:</span><strong id="sx-pay-received">0.00</strong></div>
                        <div><span>Balance:</span><strong id="sx-pay-balance">0.00</strong></div>
                        <div class="is-orange"><span>Change Return:</span><strong id="sx-pay-change">0.00</strong></div>
                    </div>
                </div>
                <div class="pos-pay-actions">
                    <button type="button" class="pos-pay-close" data-dismiss="modal"><i class="fa fa-times"></i> Close</button>
                    <button type="submit" class="pos-pay-complete"><i class="fa fa-check"></i> Complete</button>
                    <select id="sx-pay-logout" class="pos-pay-logout">
                        <option value="stay">Don't Logout</option>
                        <option value="logout">Logout</option>
                    </select>
                </div>
            </form>
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
    var catalogUrl = @json(route('pos.catalog'));
    var searchUrl = @json(route('pos.search'));
    var customerUrl = @json(route('pos.customers.store'));
    var holdUrl = @json(route('pos.hold'));
    var orderUrl = @json(route('pos.order'));
    var holdsUrl = @json(route('pos.holds'));
    var token = $('meta[name="csrf-token"]').attr('content');
    var cart = [];
    var discount = 0;
    var holdId = null;
    var categoryId = null;
    var page = 1;
    var pages = 1;
    var searchTimer = null;

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
    }

    function addProduct(item, qty) {
        qty = qty || 1;
        var existing = cart.filter(function (row) { return row.id === item.id; })[0];
        if (existing) {
            existing.quantity += qty;
        } else {
            cart.push({
                id: item.id,
                name: item.name,
                price: item.price,
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
            items: cart.map(function (row) {
                return { product_id: row.id, quantity: row.quantity };
            })
        };
    }

    function resetCart() {
        cart = [];
        discount = 0;
        holdId = null;
        renderCart();
    }

    function loadCatalog() {
        $.getJSON(catalogUrl, { category_id: categoryId || '', page: page }).done(function (data) {
            pages = data.pages || 1;
            var cats = '<button type="button" class="pos-cat' + (!categoryId ? ' is-active' : '') + '" data-id="">All</button>';
            (data.categories || []).forEach(function (cat) {
                cats += '<button type="button" class="pos-cat' + (String(categoryId) === String(cat.id) ? ' is-active' : '') + '" data-id="' + cat.id + '">' + escapeHtml(cat.name) + '</button>';
            });
            $('#sx-pos-cats').html(cats);
            var tiles = '';
            (data.products || []).forEach(function (item) {
                var empty = !(item.qty > 0);
                tiles += '<button type="button" class="pos-tile' + (empty ? ' is-empty' : '') + '" data-item=\'' + JSON.stringify(item).replace(/'/g, '&#39;') + '\'>';
                tiles += '<div class="pos-tile-meta">Qty: ' + item.qty + ' Price: ' + Number(item.price || 0).toFixed(0) + '</div>';
                tiles += '<div class="pos-tile-icon">';
                if (item.image) {
                    tiles += '<img src="' + item.image + '" alt="">';
                } else {
                    tiles += '<span class="pos-no-photo"><i class="fa fa-camera"></i></span>';
                }
                tiles += '</div><div class="pos-tile-name">' + escapeHtml(item.name) + '</div></button>';
            });
            $('#sx-pos-grid').html(tiles || '<div class="pos-empty">No items</div>');
        });
    }

    $(document).on('click', '.pos-cat', function () {
        categoryId = $(this).data('id') || null;
        page = 1;
        loadCatalog();
    });
    $('#sx-pos-prev').on('click', function () {
        if (page > 1) { page -= 1; loadCatalog(); }
    });
    $('#sx-pos-next').on('click', function () {
        if (page < pages) { page += 1; loadCatalog(); }
    });
    $('#sx-pos-refresh, #sx-pos-ok').on('click', loadCatalog);

    $(document).on('click', '.pos-tile', function () {
        var item = $(this).data('item');
        if (typeof item === 'string') {
            item = JSON.parse(item.replace(/&#39;/g, "'"));
        }
        addProduct(item, 1);
    });

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

    $(document).on('click', '#sx-pos-dashboard', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        window.location.href = this.getAttribute('href') || @json(route('dashboard', [], false));
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
    $('#sx-pos-add-cust, #sx-pos-cust, #sx-pos-cust-nav').on('click', openCustomer);
    $('#sx-pos-customer-form').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: customerUrl,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            data: $(this).serialize()
        }).done(function (data) {
            $('#sx-pos-customer').append($('<option>', { value: data.id, text: data.name + (data.phone ? ' - ' + data.phone : ''), selected: true }));
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

    function tendered() {
        var total = 0;
        $('.pos-tender').each(function () {
            total += parseFloat($(this).val()) || 0;
        });
        return total;
    }
    function refreshPayPanel() {
        var t = totals();
        var received = tendered();
        $('#sx-pay-items').text(t.qty);
        $('#sx-pay-exclusive').text(money(t.exclusive));
        $('#sx-pay-discount').text(money(t.discount));
        $('#sx-pay-tax').text(money(t.tax));
        $('#sx-pay-payable').text(money(t.grand));
        $('#sx-pay-grand-due').text(money(0));
        $('#sx-pay-received').text(money(received));
        $('#sx-pay-balance').text(money(Math.max(0, t.grand - received)));
        $('#sx-pay-change').text(money(Math.max(0, received - t.grand)));
    }
    $('#sx-pos-order').on('click', function () {
        if (!requireCart()) return;
        $('.pos-tender').val('0.00');
        $('#sx-pay-comp').val('');
        $('#sx-pay-note').val('');
        $('#sx-pay-service').val('');
        $('#sx-pay-mpesa-code, #sx-pay-bank-name, #sx-pay-bank-ref').val('');
        $('#sx-mpesa-extra, #sx-bank-extra').prop('hidden', true);
        refreshPayPanel();
        $('#sx-pos-pay-modal').modal('show');
    });
    function fillTender($input) {
        if ((parseFloat($input.val()) || 0) <= 0) {
            $input.val(money(totals().grand));
            refreshPayPanel();
        }
        if ($input.is('#sx-pay-mpesa')) {
            $('#sx-mpesa-extra').prop('hidden', false);
        }
        if ($input.is('#sx-pay-bank')) {
            $('#sx-bank-extra').prop('hidden', false);
        }
    }
    $(document).on('focus click', '#sx-pay-cash, #sx-pay-mpesa, #sx-pay-bank', function () {
        fillTender($(this));
    });
    $(document).on('input', '.pos-tender', refreshPayPanel);
    $('#sx-pos-pay-form').on('submit', function (e) {
        e.preventDefault();
        var t = totals();
        var received = tendered();
        if (received <= 0) {
            Swal.fire({ icon: 'warning', title: 'Enter a payment amount' });
            return;
        }
        var payload = cartPayload();
        payload.notes = $('#sx-pay-note').val();
        payload.service_type = $('#sx-pay-service').val();
        payload.payments = [];
        $('.pos-tender').each(function () {
            var amount = parseFloat($(this).val()) || 0;
            if (amount <= 0) return;
            var row = { method: $(this).data('method'), amount: amount };
            if ($(this).is('#sx-pay-mpesa')) {
                row.reference = $.trim($('#sx-pay-mpesa-code').val());
            }
            if ($(this).is('#sx-pay-bank')) {
                row.bank_name = $.trim($('#sx-pay-bank-name').val());
                row.reference = $.trim($('#sx-pay-bank-ref').val());
            }
            payload.payments.push(row);
        });
        payload.payment_amount = Math.min(t.grand, received);
        payload.payment_method = payload.payments[0] ? payload.payments[0].method : 'cash';
        $.ajax({
            url: orderUrl,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            data: payload
        }).done(function (data) {
            $('#sx-pos-pay-modal').modal('hide');
            resetCart();
            loadCatalog();
            if (data.receipt_url) {
                window.open(data.receipt_url, 'sx-pos-receipt', 'width=380,height=720');
            }
            if ($('#sx-pay-logout').val() === 'logout') {
                $('.pos-logout-form').first().trigger('submit');
                return;
            }
            Swal.fire({ icon: 'success', title: 'Sale saved', text: data.receipt_number || data.invoice_number || data.number, timer: 1600, showConfirmButton: false });
        }).fail(function (xhr) {
            Swal.fire({ icon: 'error', title: 'Order failed', text: (xhr.responseJSON && xhr.responseJSON.message) || 'Could not complete order.' });
        });
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
                    id: item.id,
                    name: item.name,
                    price: item.price,
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
        if ($('#sx-pos-pay-modal').is(':visible')) {
            if (e.key === 'F1') { e.preventDefault(); $('#sx-pay-cash').focus().select(); }
            if (e.key === 'F2') { e.preventDefault(); $('#sx-pay-mpesa').focus().select(); }
            if (e.key === 'F3') { e.preventDefault(); $('#sx-pay-bank').focus().select(); }
            return;
        }
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
    loadCatalog();
})(jQuery);
</script>
@endpush
