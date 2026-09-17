@extends('layouts.fleet')
@section('title', 'Create Production')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Production',
    'subtitle' => 'Add/Update Production',
    'backUrl' => route('manufacturing.production.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Production List', 'url' => route('manufacturing.production.index')],
        ['label' => 'Production'],
    ],
])

<form method="post" action="{{ route('manufacturing.production.store') }}" class="sx-item-form" id="sx-prod-form">
    @csrf
    <div class="sx-box">
        <div class="sx-box-body" style="min-height:auto;">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Production Date <span class="sx-req">*</span></label>
                        <input type="date" name="production_date" class="form-control" value="{{ old('production_date', now()->toDateString()) }}" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>BOM <span class="sx-req">*</span></label>
                        <select name="bom_id" id="sx-prod-bom" class="form-control" required>
                            <option value="">~Select BOM~</option>
                            @foreach($boms as $bom)
                                <option value="{{ $bom->id }}" @if((string) old('bom_id') === (string) $bom->id) selected @endif>{{ optional($bom->product)->name }} — {{ $bom->description }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Expected Prod <span class="sx-req">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.0001" min="0.0001" name="expected_production" id="sx-prod-expected" class="form-control" placeholder="Controller Item" value="{{ old('expected_production') }}" required>
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-success" id="sx-prod-update"><i class="fa fa-refresh"></i> Update</button>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Description <span class="sx-req">*</span></label>
                <textarea name="description" class="form-control" rows="2" placeholder="Description" required>{{ old('description') }}</textarea>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered" id="sx-prod-items">
                    <thead>
                        <tr>
                            <th>Items</th>
                            <th style="width:140px;">Unit Price</th>
                            <th style="width:120px;">Qty</th>
                            <th style="width:140px;">Sub Total</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <div class="text-center sx-form-actions">
                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
                <a href="{{ route('manufacturing.production.index') }}" class="btn btn-warning"><i class="fa fa-times"></i> Close</a>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function ($) {
    function renderItems(items, factor) {
        var body = $('#sx-prod-items tbody').empty();
        items.forEach(function (item, i) {
            var qty = Math.round((item.quantity * factor) * 10000) / 10000;
            var sub = Math.round((item.unit_price * qty) * 100) / 100;
            body.append(
                '<tr>'+
                '<td><input type="hidden" name="items['+i+'][product_id]" value="'+item.product_id+'">'+item.name+'</td>'+
                '<td><input type="number" step="0.01" name="items['+i+'][unit_price]" class="form-control" value="'+item.unit_price+'" readonly></td>'+
                '<td><input type="number" step="0.0001" name="items['+i+'][quantity]" class="form-control" value="'+qty+'" readonly></td>'+
                '<td><input type="text" class="form-control" value="'+sub.toFixed(2)+'" readonly></td>'+
                '<td><input type="text" name="items['+i+'][description]" class="form-control" value="'+(item.description||'')+'"></td>'+
                '</tr>'
            );
        });
    }
    function loadBom() {
        var id = $('#sx-prod-bom').val();
        if (!id) { $('#sx-prod-items tbody').empty(); return; }
        $.getJSON(@json(route('manufacturing.bom.json', ['bom' => '__ID__'])).replace('__ID__', id), function (bom) {
            var expected = parseFloat($('#sx-prod-expected').val() || bom.expected_production || 1) || 1;
            if (!$('#sx-prod-expected').val()) $('#sx-prod-expected').val(bom.expected_production || 1);
            var base = parseFloat(bom.expected_production || 1) || 1;
            renderItems(bom.items || [], expected / base);
        });
    }
    $('#sx-prod-bom').on('change', loadBom);
    $('#sx-prod-update').on('click', loadBom);
    if ($('#sx-prod-bom').val()) loadBom();
})(jQuery);
</script>
@endpush
