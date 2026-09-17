@extends('layouts.fleet')

@section('title', 'New Credit Note')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'New Credit Note',
    'subtitle' => 'Credit a completed sale',
    'backUrl' => route('sales.credit-notes'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Credit Notes', 'url' => route('sales.credit-notes')],
        ['label' => 'New Credit Note'],
    ],
])

<form method="post" action="{{ route('sales.credit-notes.store') }}" id="sx-credit-note-form" class="sx-item-form">
    @csrf

    <div class="sx-box sx-items-card">
        <div class="sx-items-toolbar">
            <h3 class="sx-box-title" style="flex:none;margin:0;"><i class="fa fa-file-text-o"></i> Credit Note Details</h3>
        </div>
        <div class="sx-box-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Sale / Invoice <span class="sx-req">*</span></label>
                        <select name="sale_id" id="sx-cn-sale" class="form-control" required>
                            <option value="">- Select completed sale -</option>
                            @foreach($sales as $sale)
                                <option value="{{ $sale->id }}"
                                    data-url="{{ route('sales.credit-notes.eligible', $sale) }}"
                                    @if((string) old('sale_id') === (string) $sale->id) selected @endif>
                                    {{ $sale->documentNumber() }}
                                    — {{ $sale->customerDisplayName() }}
                                    — Ksh {{ number_format((float) $sale->total, 2) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Credit Date <span class="sx-req">*</span></label>
                        <input type="date" name="credit_date" class="form-control" required
                               value="{{ old('credit_date', now()->toDateString()) }}">
                    </div>
                    <div class="form-group">
                        <label>Reason <span class="sx-req">*</span></label>
                        <input type="text" name="reason" class="form-control" required maxlength="255"
                               value="{{ old('reason') }}" placeholder="Reason for credit note">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="3" maxlength="2000"
                                  placeholder="Optional notes">{{ old('notes') }}</textarea>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="restore_stock" value="1" @if(old('restore_stock', true)) checked @endif>
                            Restore stock on post
                        </label>
                    </div>
                    <div class="form-group" id="sx-cn-sale-meta" style="display:none;">
                        <div><strong>Customer:</strong> <span id="sx-cn-customer">-</span></div>
                        <div><strong>Sale total:</strong> <span id="sx-cn-sale-total">-</span></div>
                        <div><strong>Balance:</strong> <span id="sx-cn-sale-balance">-</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="sx-box sx-items-card" style="margin-top:16px;">
        <div class="sx-items-toolbar">
            <h3 class="sx-box-title" style="flex:none;margin:0;"><i class="fa fa-list"></i> Items to Credit</h3>
        </div>
        <div class="sx-box-body sx-items-body">
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-cn-items">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Sold</th>
                            <th>Returned</th>
                            <th>Credited</th>
                            <th>Available</th>
                            <th>Unit Price</th>
                            <th>Qty to Credit</th>
                            <th>Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="sx-cn-empty">
                            <td colspan="8">Select a sale to load creditable items.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="text-right" style="margin-top:12px;">
                <strong>Credit Total: <span id="sx-cn-grand">Ksh 0.00</span></strong>
            </div>
        </div>
    </div>

    <div style="margin-top:16px;display:flex;gap:8px;">
        <button type="submit" name="action" value="draft" class="btn btn-default">
            <i class="fa fa-save"></i> Save Draft
        </button>
        <button type="submit" name="action" value="posted" class="btn sx-btn-gold">
            <i class="fa fa-check"></i> Post Credit Note
        </button>
        <a href="{{ route('sales.credit-notes') }}" class="btn btn-default">Cancel</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function ($) {
    function money(n) {
        return 'Ksh ' + (Number(n) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalc() {
        var total = 0;
        $('#sx-cn-items tbody tr[data-row]').each(function () {
            var $row = $(this);
            var qty = parseFloat($row.find('.sx-cn-qty').val()) || 0;
            var available = parseFloat($row.data('available')) || 0;
            if (qty > available) {
                qty = available;
                $row.find('.sx-cn-qty').val(qty > 0 ? qty : '');
            }
            var unit = parseFloat($row.data('unit')) || 0;
            var lineTotalBase = parseFloat($row.data('line-total')) || 0;
            var sold = parseFloat($row.data('sold')) || 0;
            var line = sold > 0 ? (lineTotalBase * (qty / sold)) : (qty * unit);
            line = Math.round(line * 100) / 100;
            $row.find('.sx-cn-line').text(money(line));
            total += line;
        });
        $('#sx-cn-grand').text(money(total));
    }

    function renderItems(items) {
        var $tbody = $('#sx-cn-items tbody');
        $tbody.empty();
        if (!items || !items.length) {
            $tbody.append('<tr id="sx-cn-empty"><td colspan="8">No creditable quantities left on this sale.</td></tr>');
            recalc();
            return;
        }

        items.forEach(function (item, index) {
            var row = $(
                '<tr data-row="1"'
                + ' data-available="' + item.available + '"'
                + ' data-unit="' + item.unit_price + '"'
                + ' data-sold="' + item.sold_qty + '"'
                + ' data-line-total="' + item.line_total + '">'
                + '<td>' + $('<div>').text(item.name).html()
                + '<input type="hidden" name="items[' + index + '][sale_item_id]" value="' + item.sale_item_id + '">'
                + '</td>'
                + '<td>' + item.sold_qty + '</td>'
                + '<td>' + item.returned_qty + '</td>'
                + '<td>' + item.credited_qty + '</td>'
                + '<td>' + item.available + '</td>'
                + '<td>' + money(item.unit_price) + '</td>'
                + '<td><input type="number" step="0.0001" min="0" max="' + item.available + '" class="form-control sx-cn-qty" name="items[' + index + '][quantity]" value=""></td>'
                + '<td class="sx-cn-line">' + money(0) + '</td>'
                + '</tr>'
            );
            $tbody.append(row);
        });
        recalc();
    }

    $('#sx-cn-sale').on('change', function () {
        var $opt = $(this).find('option:selected');
        var url = $opt.data('url');
        $('#sx-cn-sale-meta').hide();
        if (!url) {
            renderItems([]);
            $('#sx-cn-items tbody').html('<tr id="sx-cn-empty"><td colspan="8">Select a sale to load creditable items.</td></tr>');
            return;
        }

        $('#sx-cn-items tbody').html('<tr><td colspan="8">Loading...</td></tr>');
        $.getJSON(url)
            .done(function (data) {
                $('#sx-cn-customer').text(data.customer || '-');
                $('#sx-cn-sale-total').text(money(data.total));
                $('#sx-cn-sale-balance').text(money(data.balance));
                $('#sx-cn-sale-meta').show();
                renderItems(data.items || []);
            })
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Could not load sale items.';
                $('#sx-cn-items tbody').html('<tr><td colspan="8">' + $('<div>').text(msg).html() + '</td></tr>');
            });
    });

    $(document).on('input change', '.sx-cn-qty', recalc);

    if ($('#sx-cn-sale').val()) {
        $('#sx-cn-sale').trigger('change');
    }
})(jQuery);
</script>
@endpush
