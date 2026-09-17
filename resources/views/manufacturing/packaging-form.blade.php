@extends('layouts.fleet')
@section('title', 'Packaging')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Packaging',
    'backUrl' => route('manufacturing.packaging.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Packaging List', 'url' => route('manufacturing.packaging.index')],
        ['label' => 'Packaging'],
    ],
])

<form method="post" action="{{ route('manufacturing.packaging.store') }}" class="sx-item-form" id="sx-pkg-form">
    @csrf
    <div class="sx-box">
        <div class="sx-box-body" style="min-height:auto;">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Product:(Bulk) <span class="sx-req">*</span></label>
                        <select name="product_id" id="sx-pkg-bulk" class="form-control" required>
                            <option value="">~Select Product to Package~</option>
                            @foreach($products as $product)
                                <option value="{{ $product['id'] }}" data-stock="{{ $product['stock'] }}">{{ $product['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Available Stock <span class="sx-req">*</span></label>
                        <input type="text" id="sx-pkg-stock" class="form-control" value="Available Stock" readonly>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered" id="sx-pkg-items">
                    <thead>
                        <tr>
                            <th>Product To Package</th>
                            <th style="width:140px;">Unit Control</th>
                            <th style="width:140px;">Qty To Package</th>
                            <th style="width:140px;">Stock Packaged</th>
                            <th style="width:90px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <select name="items[0][product_id]" class="form-control sx-pkg-product">
                                    <option value="">---Select Product---</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product['id'] }}">{{ $product['name'] }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="text" name="items[0][unit_control]" class="form-control"></td>
                            <td><input type="number" step="0.0001" min="0" name="items[0][qty_to_package]" class="form-control sx-pkg-qty" value="0"></td>
                            <td><input type="number" step="0.0001" min="0" name="items[0][stock_packaged]" class="form-control sx-pkg-packaged" value="0" readonly></td>
                            <td>
                                <button type="button" class="btn btn-success btn-xs" id="sx-pkg-add"><i class="fa fa-plus"></i></button>
                                <button type="button" class="btn btn-danger btn-xs sx-pkg-remove"><i class="fa fa-minus"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="row">
                <div class="col-md-4 col-md-offset-8">
                    <div class="form-group">
                        <label>Grand Total</label>
                        <input type="text" id="sx-pkg-grand" class="form-control" value="0" readonly>
                    </div>
                </div>
            </div>

            <div class="text-center sx-form-actions">
                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
                <a href="{{ route('manufacturing.packaging.index') }}" class="btn btn-warning"><i class="fa fa-times"></i> Close</a>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function ($) {
    var index = 1;
    var opts = @json(collect($products)->map(fn ($p) => ['id' => $p['id'], 'name' => $p['name']])->values());
    function optHtml() {
        var h = '<option value="">---Select Product---</option>';
        opts.forEach(function (p) { h += '<option value="'+p.id+'">'+p.name+'</option>'; });
        return h;
    }
    function recalc() {
        var total = 0;
        $('#sx-pkg-items tbody tr').each(function () {
            var qty = parseFloat($(this).find('.sx-pkg-qty').val() || '0') || 0;
            $(this).find('.sx-pkg-packaged').val(qty);
            total += qty;
        });
        $('#sx-pkg-grand').val(total);
    }
    $('#sx-pkg-bulk').on('change', function () {
        var stock = $(this).find(':selected').data('stock');
        $('#sx-pkg-stock').val(stock !== undefined ? stock : 'Available Stock');
    });
    $('#sx-pkg-add').on('click', function () {
        var i = index++;
        $('#sx-pkg-items tbody').append(
            '<tr><td><select name="items['+i+'][product_id]" class="form-control sx-pkg-product">'+optHtml()+'</select></td>'+
            '<td><input type="text" name="items['+i+'][unit_control]" class="form-control"></td>'+
            '<td><input type="number" step="0.0001" min="0" name="items['+i+'][qty_to_package]" class="form-control sx-pkg-qty" value="0"></td>'+
            '<td><input type="number" step="0.0001" min="0" name="items['+i+'][stock_packaged]" class="form-control sx-pkg-packaged" value="0" readonly></td>'+
            '<td><button type="button" class="btn btn-danger btn-xs sx-pkg-remove"><i class="fa fa-minus"></i></button></td></tr>'
        );
    });
    $(document).on('click', '.sx-pkg-remove', function () {
        if ($('#sx-pkg-items tbody tr').length <= 1) return;
        $(this).closest('tr').remove();
        recalc();
    });
    $(document).on('input', '.sx-pkg-qty', recalc);
    recalc();
})(jQuery);
</script>
@endpush
