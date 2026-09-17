@extends('layouts.fleet')

@php $isEdit = $bom->exists; @endphp
@section('title', $isEdit ? 'Update BOM' : 'Create BOM')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Bill of Materials',
    'subtitle' => 'Add/Update BOM',
    'backUrl' => route('manufacturing.bom.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'BOM List', 'url' => route('manufacturing.bom.index')],
        ['label' => 'BOM'],
    ],
])

<form method="post" action="{{ $isEdit ? route('manufacturing.bom.update', $bom) : route('manufacturing.bom.store') }}" class="sx-item-form" id="sx-bom-form">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <div class="sx-box">
        <div class="sx-box-body" style="min-height:auto;">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Branch <span class="sx-req">*</span></label>
                        <select name="branch_id" class="form-control" required>
                            <option value="">~Select Branch~</option>
                            @foreach($branches as $option)
                                <option value="{{ $option->id }}" @if((string) old('branch_id', $bom->branch_id ?: $selectedBranchId) === (string) $option->id) selected @endif>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Product <span class="sx-req">*</span></label>
                        <select name="product_id" class="form-control" required>
                            <option value="">~Select Product to Make BOM~</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" data-cost="{{ $product->purchase_price }}" @if((string) old('product_id', $bom->product_id) === (string) $product->id) selected @endif>{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Description <span class="sx-req">*</span></label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Description(XYZ Production)" required>{{ old('description', $bom->description) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered" id="sx-bom-items">
                    <thead>
                        <tr>
                            <th>Items</th>
                            <th style="width:140px;">Unit Price</th>
                            <th style="width:120px;">Qty</th>
                            <th style="width:140px;">Sub Total</th>
                            <th>Description</th>
                            <th style="width:50px;"><button type="button" class="btn btn-success btn-xs" id="sx-bom-add"><i class="fa fa-plus"></i></button></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $oldItems = old('items', $bom->exists ? $bom->items->toArray() : [['product_id'=>'','unit_price'=>'','quantity'=>'','description'=>'']]); @endphp
                        @foreach($oldItems as $i => $item)
                            <tr>
                                <td>
                                    <select name="items[{{ $i }}][product_id]" class="form-control sx-bom-product">
                                        <option value="">Select Item</option>
                                        @foreach($products as $product)
                                            <option value="{{ $product->id }}" data-cost="{{ $product->purchase_price }}" @if((string) ($item['product_id'] ?? '') === (string) $product->id) selected @endif>{{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="number" step="0.01" min="0" name="items[{{ $i }}][unit_price]" class="form-control sx-bom-price" value="{{ $item['unit_price'] ?? '' }}"></td>
                                <td><input type="number" step="0.0001" min="0" name="items[{{ $i }}][quantity]" class="form-control sx-bom-qty" value="{{ $item['quantity'] ?? '' }}"></td>
                                <td><input type="text" class="form-control sx-bom-sub" value="{{ number_format((float) ($item['sub_total'] ?? 0), 2, '.', '') }}" readonly></td>
                                <td><input type="text" name="items[{{ $i }}][description]" class="form-control" value="{{ $item['description'] ?? '' }}"></td>
                                <td><button type="button" class="btn btn-danger btn-xs sx-bom-remove"><i class="fa fa-minus"></i></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="row">
                <div class="col-md-4 col-md-offset-8">
                    <div class="form-group">
                        <label>Expected Production</label>
                        <input type="number" step="0.0001" min="0" name="expected_production" id="sx-bom-expected" class="form-control" value="{{ old('expected_production', $bom->expected_production ?: 1) }}" readonly>
                    </div>
                    <div class="form-group">
                        <label>Grand Total</label>
                        <input type="text" id="sx-bom-grand" class="form-control" value="{{ number_format((float) $bom->production_cost, 2, '.', '') }}" readonly>
                    </div>
                </div>
            </div>

            <div class="text-center sx-form-actions">
                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
                <a href="{{ route('manufacturing.bom.index') }}" class="btn btn-warning"><i class="fa fa-times"></i> Close</a>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function ($) {
    var index = $('#sx-bom-items tbody tr').length;
    var productOptions = @json($products->map(fn($p) => ['id'=>$p->id,'name'=>$p->name,'cost'=>(float)$p->purchase_price])->values());
    function optionsHtml(selected) {
        var html = '<option value="">Select Item</option>';
        productOptions.forEach(function (p) {
            html += '<option value="'+p.id+'" data-cost="'+p.cost+'"'+(String(selected)===String(p.id)?' selected':'')+'>'+p.name+'</option>';
        });
        return html;
    }
    function recalc() {
        var grand = 0;
        $('#sx-bom-items tbody tr').each(function () {
            var price = parseFloat($(this).find('.sx-bom-price').val() || '0') || 0;
            var qty = parseFloat($(this).find('.sx-bom-qty').val() || '0') || 0;
            var sub = Math.round(price * qty * 100) / 100;
            $(this).find('.sx-bom-sub').val(sub.toFixed(2));
            grand += sub;
        });
        $('#sx-bom-grand').val(grand.toFixed(2));
    }
    $('#sx-bom-add').on('click', function () {
        var i = index++;
        var row = '<tr><td><select name="items['+i+'][product_id]" class="form-control sx-bom-product">'+optionsHtml('')+'</select></td>'+
            '<td><input type="number" step="0.01" min="0" name="items['+i+'][unit_price]" class="form-control sx-bom-price"></td>'+
            '<td><input type="number" step="0.0001" min="0" name="items['+i+'][quantity]" class="form-control sx-bom-qty"></td>'+
            '<td><input type="text" class="form-control sx-bom-sub" readonly></td>'+
            '<td><input type="text" name="items['+i+'][description]" class="form-control"></td>'+
            '<td><button type="button" class="btn btn-danger btn-xs sx-bom-remove"><i class="fa fa-minus"></i></button></td></tr>';
        $('#sx-bom-items tbody').append(row);
    });
    $(document).on('click', '.sx-bom-remove', function () { $(this).closest('tr').remove(); recalc(); });
    $(document).on('change', '.sx-bom-product', function () {
        var cost = $(this).find(':selected').data('cost');
        if (cost !== undefined) $(this).closest('tr').find('.sx-bom-price').val(cost);
        recalc();
    });
    $(document).on('input', '.sx-bom-price, .sx-bom-qty', recalc);
    recalc();
})(jQuery);
</script>
@endpush
