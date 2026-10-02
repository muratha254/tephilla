@extends('layouts.fleet')

@section('title', 'New Invoice')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Invoice',
    'subtitle' => 'Create Invoice',
    'backUrl' => route('sales.invoices'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Invoices', 'url' => route('sales.invoices')],
        ['label' => 'New Invoice'],
    ],
])

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<form method="post" action="{{ route('sales.invoices.store') }}" id="sx-invoice-form" class="sx-purchase-form">
    @csrf
    <div class="sx-purchase-meta">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="sx-req">Customer *</label>
                    <div class="sx-inv-customer-row">
                        <select name="customer_id" id="sx-inv-customer" class="form-control" required>
                            <option value="">-Select-</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @if((string) old('customer_id') === (string) $customer->id) selected @endif>
                                    {{ $customer->name }}{{ $customer->is_walk_in ? ' (Walk-in)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @if($canAddCustomer)
                            <button type="button" class="btn sx-btn-aqua" id="sx-inv-add-customer"><i class="fa fa-user-plus"></i> Add Customer</button>
                        @endif
                    </div>
                </div>
                <div class="form-group">
                    <label>Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="{{ old('due_date', $dueDate) }}">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="sx-req">Invoice Date *</label>
                    <input type="date" name="invoice_date" class="form-control" value="{{ old('invoice_date', $invoiceDate) }}" required>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    @php
        $oldItems = old('items');
        if (! is_array($oldItems) || count($oldItems) === 0) {
            $oldItems = [[
                'product_key' => '',
                'quantity' => 1,
                'unit_price' => '',
                'tax_rate' => '',
                'discount_type' => 'amount',
                'discount_value' => 0,
            ]];
        }
    @endphp

    <div class="table-responsive">
        <table class="table table-bordered sx-gold-table sx-po-table" id="sx-inv-table">
            <thead>
                <tr>
                    <th style="min-width:220px;">Product</th>
                    <th style="width:90px;">Qty</th>
                    <th style="width:120px;">Unit Price</th>
                    <th style="width:90px;">Tax %</th>
                    <th style="width:160px;">Discount</th>
                    <th style="width:120px;">Line Total</th>
                    <th style="width:60px;">Action</th>
                </tr>
            </thead>
            <tbody id="sx-inv-rows">
                @foreach($oldItems as $index => $item)
                    <tr class="sx-inv-row">
                        <td>
                            <select name="items[{{ $index }}][product_key]" class="form-control sx-inv-product" required>
                                <option value="">-Select-</option>
                                @foreach($productOptions as $option)
                                    <option value="{{ $option['key'] }}"
                                        data-price="{{ $option['price'] }}"
                                        data-qty="{{ $option['quantity'] }}"
                                        data-tax="{{ $option['tax_rate'] }}"
                                        @if((string) ($item['product_key'] ?? '') === (string) $option['key']) selected @endif>
                                        {{ $option['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="sx-inv-stock"></small>
                        </td>
                        <td>
                            <input type="number" step="0.0001" min="0.0001" name="items[{{ $index }}][quantity]" class="form-control sx-inv-qty" value="{{ $item['quantity'] ?? 1 }}" required>
                        </td>
                        <td>
                            <input type="number" step="0.01" min="0" name="items[{{ $index }}][unit_price]" class="form-control sx-inv-price" value="{{ $item['unit_price'] ?? '' }}" required>
                        </td>
                        <td>
                            <input type="number" step="0.01" min="0" name="items[{{ $index }}][tax_rate]" class="form-control sx-inv-tax" value="{{ $item['tax_rate'] ?? '' }}">
                        </td>
                        <td>
                            <div class="sx-inv-discount">
                                <select name="items[{{ $index }}][discount_type]" class="form-control sx-inv-discount-type">
                                    <option value="amount" @if(($item['discount_type'] ?? 'amount') === 'amount') selected @endif>Ksh</option>
                                    <option value="percent" @if(($item['discount_type'] ?? '') === 'percent') selected @endif>%</option>
                                </select>
                                <input type="number" step="0.01" min="0" name="items[{{ $index }}][discount_value]" class="form-control sx-inv-discount-value" value="{{ $item['discount_value'] ?? 0 }}">
                            </div>
                        </td>
                        <td class="sx-inv-line-total text-right">0.00</td>
                        <td>
                            <button type="button" class="btn btn-danger btn-xs sx-inv-remove" title="Remove"><i class="fa fa-trash"></i></button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <button type="button" class="btn sx-link-add" id="sx-inv-add-row"><i class="fa fa-plus"></i> Add Line</button>

    <div class="sx-po-totals">
        <div class="sx-po-totals-left"></div>
        <div class="sx-po-totals-right">
            <div class="sx-po-total-row"><span>Subtotal</span><strong>Ksh <span id="sx-inv-subtotal">0.00</span></strong></div>
            <div class="sx-po-total-row">
                <span>Discount</span>
                <div class="sx-inv-discount">
                    <select name="discount_type" id="sx-inv-header-type" class="form-control">
                        <option value="amount" @if(old('discount_type', 'amount') === 'amount') selected @endif>Ksh</option>
                        <option value="percent" @if(old('discount_type') === 'percent') selected @endif>%</option>
                    </select>
                    <input type="number" step="0.01" min="0" name="discount_value" id="sx-inv-header-discount" class="form-control input-sm" value="{{ old('discount_value', '0.00') }}">
                </div>
            </div>
            <div class="sx-po-total-row"><span>Tax</span><strong>Ksh <span id="sx-inv-tax">0.00</span></strong></div>
            <div class="sx-po-total-row"><span>Grand Total</span><strong>Ksh <span id="sx-inv-grand">0.00</span></strong></div>
        </div>
    </div>

    <div class="sx-label-actions">
        <button type="submit" class="btn sx-btn-preview">Save</button>
        <a href="{{ route('sales.invoices') }}" class="btn sx-btn-label-close">Close</a>
    </div>

    <template id="sx-inv-row-template">
        <tr class="sx-inv-row">
            <td>
                <select name="items[__INDEX__][product_key]" class="form-control sx-inv-product" required>
                    <option value="">-Select-</option>
                    @foreach($productOptions as $option)
                        <option value="{{ $option['key'] }}" data-price="{{ $option['price'] }}" data-qty="{{ $option['quantity'] }}" data-tax="{{ $option['tax_rate'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <small class="sx-inv-stock"></small>
            </td>
            <td><input type="number" step="0.0001" min="0.0001" name="items[__INDEX__][quantity]" class="form-control sx-inv-qty" value="1" required></td>
            <td><input type="number" step="0.01" min="0" name="items[__INDEX__][unit_price]" class="form-control sx-inv-price" value="" required></td>
            <td><input type="number" step="0.01" min="0" name="items[__INDEX__][tax_rate]" class="form-control sx-inv-tax" value=""></td>
            <td>
                <div class="sx-inv-discount">
                    <select name="items[__INDEX__][discount_type]" class="form-control sx-inv-discount-type">
                        <option value="amount">Ksh</option>
                        <option value="percent">%</option>
                    </select>
                    <input type="number" step="0.01" min="0" name="items[__INDEX__][discount_value]" class="form-control sx-inv-discount-value" value="0">
                </div>
            </td>
            <td class="sx-inv-line-total text-right">0.00</td>
            <td><button type="button" class="btn btn-danger btn-xs sx-inv-remove" title="Remove"><i class="fa fa-trash"></i></button></td>
        </tr>
    </template>
</form>

@if($canAddCustomer)
<div class="modal fade" id="sx-inv-customer-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Add Customer</h4>
            </div>
            <div class="modal-body">
                <form id="sx-inv-customer-form">
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
                    <p class="text-danger" id="sx-inv-customer-error" hidden></p>
                    <div class="sx-form-actions">
                        <button type="submit" class="btn btn-success">Save</button>
                        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
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
    var rowIndex = $('#sx-inv-rows .sx-inv-row').length;

    function money(n) {
        return (Math.round((n + Number.EPSILON) * 100) / 100).toFixed(2);
    }

    function lineDiscount(base, type, value) {
        var discount = type === 'percent' ? base * (value / 100) : value;
        return Math.min(Math.max(0, discount), base);
    }

    function recalc() {
        var subtotal = 0;
        var taxTotal = 0;

        $('#sx-inv-rows .sx-inv-row').each(function () {
            var $row = $(this);
            var qty = parseFloat($row.find('.sx-inv-qty').val()) || 0;
            var price = parseFloat($row.find('.sx-inv-price').val()) || 0;
            var taxRate = parseFloat($row.find('.sx-inv-tax').val()) || 0;
            var base = qty * price;
            var discount = lineDiscount(base, $row.find('.sx-inv-discount-type').val(), parseFloat($row.find('.sx-inv-discount-value').val()) || 0);
            var taxable = Math.max(0, base - discount);
            var tax = taxable * (taxRate / 100);
            subtotal += taxable;
            taxTotal += tax;
            $row.find('.sx-inv-line-total').text(money(taxable + tax));
        });

        var headerDiscount = lineDiscount(subtotal, $('#sx-inv-header-type').val(), parseFloat($('#sx-inv-header-discount').val()) || 0);
        $('#sx-inv-subtotal').text(money(subtotal));
        $('#sx-inv-tax').text(money(taxTotal));
        $('#sx-inv-grand').text(money(Math.max(0, subtotal - headerDiscount + taxTotal)));
    }

    function bindRow($row) {
        $row.find('.sx-inv-product').on('change', function () {
            var $opt = $(this).find('option:selected');
            var price = parseFloat($opt.data('price'));
            var qty = $opt.attr('data-qty');
            if (price > 0) {
                $row.find('.sx-inv-price').val(price);
            } else if ($opt.val()) {
                $row.find('.sx-inv-price').val('');
            }
            if ($opt.data('tax') !== undefined && $opt.data('tax') !== '') {
                $row.find('.sx-inv-tax').val($opt.data('tax'));
            }
            var hint = '';
            if ($opt.val() && qty !== undefined && qty !== '') {
                hint = 'Available ' + qty;
            }
            if ($opt.val() && price > 0) {
                hint += (hint ? ' · ' : '') + 'Ksh ' + money(price);
            }
            $row.find('.sx-inv-stock').text(hint);
            recalc();
        });
        $row.find('.sx-inv-qty, .sx-inv-price, .sx-inv-tax, .sx-inv-discount-value, .sx-inv-discount-type').on('input change', recalc);
        $row.find('.sx-inv-remove').on('click', function () {
            if ($('#sx-inv-rows .sx-inv-row').length <= 1) {
                return;
            }
            $row.remove();
            recalc();
        });
    }

    $('#sx-inv-rows .sx-inv-row').each(function () {
        bindRow($(this));
    });

    $('#sx-inv-add-row').on('click', function () {
        var html = $('#sx-inv-row-template').html().replace(/__INDEX__/g, String(rowIndex++));
        var $row = $(html);
        $('#sx-inv-rows').append($row);
        bindRow($row);
        recalc();
    });

    $('#sx-inv-header-discount, #sx-inv-header-type').on('input change', recalc);
    recalc();

    $('#sx-inv-add-customer').on('click', function () {
        $('#sx-inv-customer-form')[0].reset();
        $('#sx-inv-customer-error').attr('hidden', true).text('');
        $('#sx-inv-customer-modal').modal('show');
    });

    $('#sx-inv-customer-form').on('submit', function (e) {
        e.preventDefault();
        var $error = $('#sx-inv-customer-error');
        $error.attr('hidden', true).text('');
        $.ajax({
            url: @json(route('sales.invoices.customers.store')),
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            data: $(this).serialize()
        }).done(function (data) {
            var label = data.name + (data.phone ? ' - ' + data.phone : '');
            $('#sx-inv-customer').append($('<option>', { value: data.id, text: label, selected: true }));
            $('#sx-inv-customer-modal').modal('hide');
        }).fail(function (xhr) {
            var message = 'Could not save customer.';
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                message = Object.values(xhr.responseJSON.errors)[0][0];
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            $error.text(message).removeAttr('hidden');
        });
    });
})(jQuery);
</script>
@endpush
