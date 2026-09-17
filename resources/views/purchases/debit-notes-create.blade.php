@extends('layouts.fleet')

@section('title', 'New Debit Note')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'New Debit Note',
    'subtitle' => 'Create a debit note from a received purchase',
    'backUrl' => route('debit-notes.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Debit Notes', 'url' => route('debit-notes.index')],
        ['label' => 'New Debit Note'],
    ],
])

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        <ul style="margin:0;padding-left:18px;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="post" action="{{ route('debit-notes.store') }}" id="sx-debit-note-form" class="sx-item-form">
    @csrf

    <div class="sx-box sx-items-card">
        <div class="sx-items-toolbar">
            <h3 class="sx-box-title" style="flex:none;margin:0;"><i class="fa fa-file-text-o"></i> Debit Note Details</h3>
        </div>
        <div class="sx-box-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Purchase Order <span class="sx-req">*</span></label>
                        <select name="purchase_order_id" id="sx-dn-purchase" class="form-control" required>
                            <option value="">- Select received purchase -</option>
                            @foreach($purchases as $purchase)
                                <option value="{{ $purchase['id'] }}"
                                    data-url="{{ $purchase['eligible_url'] }}"
                                    @if((string) old('purchase_order_id') === (string) $purchase['id']) selected @endif>
                                    {{ $purchase['number'] }}
                                    — {{ $purchase['supplier'] }}
                                    — {{ $purchase['order_date'] }}
                                    — Ksh {{ $purchase['total'] }}
                                </option>
                            @endforeach
                        </select>
                        @if($purchases->isEmpty())
                            <p class="help-block">No returnable purchases found for this branch. Receive stock on a purchase first.</p>
                        @endif
                    </div>
                    <div class="form-group">
                        <label>Debit Note Date <span class="sx-req">*</span></label>
                        <input type="date" name="return_date" class="form-control" required
                               value="{{ old('return_date', now()->toDateString()) }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Notes / Reason</label>
                        <textarea name="notes" class="form-control" rows="4" maxlength="2000"
                                  placeholder="Reason for debit note">{{ old('notes') }}</textarea>
                    </div>
                    <div class="form-group" id="sx-dn-meta" style="display:none;">
                        <div><strong>Supplier:</strong> <span id="sx-dn-supplier">-</span></div>
                        <div><strong>Purchase:</strong> <span id="sx-dn-number">-</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="sx-box sx-items-card" style="margin-top:16px;">
        <div class="sx-items-toolbar">
            <h3 class="sx-box-title" style="flex:none;margin:0;"><i class="fa fa-list"></i> Items to Debit</h3>
        </div>
        <div class="sx-box-body sx-items-body">
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-dn-items">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Received</th>
                            <th>Already returned</th>
                            <th>Available</th>
                            <th>Unit Cost</th>
                            <th>Qty to Debit</th>
                            <th>Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="sx-dn-empty">
                            <td colspan="7">Select a purchase to load returnable items.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="text-right" style="margin-top:12px;">
                <strong>Debit Total: <span id="sx-dn-grand">Ksh 0.00</span></strong>
            </div>
        </div>
    </div>

    <div style="margin-top:16px;display:flex;gap:8px;">
        <button type="submit" class="btn sx-btn-gold" @if($purchases->isEmpty()) disabled @endif>
            <i class="fa fa-check"></i> Create Debit Note
        </button>
        <a href="{{ route('debit-notes.index') }}" class="btn btn-default">Cancel</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function ($) {
    function money(n) {
        n = parseFloat(n);
        if (isNaN(n)) n = 0;
        return 'Ksh ' + n.toFixed(2);
    }

    function recalc() {
        var total = 0;
        $('#sx-dn-items tbody tr').each(function () {
            var qty = parseFloat($(this).find('.sx-dn-qty').val()) || 0;
            var cost = parseFloat($(this).find('.sx-dn-cost').val()) || 0;
            var line = qty * cost;
            $(this).find('.sx-dn-line').text(money(line));
            total += line;
        });
        $('#sx-dn-grand').text(money(total));
    }

    function loadItems(url) {
        var body = $('#sx-dn-items tbody').empty();
        body.append('<tr><td colspan="7">Loading...</td></tr>');
        $.getJSON(url).done(function (data) {
            $('#sx-dn-supplier').text(data.supplier || '-');
            $('#sx-dn-number').text(data.number || '-');
            $('#sx-dn-meta').show();
            body.empty();
            if (!data.items || !data.items.length) {
                body.append('<tr id="sx-dn-empty"><td colspan="7">No returnable items on this purchase.</td></tr>');
                recalc();
                return;
            }
            data.items.forEach(function (item, i) {
                body.append(
                    '<tr>' +
                        '<td>' + $('<div>').text(item.name).html() +
                            '<input type="hidden" name="items[' + i + '][product_id]" value="' + item.product_id + '">' +
                        '</td>' +
                        '<td>' + item.received + '</td>' +
                        '<td>' + item.returned + '</td>' +
                        '<td>' + item.available + '</td>' +
                        '<td><input type="hidden" class="sx-dn-cost" value="' + item.unit_cost + '">' + money(item.unit_cost) + '</td>' +
                        '<td><input type="number" step="0.0001" min="0" max="' + item.available + '" name="items[' + i + '][quantity]" class="form-control sx-dn-qty" placeholder="0"></td>' +
                        '<td class="sx-dn-line">Ksh 0.00</td>' +
                    '</tr>'
                );
            });
            recalc();
        }).fail(function () {
            body.html('<tr><td colspan="7">Could not load purchase items.</td></tr>');
            $('#sx-dn-meta').hide();
            recalc();
        });
    }

    $('#sx-dn-purchase').on('change', function () {
        var url = $(this).find(':selected').data('url');
        if (!url) {
            $('#sx-dn-items tbody').html('<tr id="sx-dn-empty"><td colspan="7">Select a purchase to load returnable items.</td></tr>');
            $('#sx-dn-meta').hide();
            recalc();
            return;
        }
        loadItems(url);
    });

    $('#sx-dn-items').on('input', '.sx-dn-qty', recalc);

    $('#sx-debit-note-form').on('submit', function (e) {
        var hasQty = false;
        $('#sx-dn-items .sx-dn-qty').each(function () {
            if ((parseFloat(this.value) || 0) > 0) hasQty = true;
        });
        if (!hasQty) {
            e.preventDefault();
            if (window.Swal) {
                Swal.fire({ icon: 'warning', title: 'Enter quantities', text: 'Enter at least one quantity to debit.' });
            } else {
                alert('Enter at least one quantity to debit.');
            }
        }
    });

    if ($('#sx-dn-purchase').val()) {
        $('#sx-dn-purchase').trigger('change');
    }
})(jQuery);
</script>
@endpush
