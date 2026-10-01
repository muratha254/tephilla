@extends('layouts.fleet')

@section('title', !empty($isEdit) ? 'Edit Purchase' : (($purchaseType ?? '') === 'lpo' ? 'New LPO' : 'Direct Purchase'))

@section('content')
@php
    $isEdit = !empty($isEdit) && !empty($purchase);
    $purchase = $purchase ?? null;
@endphp
@include('layouts.partials.page-header', [
    'title' => $isEdit ? 'Edit Purchase Order' : (($purchaseType ?? '') === 'lpo' ? 'New LPO' : 'Direct Purchase'),
    'subtitle' => $isEdit ? 'Update Purchase Order ' . $purchase->number : (($purchaseType ?? '') === 'lpo' ? 'Order from a supplier. Stock is added when the order is received.' : 'Receive stock from a supplier now.'),
    'backUrl' => route('purchases.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Purchase List', 'url' => route('purchases.index')],
        ['label' => $isEdit ? 'Edit Purchase' : (($purchaseType ?? '') === 'lpo' ? 'New LPO' : 'Direct Purchase')],
    ],
])

<form method="post"
      action="{{ $isEdit ? route('purchases.update', $purchase) : route('purchases.store') }}"
      enctype="multipart/form-data"
      id="sx-purchase-form"
      class="sx-purchase-form">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="sx-purchase-meta">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="sx-req">Supplier Name*</label>
                    <div class="input-group">
                        <select name="supplier_id" id="sx-po-supplier" class="form-control" required>
                            <option value="">-Select-</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @if((string) old('supplier_id', optional($purchase)->supplier_id) === (string) $supplier->id) selected @endif>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        @if($canCreateSupplier)
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-info" id="sx-po-add-supplier" title="Add supplier"><i class="fa fa-user-plus"></i></button>
                            </span>
                        @endif
                    </div>
                </div>
                <div class="form-group">
                    <label>Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="{{ old('due_date', optional(optional($purchase)->due_date)->format('Y-m-d') ?: now()->toDateString()) }}">
                </div>
                <div class="form-group">
                    <label>Reference No.</label>
                    <input type="text" name="reference_no" class="form-control" placeholder="Reference code" value="{{ old('reference_no', optional($purchase)->reference_no) }}">
                </div>
                <div class="form-group">
                    <label>CU No</label>
                    <input type="text" name="cu_number" class="form-control" placeholder="Control Unit No(CU)" value="{{ old('cu_number', optional($purchase)->cu_number) }}">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="sx-req">Purchase Date *</label>
                    <input type="date" name="order_date" class="form-control" value="{{ old('order_date', optional(optional($purchase)->order_date)->format('Y-m-d') ?: now()->toDateString()) }}" required>
                </div>
                <div class="form-group">
                    <label class="sx-req">Status *</label>
                    <select name="status" class="form-control" required>
                        <option value="">-Select-</option>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}"{{ (old('status', optional($purchase)->status ?: ($defaultStatus ?? '')) === $value) ? ' selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Purchase File</label>
                    <input type="file" name="attachment" class="form-control">
                    @if($isEdit && $purchase->attachment_path)
                        <p class="help-block" style="margin-top:6px;">
                            Current file:
                            <a href="{{ asset('storage/' . ltrim($purchase->attachment_path, '/')) }}" target="_blank">View attachment</a>
                        </p>
                    @endif
                </div>
                @if($isEdit)
                    <div class="form-group">
                        <label>Purchase No</label>
                        <input type="text" class="form-control" value="{{ $purchase->number }}" readonly>
                    </div>
                @endif
            </div>
        </div>

        <button type="button" class="btn sx-link-add" id="sx-po-add-expense"><i class="fa fa-plus"></i> Add Expense</button>
        <div id="sx-po-expenses"></div>
    </div>

    <div class="sx-purchase-search-wrap">
        <div class="sx-label-search">
            <span class="sx-label-search-icon"><i class="fa fa-th-large"></i></span>
            <input type="text" id="sx-po-q" class="form-control" placeholder="Item name/Barcode/item code" autocomplete="off">
            <button type="button" class="btn btn-default sx-po-search-plus" id="sx-po-search-plus" title="Add item"><i class="fa fa-plus"></i></button>
            <div id="sx-po-results" class="sx-label-results" hidden></div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered sx-gold-table sx-po-table">
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Quantity</th>
                    <th>Cost price</th>
                    <th>Tax %</th>
                    <th>Tax Amt</th>
                    <th>Discount(%)</th>
                    <th>Unit Cost</th>
                    <th>Total Amount</th>
                    <th>Unit Sales</th>
                    <th>Stock</th>
                    <th>Expiry</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="sx-po-rows"></tbody>
        </table>
    </div>

    <div class="sx-po-totals">
        <div class="sx-po-totals-left">
            <div class="sx-po-total-row">
                <span>Total Quantities</span>
                <strong id="sx-po-total-qty">0</strong>
            </div>
            <div class="form-group">
                <label>Note</label>
                <textarea name="notes" class="form-control" rows="3">{{ old('notes', optional($purchase)->notes) }}</textarea>
            </div>
        </div>
        <div class="sx-po-totals-right">
            <div class="sx-po-total-row"><span>Subtotal</span><strong>Ksh <span id="sx-po-subtotal">0.00</span></strong></div>
            <div class="sx-po-total-row"><span>Round Off</span><input type="number" step="0.01" name="round_off" id="sx-po-round" class="form-control input-sm" value="{{ old('round_off', $isEdit ? number_format((float) $purchase->round_off, 2, '.', '') : '0.00') }}"></div>
            <div class="sx-po-total-row"><span>Grand Total</span><strong>Ksh <span id="sx-po-grand">0.00</span></strong></div>
            <div class="sx-po-total-row"><span>Stock Value</span><strong>Ksh <span id="sx-po-stock-value">0.00</span></strong></div>
        </div>
    </div>

    @if(!$isEdit)
    <div class="sx-po-pay">
        <h4>Make Payment:</h4>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>Amount</label>
                    <input type="number" step="0.01" min="0" name="payment_amount" id="sx-po-pay-amount" class="form-control" placeholder="Amount To pay" value="{{ old('payment_amount') }}">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Payment Type</label>
                    <select name="payment_method" class="form-control">
                        <option value="">-Select-</option>
                        @foreach($paymentMethods as $value => $label)
                            <option value="{{ $value }}" @if(old('payment_method') === $value) selected @endif>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Voucher No</label>
                    <input type="text" name="payment_reference" class="form-control" placeholder="Voucher No" value="{{ old('payment_reference') }}">
                </div>
            </div>
        </div>
        <div class="form-group">
            <label>Payment Note</label>
            <textarea name="payment_notes" class="form-control" rows="2">{{ old('payment_notes') }}</textarea>
        </div>
    </div>
    @endif

    <div class="sx-label-actions">
        <button type="submit" class="btn sx-btn-preview">{{ $isEdit ? 'Update' : 'Save' }}</button>
        <a href="{{ route('purchases.index') }}" class="btn sx-btn-label-close">Close</a>
    </div>
</form>

@if($canCreateSupplier)
@php
    $kenyaCounties = [
        'Baringo', 'Bomet', 'Bungoma', 'Busia', 'Elgeyo-Marakwet', 'Embu', 'Garissa', 'Homa Bay',
        'Isiolo', 'Kajiado', 'Kakamega', 'Kericho', 'Kiambu', 'Kilifi', 'Kirinyaga', 'Kisii',
        'Kisumu', 'Kitui', 'Kwale', 'Laikipia', 'Lamu', 'Machakos', 'Makueni', 'Mandera',
        'Marsabit', 'Meru', 'Migori', 'Mombasa', 'Murang\'a', 'Nairobi', 'Nakuru', 'Nandi',
        'Narok', 'Nyamira', 'Nyandarua', 'Nyeri', 'Samburu', 'Siaya', 'Taita-Taveta', 'Tana River',
        'Tharaka-Nithi', 'Trans Nzoia', 'Turkana', 'Uasin Gishu', 'Vihiga', 'Wajir', 'West Pokot',
    ];
@endphp
<div class="modal fade" id="sx-supplier-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-user"></i> Add Supplier</h4>
            </div>
            <div class="modal-body">
                <form id="sx-supplier-form">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="sx-req">Supplier Name*</label>
                                <input type="text" name="name" class="form-control" placeholder="Enter Name" required>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" placeholder="Email Address">
                            </div>
                            <div class="form-group">
                                <label>State</label>
                                <select name="state" class="form-control">
                                    <option value="">-Select-</option>
                                    @foreach($kenyaCounties as $county)
                                        <option value="{{ $county }}">{{ $county }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Mobile</label>
                                <input type="text" name="mobile" class="form-control" placeholder="Mobile Number">
                            </div>
                            <div class="form-group">
                                <label>KRA PIN</label>
                                <input type="text" name="tax_number" class="form-control" placeholder="KRA PIN">
                            </div>
                            <div class="form-group">
                                <label>Postcode</label>
                                <input type="text" name="postcode" class="form-control" placeholder="Postcode">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" name="phone" class="form-control" placeholder="Phone Number">
                            </div>
                            <div class="form-group">
                                <label>Country</label>
                                <select name="country" class="form-control">
                                    <option value="Kenya" selected>Kenya</option>
                                    <option value="Uganda">Uganda</option>
                                    <option value="Tanzania">Tanzania</option>
                                    <option value="Rwanda">Rwanda</option>
                                    <option value="Ethiopia">Ethiopia</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Address</label>
                                <textarea name="address" class="form-control" rows="3" placeholder="Address"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="sx-form-actions">
                        <button type="button" class="btn btn-warning" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function ($) {
    var searchUrl = @json(route('products.search'));
    var supplierUrl = @json(route('suppliers.store'));
    var token = $('meta[name="csrf-token"]').attr('content');
    var timer = null;
    var nextIndex = 0;
    var lastItem = null;

    function money(n) {
        n = parseFloat(n);
        if (isNaN(n)) n = 0;
        return n.toFixed(2);
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : value).html();
    }

    function lineCalc(row) {
        var qty = parseFloat(row.find('.sx-po-qty').val()) || 0;
        var cost = parseFloat(row.find('.sx-po-cost').val()) || 0;
        var tax = parseFloat(row.find('.sx-po-tax').val()) || 0;
        var disc = parseFloat(row.find('.sx-po-disc').val()) || 0;
        var base = qty * cost;
        var discountAmt = base * (disc / 100);
        var taxable = base - discountAmt;
        var taxAmt = taxable * (tax / 100);
        var total = taxable + taxAmt;
        var unit = qty > 0 ? total / qty : 0;
        row.find('.sx-po-taxamt').val(money(taxAmt));
        row.find('.sx-po-unitcost').val(money(unit));
        row.find('.sx-po-linetotal').val(money(total));
        return { qty: qty, cost: cost, total: total, stockValue: qty * cost };
    }

    function recalc() {
        var qty = 0, sub = 0, stock = 0, exp = 0;
        $('#sx-po-rows tr').each(function () {
            var line = lineCalc($(this));
            qty += line.qty;
            sub += line.total;
            stock += line.stockValue;
        });
        $('#sx-po-expenses .sx-po-exp-amt').each(function () {
            exp += parseFloat(this.value) || 0;
        });
        var round = parseFloat($('#sx-po-round').val()) || 0;
        $('#sx-po-total-qty').text(qty);
        $('#sx-po-subtotal').text(money(sub));
        $('#sx-po-grand').text(money(sub + exp + round));
        $('#sx-po-stock-value').text(money(stock));
    }

    function addItem(item) {
        var i = nextIndex++;
        var qty = item.quantity != null ? item.quantity : 1;
        var disc = item.discount_percent != null ? item.discount_percent : 0;
        var row = $(
            '<tr>' +
                '<td><input type="hidden" name="items[' + i + '][product_id]" value="' + item.id + '">' +
                    '<input type="hidden" name="items[' + i + '][product_variant_id]" value="' + (item.variant_id || 0) + '">' +
                    '<input type="text" class="form-control" value="' + escapeHtml(item.name) + '" readonly></td>' +
                '<td><input type="number" step="0.01" min="0.01" name="items[' + i + '][quantity]" class="form-control sx-po-qty" value="' + qty + '" required></td>' +
                '<td><input type="number" step="0.01" min="0" name="items[' + i + '][unit_cost]" class="form-control sx-po-cost" value="' + money(item.purchase_price) + '" required></td>' +
                '<td><input type="number" step="0.01" min="0" name="items[' + i + '][tax_rate]" class="form-control sx-po-tax" value="' + money(item.tax_rate) + '"></td>' +
                '<td><input type="text" class="form-control sx-po-taxamt" readonly tabindex="-1"></td>' +
                '<td><input type="number" step="0.01" min="0" name="items[' + i + '][discount_percent]" class="form-control sx-po-disc" value="' + money(disc) + '"></td>' +
                '<td><input type="text" class="form-control sx-po-unitcost" readonly tabindex="-1"></td>' +
                '<td><input type="text" class="form-control sx-po-linetotal" readonly tabindex="-1"></td>' +
                '<td><input type="number" step="0.01" min="0" name="items[' + i + '][selling_price]" class="form-control" value="' + money(item.selling_price) + '"></td>' +
                '<td><input type="text" class="form-control" value="' + money(item.stock) + '" readonly></td>' +
                '<td><input type="date" name="items[' + i + '][expiry_date]" class="form-control" value="' + (item.expiry || '') + '"></td>' +
                '<td class="text-center"><button type="button" class="btn btn-danger btn-xs sx-po-remove"><i class="fa fa-minus"></i></button></td>' +
            '</tr>'
        );
        $('#sx-po-rows').append(row);
        recalc();
        $('#sx-po-q').val('').focus();
        hideResults();
        lastItem = null;
    }

    function hideResults() {
        $('#sx-po-results').empty().attr('hidden', true);
    }

    function showResults(items) {
        var box = $('#sx-po-results').empty();
        if (!items.length) {
            box.append('<div class="sx-label-empty">No matching items</div>').removeAttr('hidden');
            return;
        }
        items.forEach(function (item) {
            box.append(
                '<button type="button" class="sx-label-result" data-item=\'' + JSON.stringify(item).replace(/'/g, '&#39;') + '\'>' +
                    '<strong>' + escapeHtml(item.name) + '</strong>' +
                    '<span>' + escapeHtml(item.sku || item.barcode || '') + '</span>' +
                '</button>'
            );
        });
        box.removeAttr('hidden');
    }

    $('#sx-po-q').on('input', function () {
        var q = $.trim(this.value);
        lastItem = null;
        clearTimeout(timer);
        if (!q) { hideResults(); return; }
        timer = setTimeout(function () {
            $.getJSON(searchUrl, { q: q }).done(function (items) {
                lastItem = items[0] || null;
                showResults(items);
            }).fail(hideResults);
        }, 250);
    });

    function chooseColour(item, done) {
        if (!item.variants || !item.variants.length || item.variant_id) {
            done(item);
            return;
        }
        var options = {};
        item.variants.forEach(function (variant) {
            options[String(variant.id)] = variant.name + ' — ' + variant.qty + (item.unit ? ' ' + item.unit : '');
        });
        if (!window.Swal) {
            alert('Select a colour for ' + item.name + '.');
            return;
        }
        Swal.fire({
            title: 'Select colour',
            input: 'select',
            inputOptions: options,
            inputPlaceholder: 'Choose a colour',
            showCancelButton: true
        }).then(function (result) {
            if (!result.value) return;
            var variant = null;
            item.variants.forEach(function (row) {
                if (String(row.id) === String(result.value)) variant = row;
            });
            if (!variant) return;
            item.variant_id = variant.id;
            item.name = item.name + ' (' + variant.name + ')';
            item.stock = variant.qty;
            done(item);
        });
    }

    $(document).on('click', '#sx-po-results .sx-label-result', function () {
        var item = JSON.parse($(this).attr('data-item').replace(/&#39;/g, "'"));
        chooseColour(item, addItem);
    });

    $('#sx-po-search-plus').on('click', function () {
        if (lastItem) chooseColour(lastItem, addItem);
    });

    $('#sx-po-rows').on('input', '.sx-po-qty, .sx-po-cost, .sx-po-tax, .sx-po-disc', recalc);
    $('#sx-po-rows').on('click', '.sx-po-remove', function () {
        $(this).closest('tr').remove();
        recalc();
    });
    $('#sx-po-round, #sx-po-expenses').on('input', 'input', recalc);

    var expIndex = 0;
    $('#sx-po-add-expense').on('click', function () {
        var i = expIndex++;
        $('#sx-po-expenses').append(
            '<div class="sx-po-exp-row">' +
                '<input type="text" name="expenses[' + i + '][description]" class="form-control" placeholder="Expense">' +
                '<input type="number" step="0.01" min="0" name="expenses[' + i + '][amount]" class="form-control sx-po-exp-amt" placeholder="Amount">' +
                '<button type="button" class="btn btn-danger btn-xs sx-po-exp-remove"><i class="fa fa-minus"></i></button>' +
            '</div>'
        );
    });
    $('#sx-po-expenses').on('click', '.sx-po-exp-remove', function () {
        $(this).closest('.sx-po-exp-row').remove();
        recalc();
    });

    $('#sx-po-add-supplier').on('click', function () {
        $('#sx-supplier-form')[0].reset();
        $('#sx-supplier-form select[name="country"]').val('Kenya');
        $('#sx-supplier-modal').modal('show');
    });

    $('#sx-supplier-form').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: supplierUrl,
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            data: $(this).serialize()
        }).done(function (data) {
            $('#sx-po-supplier').append($('<option>', { value: data.id, text: data.name, selected: true }));
            $('#sx-supplier-modal').modal('hide');
        }).fail(function (xhr) {
            var msg = 'Could not save supplier.';
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                msg = Object.values(xhr.responseJSON.errors)[0][0] || msg;
            }
            if (window.Swal) {
                Swal.fire({ icon: 'error', title: 'Save failed', text: msg });
            } else {
                alert(msg);
            }
        });
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.sx-purchase-search-wrap').length) hideResults();
    });

    $('#sx-purchase-form').on('submit', function (e) {
        if (!$('#sx-po-rows tr').length) {
            e.preventDefault();
            if (window.Swal) {
                Swal.fire({ icon: 'warning', title: 'Add an item', text: 'Search and add at least one item.' });
            } else {
                alert('Search and add at least one item.');
            }
        }
    });

    var existingItems = @json($existingItems ?? []);
    existingItems.forEach(function (item) {
        addItem(item);
    });

    var existingExpenses = @json($existingExpenses ?? []);
    existingExpenses.forEach(function (expense) {
        var i = expIndex++;
        $('#sx-po-expenses').append(
            '<div class="sx-po-exp-row">' +
                '<input type="text" name="expenses[' + i + '][description]" class="form-control" placeholder="Expense" value="' + escapeHtml(expense.description || '') + '">' +
                '<input type="number" step="0.01" min="0" name="expenses[' + i + '][amount]" class="form-control sx-po-exp-amt" placeholder="Amount" value="' + money(expense.amount || 0) + '">' +
                '<button type="button" class="btn btn-danger btn-xs sx-po-exp-remove"><i class="fa fa-minus"></i></button>' +
            '</div>'
        );
    });
    if (existingItems.length || existingExpenses.length) {
        recalc();
    }
})(jQuery);
</script>
@endpush
