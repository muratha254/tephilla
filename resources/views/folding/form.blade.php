@extends('layouts.fleet')

@section('title', 'New folding')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'New folding / production',
    'subtitle' => 'Enter the flat sheet used and the finished items produced. Narration is not converted into quantities.',
    'backUrl' => route('folding.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Folding', 'url' => route('folding.index')],
        ['label' => 'New'],
    ],
])

<div class="sx-box">
    <div class="sx-box-body">
        <form action="{{ route('folding.store') }}" method="post" id="folding-form">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
            <input type="hidden" name="effect" value="consume">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="folded_on" class="form-control" value="{{ old('folded_on', now()->toDateString()) }}" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Branch</label>
                        <input type="text" class="form-control" value="{{ optional($branch)->name ?? session('current_branch_name') }}" readonly>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Raw material</label>
                        <select name="product_id" class="form-control" required>
                            <option value="">Select</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" data-unit="{{ optional($product->unit)->short_name ?: optional($product->unit)->name }}" data-price="{{ $product->purchase_price }}" @if((string) old('product_id') === (string) $product->id) selected @endif>
                                    {{ $product->name }}@if($product->unit) ({{ $product->unit->short_name ?: $product->unit->name }})@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Colour</label>
                        <select name="colour_id" id="raw-colour" class="form-control">
                            <option value="">Select</option>
                            @foreach($colours as $colour)
                                <option value="{{ $colour->id }}" @if((string) old('colour_id') === (string) $colour->id) selected @endif>{{ $colour->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Available</label>
                        <input type="text" id="raw-available" class="form-control" value="" readonly>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Quantity used</label>
                        <input type="number" name="quantity" id="raw-quantity" class="form-control" min="0.0001" step="0.0001" value="{{ old('quantity') }}" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Balance</label>
                        <input type="text" id="raw-balance" class="form-control" value="" readonly>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Unit</label>
                        <input type="text" id="raw-unit" class="form-control" value="" readonly>
                    </div>
                </div>
            </div>

            <h4>Produced items</h4>
            <p>Choose the finished item. If it is not in the list, <a href="{{ route('products.create') }}">register it under Products</a> first, using product type Roofing Accessory, then open Production again.</p>
            <table class="table table-bordered" id="output-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Colour</th>
                        <th>Quantity</th>
                        <th>Buying Price</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(old('outputs', [[], []]) as $index => $output)
                        <tr>
                            <td>
                                <select name="outputs[{{ $index }}][product_id]" class="form-control">
                                    <option value="">Select</option>
                                    @foreach($finishedProducts as $product)
                                        <option value="{{ $product->id }}" @if((string) ($output['product_id'] ?? '') === (string) $product->id) selected @endif>{{ $product->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="outputs[{{ $index }}][colour_id]" class="form-control">
                                    <option value="">Select</option>
                                    @foreach($colours as $colour)
                                        <option value="{{ $colour->id }}" @if((string) ($output['colour_id'] ?? '') === (string) $colour->id) selected @endif>{{ $colour->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="number" name="outputs[{{ $index }}][quantity]" class="form-control output-qty" min="0" step="0.0001" value="{{ $output['quantity'] ?? '' }}">
                            </td>
                            <td>
                                <input type="text" class="form-control output-cost" value="" readonly tabindex="-1">
                            </td>
                            <td><button type="button" class="btn btn-danger btn-xs remove-output">Remove</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p><button type="button" class="btn btn-default" id="add-output">Add product</button></p>

            <div class="form-group">
                <label>Narration</label>
                <input type="text" name="notes" class="form-control" maxlength="2000" value="{{ old('notes') }}" placeholder="folded: 20 valleys, 20 bbcs">
            </div>
            <button type="submit" class="btn btn-success">Save production</button>
            <a href="{{ route('folding.index') }}" class="btn btn-default">Cancel</a>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var stock = @json($materialStock ?? new \stdClass());
    var material = document.querySelector('select[name="product_id"]');
    var colour = document.getElementById('raw-colour');
    var unit = document.getElementById('raw-unit');
    var available = document.getElementById('raw-available');
    var used = document.getElementById('raw-quantity');
    var balance = document.getElementById('raw-balance');
    var savedColour = @json((string) old('colour_id', ''));

    function formatQty(amount) {
        return (Math.round((parseFloat(amount) || 0) * 10000) / 10000).toString();
    }

    function rowsFor(productId) {
        var rows = stock[productId] || [];
        return Array.isArray(rows) ? rows : [];
    }

    function fillColour(prefer) {
        if (!colour) return;
        var rows = material && material.value ? rowsFor(material.value) : [];
        var colours = rows.filter(function (row) { return row.id; });
        colour.innerHTML = '';
        if (!colours.length) {
            colour.appendChild(new Option('Select', ''));
            return;
        }
        colours.forEach(function (row) {
            colour.appendChild(new Option(row.name || 'Colour', row.id));
        });
        var chosen = colours.some(function (row) { return String(row.id) === String(prefer); })
            ? String(prefer)
            : String(colours[0].id);
        colour.value = chosen;
    }

    function qtyOnHand() {
        if (!material || !material.value) return 0;
        var rows = rowsFor(material.value);
        var colourId = colour ? colour.value : '';
        var match = rows.find(function (row) { return String(row.id || '') === String(colourId || ''); });
        if (!match && rows.length === 1) match = rows[0];
        return match ? (parseFloat(match.qty) || 0) : 0;
    }

    function refreshMaterial() {
        if (unit && material) {
            var selected = material.options[material.selectedIndex];
            unit.value = selected ? (selected.getAttribute('data-unit') || '') : '';
        }
        var onHand = qtyOnHand();
        if (available) available.value = material && material.value ? formatQty(onHand) : '';
        if (balance) {
            var usedQty = used ? parseFloat(used.value) : NaN;
            balance.value = material && material.value && !isNaN(usedQty) ? formatQty(onHand - usedQty) : '';
        }
        refreshBuyingPrices();
    }

    function costBasis() {
        if (!material || !material.value) return 0;
        var rows = rowsFor(material.value);
        var colourId = colour ? colour.value : '';
        var match = rows.find(function (row) { return String(row.id || '') === String(colourId || ''); });
        if (!match && rows.length === 1) match = rows[0];
        var basis = match ? (parseFloat(match.basis) || 0) : 0;
        return basis > 0 ? basis : (match ? (parseFloat(match.qty) || 0) : 0);
    }

    function refreshBuyingPrices() {
        var boxes = document.querySelectorAll('#output-table .output-cost');
        var price = 0;
        if (material && material.value) {
            var selected = material.options[material.selectedIndex];
            price = selected ? (parseFloat(selected.getAttribute('data-price')) || 0) : 0;
        }
        var onHand = qtyOnHand();
        var basis = costBasis();
        var usedQty = used ? parseFloat(used.value) : NaN;
        var produced = 0;
        document.querySelectorAll('#output-table .output-qty').forEach(function (field) {
            var qty = parseFloat(field.value);
            if (qty > 0) produced += qty;
        });
        var each = '';
        if (price > 0 && basis > 0 && usedQty > 0 && produced > 0 && usedQty <= onHand + 0.00005) {
            each = (Math.round(((price / basis) * usedQty / produced) * 100) / 100).toFixed(2);
        }
        boxes.forEach(function (box) { box.value = each; });
    }

    function syncOutputColours(onlyEmpty) {
        if (!colour || !colour.value) return;
        document.querySelectorAll('#output-table select[name$="[colour_id]"]').forEach(function (select) {
            if (onlyEmpty && select.value) return;
            var option = select.querySelector('option[value="' + colour.value + '"]');
            if (option) select.value = colour.value;
        });
    }

    if (material) {
        material.addEventListener('change', function () {
            fillColour('');
            refreshMaterial();
            syncOutputColours(false);
        });
        fillColour(savedColour);
        refreshMaterial();
        syncOutputColours(true);
    }
    if (colour) {
        colour.addEventListener('change', function () {
            refreshMaterial();
            syncOutputColours(false);
        });
    }
    if (used) used.addEventListener('input', refreshMaterial);
    var table = document.querySelector('#output-table tbody');
    var add = document.getElementById('add-output');
    if (!table || !add) return;
    add.addEventListener('click', function () {
        var row = table.rows[0].cloneNode(true);
        var index = table.rows.length;
        row.querySelectorAll('select, input').forEach(function (field) {
            field.name = field.name.replace(/outputs\[\d+\]/, 'outputs[' + index + ']');
            if (field.name.indexOf('[colour_id]') !== -1 && colour && colour.value) {
                field.value = colour.value;
            } else if (field.tagName === 'SELECT') {
                field.selectedIndex = 0;
            } else {
                field.value = '';
            }
        });
        table.appendChild(row);
        refreshBuyingPrices();
    });
    table.addEventListener('input', refreshBuyingPrices);
    table.addEventListener('click', function (event) {
        if (!event.target.classList.contains('remove-output')) return;
        if (table.rows.length === 1) return;
        event.target.closest('tr').remove();
        refreshBuyingPrices();
    });
})();
</script>
@endpush
