@extends('layouts.fleet')

@section('title', 'New Stock Transfer')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'New Stock Transfer',
    'subtitle' => 'Move stock between branches',
    'backUrl' => route('stock.transfers.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Stock Transfers', 'url' => route('stock.transfers.index')],
        ['label' => 'New Transfer'],
    ],
])

@php
    $oldItems = old('items', [[
        'product_id' => '',
        'quantity' => '1',
        'unit_cost' => '',
    ]]);
@endphp

<form method="post" action="{{ route('stock.transfers.store') }}" id="sx-transfer-form" class="sx-item-form">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
    <div class="sx-box">
        <div class="sx-box-body" style="min-height:auto;">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>From Branch <span class="sx-req">*</span></label>
                        <select name="from_branch_id" class="form-control" required>
                            @foreach($branches as $row)
                                <option value="{{ $row->id }}" @if((string) old('from_branch_id', $selectedBranchId) === (string) $row->id) selected @endif>{{ $row->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>To Branch <span class="sx-req">*</span></label>
                        <select name="to_branch_id" class="form-control" required>
                            <option value="">~~Select~~</option>
                            @foreach($branches as $row)
                                <option value="{{ $row->id }}" @if((string) old('to_branch_id') === (string) $row->id) selected @endif>{{ $row->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Transfer Date <span class="sx-req">*</span></label>
                        <input type="date" name="transfer_date" class="form-control" value="{{ old('transfer_date', now()->toDateString()) }}" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Notes</label>
                        <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="Optional notes">
                    </div>
                </div>
            </div>

            <div class="sx-issued-head" style="margin-top:12px;">
                <div class="sx-issued-col-item">Item</div>
                <div class="sx-issued-col-price">Unit Cost</div>
                <div class="sx-issued-col-qty">Qty</div>
                <div class="sx-issued-col-action">
                    <button type="button" class="btn btn-success" id="sx-transfer-add" title="Add line"><i class="fa fa-plus"></i></button>
                </div>
            </div>

            <div id="sx-transfer-lines">
                @foreach($oldItems as $index => $line)
                    <div class="sx-issued-line sx-transfer-line">
                        <div class="sx-issued-col-item">
                            <select name="items[{{ $index }}][product_id]" class="form-control sx-transfer-product" required>
                                <option value="">~~Select Product~~</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" @if((string) ($line['product_id'] ?? '') === (string) $product->id) selected @endif>{{ $product->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sx-issued-col-price">
                            <input type="number" step="0.0001" min="0" name="items[{{ $index }}][unit_cost]" class="form-control sx-transfer-cost" value="{{ $line['unit_cost'] ?? '' }}">
                        </div>
                        <div class="sx-issued-col-qty">
                            <input type="number" step="0.0001" min="0.0001" name="items[{{ $index }}][quantity]" class="form-control" value="{{ $line['quantity'] ?? '1' }}" required>
                        </div>
                        <div class="sx-issued-col-action">
                            <button type="button" class="btn btn-danger sx-transfer-remove"><i class="fa fa-trash"></i></button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="text-center sx-form-actions" style="margin-top:20px;">
                <button type="submit" name="action" value="draft" class="btn btn-warning"><i class="fa fa-save"></i> Save Draft</button>
                <button type="submit" name="action" value="complete" class="btn btn-success"><i class="fa fa-check"></i> Save &amp; Complete</button>
                <a href="{{ route('stock.transfers.index') }}" class="btn btn-default"><i class="fa fa-times"></i> Close</a>
            </div>
        </div>
    </div>
</form>

<script type="text/template" id="sx-transfer-line-tpl">
    <div class="sx-issued-line sx-transfer-line">
        <div class="sx-issued-col-item">
            <select name="items[__INDEX__][product_id]" class="form-control sx-transfer-product" required>
                <option value="">~~Select Product~~</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="sx-issued-col-price">
            <input type="number" step="0.0001" min="0" name="items[__INDEX__][unit_cost]" class="form-control sx-transfer-cost" value="">
        </div>
        <div class="sx-issued-col-qty">
            <input type="number" step="0.0001" min="0.0001" name="items[__INDEX__][quantity]" class="form-control" value="1" required>
        </div>
        <div class="sx-issued-col-action">
            <button type="button" class="btn btn-danger sx-transfer-remove"><i class="fa fa-trash"></i></button>
        </div>
    </div>
</script>
@endsection

@push('scripts')
<script>
(function ($) {
    var costs = @json($productCosts);
    var nextIndex = {{ count($oldItems) }};

    function money(value) {
        var n = parseFloat(value);
        if (isNaN(n)) n = 0;
        return n.toFixed(4);
    }

    $('#sx-transfer-lines').on('change', '.sx-transfer-product', function () {
        var row = $(this).closest('.sx-transfer-line');
        var id = $(this).val();
        row.find('.sx-transfer-cost').val(id && costs[id] != null ? money(costs[id]) : '');
    });

    $('#sx-transfer-add').on('click', function () {
        var html = $('#sx-transfer-line-tpl').html().replace(/__INDEX__/g, String(nextIndex++));
        $('#sx-transfer-lines').append(html);
    });

    $('#sx-transfer-lines').on('click', '.sx-transfer-remove', function () {
        if ($('#sx-transfer-lines .sx-transfer-line').length === 1) {
            var row = $(this).closest('.sx-transfer-line');
            row.find('select').val('');
            row.find('input').val('');
            row.find('input[name*="[quantity]"]').val('1');
            return;
        }
        $(this).closest('.sx-transfer-line').remove();
    });
})(jQuery);
</script>
@endpush
