@extends('layouts.fleet')

@php
    $isEdit = $product->exists;
    $selectedBranch = old('branch_id', $selectedBranchId ?? session('current_branch_id'));
    $forSale = old('for_sale', $product->for_sale ? '1' : '0');
    $manageStock = old('manage_stock', $product->manage_stock ? '1' : '0');
    $allowNegative = old('allow_negative_stock', $product->allow_negative_stock ? '1' : '0');
    $taxInclusive = old('tax_inclusive', $product->tax_inclusive ? '1' : '0');
@endphp

@section('title', 'Items')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Items',
    'subtitle' => 'Add/Update Items',
    'backUrl' => route('products.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Items List', 'url' => route('products.index')],
        ['label' => 'Items'],
    ],
])

<form class="sx-item-form" action="{{ $isEdit ? route('products.update', $product) : route('products.store') }}" method="post" enctype="multipart/form-data">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="sx-box">
        <div class="sx-register-bar">Register Item</div>
        <div class="sx-box-body" style="min-height:auto;">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="sx-req">Branch *</label>
                        <select name="branch_id" class="form-control" required>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @if((string) $selectedBranch === (string) $branch->id) selected @endif>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="sx-req">Item Name*</label>
                        <input type="text" name="name" class="form-control" placeholder="Product Name" value="{{ old('name', $product->name) }}" required>
                    </div>
                </div>
                @php
                    $selectedTypeId = (string) old('category_id', $product->category_id);
                    $types = $categories->filter(function ($category) use ($categories, $selectedTypeId) {
                        $isType = $category->parent_id || ! $categories->contains('parent_id', $category->id);

                        return $isType || (string) $category->id === (string) $selectedTypeId;
                    })->values();
                @endphp
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="sx-req">Product type *</label>
                        <select name="category_id" id="category_id" class="form-control" required>
                            <option value="">-Select-</option>
                            @foreach($types as $type)
                                <option value="{{ $type->id }}" @if($selectedTypeId === (string) $type->id) selected @endif>{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Colour</label>
                        @if($isEdit)
                            <select name="colour_ids[]" id="colour_id" class="form-control" multiple>
                                @foreach($colours as $colour)
                                    <option value="{{ $colour->id }}" @if(in_array((string) $colour->id, $selectedColourIds ?? [], true)) selected @endif>{{ $colour->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <select name="colour_id" id="colour_id" class="form-control">
                                <option value="">-Select-</option>
                                @foreach($colours as $colour)
                                    <option value="{{ $colour->id }}" @if((string) ($selectedColourId ?? '') === (string) $colour->id) selected @endif>{{ $colour->name }}</option>
                                @endforeach
                            </select>
                            @foreach($selectedColourIds ?? [] as $colourId)
                                <input type="hidden" name="colour_ids[]" value="{{ $colourId }}">
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="is_active" class="form-control">
                            <option value="1" @if(old('is_active', $product->is_active ? '1' : '0') == '1') selected @endif>Active</option>
                            <option value="0" @if(old('is_active', $product->is_active ? '1' : '0') == '0') selected @endif>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="sx-req">Unit Of Measure*</label>
                        <div class="input-group">
                            <select name="unit_id" id="unit_id" class="form-control" required>
                                <option value="">-Select-</option>
                                @foreach($units as $unit)
                                    <option value="{{ $unit->id }}" @if(old('unit_id', $product->unit_id) == $unit->id) selected @endif>{{ $unit->name }} ({{ $unit->short_name }})</option>
                                @endforeach
                            </select>
                            @if(auth()->user()->hasPermission('units.manage'))
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-info sx-plus-btn" data-quick="unit" title="Add unit"><i class="fa fa-plus"></i></button>
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Alert Quantity</label>
                        <input type="number" step="0.01" min="0" name="reorder_level" class="form-control" value="{{ old('reorder_level', $product->reorder_level ?? 0) }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Serial Key Unit (SKU)</label>
                        <input type="text" name="sku" class="form-control" placeholder="Serial Key Unit(For Scanning)" value="{{ old('sku', $product->sku) }}">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="sx-req">Purpose <i class="fa fa-question-circle" title="State if For Sale or Not"></i> <span class="sx-hint">State if For Sale or Not</span></label>
                        <div class="input-group">
                            <span class="input-group-addon"><i class="fa fa-anchor"></i></span>
                            <select name="for_sale" class="form-control">
                                <option value="1" @if($forSale == '1') selected @endif>For Sale</option>
                                <option value="0" @if($forSale == '0') selected @endif>Not For Sale</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Manage Stock <i class="fa fa-info-circle sx-info" title="Track quantity on hand for this item"></i></label>
                        <select name="manage_stock" class="form-control">
                            <option value="1" @if($manageStock == '1') selected @endif>Yes</option>
                            <option value="0" @if($manageStock == '0') selected @endif>No</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Allow -ve Sale <i class="fa fa-info-circle sx-info" title="Allow selling when stock is below zero"></i></label>
                        <select name="allow_negative_stock" class="form-control">
                            <option value="0" @if($allowNegative == '0') selected @endif>No</option>
                            <option value="1" @if($allowNegative == '1') selected @endif>Yes</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Select Image</label>
                        <input type="file" name="image" class="form-control-file" accept="image/*">
                        @if($product->image_path)
                            <label class="sx-remove-image"><input type="checkbox" name="remove_image" value="1"> Remove current image</label>
                        @endif
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="sx-req">Tax Category*</label>
                        <select name="tax_id" class="form-control" required>
                            <option value="">-Select-</option>
                            @foreach($taxes as $tax)
                                <option value="{{ $tax->id }}" @if(old('tax_id', $product->tax_id) == $tax->id) selected @endif>{{ $tax->name }} ({{ $tax->rate }}%)</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="sx-req">Purchase Price*</label>
                        <input type="number" step="0.01" min="0" name="purchase_price" id="purchase_price" class="form-control" placeholder="Total Price with Tax Amount" value="{{ old('purchase_price', $isEdit ? $product->purchase_price : '') }}" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="sx-req">Tax Type*</label>
                        <select name="tax_inclusive" class="form-control">
                            <option value="1" @if($taxInclusive == '1') selected @endif>Inclusive</option>
                            <option value="0" @if($taxInclusive == '0') selected @endif>Exclusive</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Profit Margin(%)</label>
                        <input type="number" step="0.01" name="profit_margin" id="profit_margin" class="form-control" value="{{ old('profit_margin', $product->profit_margin ?? 0) }}">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="sx-req">Sales/Retail Price*</label>
                        <input type="number" step="0.01" min="0" name="selling_price" id="selling_price" class="form-control" placeholder="Sales Price" value="{{ old('selling_price', $isEdit ? $product->selling_price : '') }}" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="sx-req">Wholesale Price*</label>
                        <input type="number" step="0.01" min="0" name="wholesale_price" class="form-control" value="{{ old('wholesale_price', $product->wholesale_price ?? 0) }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="sx-req">Promo/Minimum Price*</label>
                        <input type="number" step="0.01" min="0" name="promo_price" class="form-control" value="{{ old('promo_price', $product->promo_price ?? 0) }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Sales Commission(%)</label>
                        <input type="number" step="0.01" min="0" name="sales_commission" class="form-control" value="{{ old('sales_commission', $product->sales_commission ?? 0) }}">
                    </div>
                </div>
            </div>

            @php
                $openingValue = old('opening_stock');
                if ($openingValue === null && $isEdit && isset($openingStock)) {
                    $openingValue = rtrim(rtrim(number_format((float) $openingStock, 4, '.', ''), '0'), '.');
                    if ($openingValue === '' || $openingValue === '-') {
                        $openingValue = '0';
                    }
                }
            @endphp
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Opening Stock</label>
                        <input type="number" step="0.01" name="opening_stock" id="opening_stock" class="form-control" placeholder="-/+" value="{{ $openingValue }}">
                    </div>
                </div>
                <div class="col-md-9">
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Description">{{ old('description', $product->description) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="sx-form-actions">
                <button type="submit" class="btn btn-success">Save</button>
                <a href="{{ route('products.index') }}" class="btn btn-warning">Close</a>
            </div>
        </div>
    </div>
</form>

<div class="modal fade" id="sx-quick-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="sx-quick-title">Add</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" id="sx-quick-name" class="form-control">
                </div>
                <div class="form-group" id="sx-quick-short-wrap" style="display:none;">
                    <label>Short name</label>
                    <input type="text" id="sx-quick-short" class="form-control" maxlength="32">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="sx-quick-save">Save</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var purchase = document.getElementById('purchase_price');
    var selling = document.getElementById('selling_price');
    var margin = document.getElementById('profit_margin');
    var updating = false;

    function toNumber(el) {
        return parseFloat(el && el.value ? el.value : 0) || 0;
    }

    function round2(n) {
        return Math.round(n * 100) / 100;
    }

    function fromPrices() {
        if (updating) return;
        updating = true;
        var cost = toNumber(purchase);
        var price = toNumber(selling);
        margin.value = cost > 0 ? round2(((price - cost) / cost) * 100) : 0;
        updating = false;
    }

    function fromMargin() {
        if (updating) return;
        updating = true;
        var cost = toNumber(purchase);
        selling.value = cost > 0 ? round2(cost * (1 + (toNumber(margin) / 100))) : selling.value;
        updating = false;
    }

    if (purchase && selling && margin) {
        purchase.addEventListener('input', fromPrices);
        selling.addEventListener('input', fromPrices);
        margin.addEventListener('input', fromMargin);
    }

    var endpoints = {
        unit: { url: @json(route('units.store')), select: 'unit_id', title: 'Add Unit' }
    };
    var currentType = null;
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    document.querySelectorAll('[data-quick]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            currentType = this.getAttribute('data-quick');
            document.getElementById('sx-quick-title').textContent = endpoints[currentType].title;
            document.getElementById('sx-quick-name').value = '';
            document.getElementById('sx-quick-short').value = '';
            document.getElementById('sx-quick-short-wrap').style.display = currentType === 'unit' ? 'block' : 'none';
            $('#sx-quick-modal').modal('show');
            setTimeout(function () { document.getElementById('sx-quick-name').focus(); }, 300);
        });
    });

    @if($isEdit && !empty($colourStock))
    var colourStock = @json($colourStock);
    var colourSelect = document.getElementById('colour_id');
    var openingInput = document.getElementById('opening_stock');
    if (colourSelect && openingInput) {
        colourSelect.addEventListener('change', function () {
            var key = colourSelect.value || '';
            if (Object.prototype.hasOwnProperty.call(colourStock, key)) {
                openingInput.value = colourStock[key];
            }
        });
    }
    @endif

    document.getElementById('sx-quick-save').addEventListener('click', function () {
        if (!currentType) return;
        var name = document.getElementById('sx-quick-name').value.trim();
        if (!name) return;
        var payload = { name: name };
        if (currentType === 'unit') {
            payload.short_name = document.getElementById('sx-quick-short').value.trim() || name.substring(0, 3);
        }
        fetch(endpoints[currentType].url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token
            },
            body: JSON.stringify(payload)
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok) throw data;
                return data;
            });
        }).then(function (data) {
            var select = document.getElementById(endpoints[currentType].select);
            var option = document.createElement('option');
            option.value = data.id;
            option.textContent = data.label || data.name;
            option.selected = true;
            select.appendChild(option);
            $('#sx-quick-modal').modal('hide');
        }).catch(function () {
            alert('Could not save. Check the name and try again.');
        });
    });
})();
</script>
@endpush
