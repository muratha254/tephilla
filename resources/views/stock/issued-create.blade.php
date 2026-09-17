@extends('layouts.fleet')

@section('title', 'Add Issued Products')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Add Issued Products',
    'backUrl' => route('stock.issued'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Issued Products', 'url' => route('stock.issued')],
        ['label' => 'Add Issued Products'],
    ],
])

@php
    $oldItems = old('items', [[
        'product_id' => '',
        'unit_price' => '',
        'quantity' => '1',
        'issued_at' => now()->toDateString(),
        'notes' => '',
    ]]);
@endphp

<form method="post" action="{{ route('stock.issued.store') }}" id="sx-issued-form" class="sx-issued-form">
    @csrf

    <div class="sx-issued-head">
        <div class="sx-issued-col-item">Item Name</div>
        <div class="sx-issued-col-price">Unit Price</div>
        <div class="sx-issued-col-qty">Qty</div>
        <div class="sx-issued-col-sub">Sub Total</div>
        <div class="sx-issued-col-date">Date</div>
        <div class="sx-issued-col-action">
            <button type="button" class="btn btn-success sx-issued-add" id="sx-issued-add" title="Add line"><i class="fa fa-plus"></i></button>
        </div>
    </div>

    <div class="sx-issued-branch">
        <label>Branch/Station</label>
        <select name="branch_id" class="form-control" required>
            @foreach($branches as $row)
                <option value="{{ $row->id }}" @if((string) old('branch_id', $selectedBranchId) === (string) $row->id) selected @endif>{{ $row->name }}</option>
            @endforeach
        </select>
    </div>

    <div id="sx-issued-lines">
        @foreach($oldItems as $index => $line)
            @include('stock.partials.issued-line', ['index' => $index, 'line' => $line, 'products' => $products])
        @endforeach
    </div>

    <div class="sx-issued-grand">
        <label>Grand Total</label>
        <input type="text" id="sx-issued-grand" class="form-control" value="0.00" readonly>
    </div>

    <div class="sx-issued-submit">
        <button type="submit" class="btn btn-success"><i class="fa fa-floppy-o"></i> Submit</button>
    </div>
</form>

<script type="text/template" id="sx-issued-line-tpl">
    @include('stock.partials.issued-line', ['index' => '__INDEX__', 'line' => [
        'product_id' => '',
        'unit_price' => '',
        'quantity' => '1',
        'issued_at' => now()->toDateString(),
        'notes' => '',
    ], 'products' => $products])
</script>
@endsection

@push('scripts')
<script>
(function ($) {
    var prices = @json($productPrices);
    var nextIndex = {{ count($oldItems) }};

    function money(value) {
        var n = parseFloat(value);
        if (isNaN(n)) n = 0;
        return n.toFixed(2);
    }

    function lineSubtotal(row) {
        var price = parseFloat(row.find('.sx-issued-price').val()) || 0;
        var qty = parseFloat(row.find('.sx-issued-qty').val()) || 0;
        var total = price * qty;
        row.find('.sx-issued-sub').val(money(total));
        return total;
    }

    function recalc() {
        var grand = 0;
        $('#sx-issued-lines .sx-issued-line').each(function () {
            grand += lineSubtotal($(this));
        });
        $('#sx-issued-grand').val(money(grand));
    }

    $('#sx-issued-lines').on('change', '.sx-issued-product', function () {
        var row = $(this).closest('.sx-issued-line');
        var id = $(this).val();
        row.find('.sx-issued-price').val(id && prices[id] != null ? money(prices[id]) : '');
        recalc();
    });

    $('#sx-issued-lines').on('input', '.sx-issued-price, .sx-issued-qty', recalc);

    $('#sx-issued-add').on('click', function () {
        var html = $('#sx-issued-line-tpl').html().replace(/__INDEX__/g, String(nextIndex++));
        $('#sx-issued-lines').append(html);
        recalc();
    });

    $('#sx-issued-lines').on('click', '.sx-issued-remove', function () {
        if ($('#sx-issued-lines .sx-issued-line').length === 1) {
            var row = $(this).closest('.sx-issued-line');
            row.find('select').val('');
            row.find('.sx-issued-price, .sx-issued-sub').val('');
            row.find('.sx-issued-qty').val('1');
            row.find('textarea').val('');
            recalc();
            return;
        }
        $(this).closest('.sx-issued-line').remove();
        recalc();
    });

    recalc();
})(jQuery);
</script>
@endpush
