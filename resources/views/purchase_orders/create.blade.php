@extends('layouts.master')

@section('title')
    Create Purchase Order
@endsection

@section('breadcrumb')
    @parent
    <li><a href="{{ route('purchase-orders.index') }}">Purchase Orders</a></li>
    <li class="active">Create</li>
@endsection

@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<style>
    .table-po-items th,
    .table-po-items td {
        vertical-align: middle !important;
    }
    .po-total-input {
        background-color: #f9f9f9;
    }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">New Purchase Order</h3>
                <div class="box-tools pull-right">
                    <a href="{{ route('purchase-orders.index') }}" class="btn btn-default btn-flat">
                        <i class="fa fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
            <form action="{{ route('purchase-orders.store-multi') }}" method="POST" id="po-form">
                @csrf
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="order_date">Order Date</label>
                                <input type="date" class="form-control" name="order_date" id="order_date" value="{{ now()->format('Y-m-d') }}" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="reference_number">Reference Number</label>
                                <input type="text" class="form-control" name="reference_number" id="reference_number" placeholder="Reference will be generated" value="{{ $defaultReference }}">
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> <strong>Note:</strong> You can add items from different suppliers. Select the supplier for each item in the table below.
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="Instructions or remarks (optional)"></textarea>
                    </div>

                    <hr>
                    <div class="form-group">
                        <div class="clearfix m-b-10">
                            <h4 class="pull-left">Items</h4>
                            <button type="button" class="btn btn-primary btn-flat pull-right" id="btn-add-item">
                                <i class="fa fa-plus"></i> Add Item
                            </button>
                        </div>
                        <div class="table-responsive" id="po-items-wrapper">
                            <table class="table table-bordered table-striped table-po-items">
                                <thead>
                                    <tr>
                                        <th style="width: 25%;">Item</th>
                                        <th style="width: 20%;">Supplier</th>
                                        <th style="width: 12%;">Quantity</th>
                                        <th style="width: 15%;">Unit Price (KES)</th>
                                        <th style="width: 15%;">Total Price (KES)</th>
                                        <th style="width: 8%;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="po-items-body">
                                </tbody>
                            </table>
                        </div>
                        <small class="text-muted">Total price is automatically calculated as quantity × unit price.</small>
                    </div>
                </div>
                <div class="box-footer text-right">
                    <button type="submit" class="btn btn-success btn-flat">
                        <i class="fa fa-save"></i> Save Purchase Order
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    const productOptions = @json($productOptions);
    const suppliers = @json($suppliers);

    let itemIndex = 0;
    const itemsWrapper = $('#po-items-wrapper');

    function buildRow(index) {
        const productOptionsHtml = productOptions.map(product => {
            const label = `${product.name} (Stock: ${product.stock})`;
            return `<option value="${product.id}" data-price="${product.price}" data-supplier-id="${product.supplier_id || ''}">${label}</option>`;
        }).join('');

        const supplierOptionsHtml = suppliers.map(supplier => {
            return `<option value="${supplier.id_supplier}">${supplier.nama}</option>`;
        }).join('');

        return `
            <tr data-index="${index}">
                <td>
                    <select name="items[${index}][product_id]" class="form-control product-select" required data-placeholder="Search product">
                        <option value="">Select Item</option>
                        ${productOptionsHtml}
                    </select>
                </td>
                <td>
                    <select name="items[${index}][supplier_id]" class="form-control supplier-select" required data-placeholder="Select Supplier">
                        <option value="">Select Supplier</option>
                        ${supplierOptionsHtml}
                    </select>
                </td>
                <td>
                    <input type="number" min="1" class="form-control qty-input" name="items[${index}][quantity]" value="1" required>
                </td>
                <td>
                    <input type="number" min="0" step="0.01" class="form-control unit-price-input" name="items[${index}][unit_price]" placeholder="0.00">
                </td>
                <td>
                    <input type="text" class="form-control po-total-input total-price-input" name="items[${index}][total_display]" value="0.00" readonly>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-flat btn-remove-item">
                        <i class="fa fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }

    function initProductSelect($select) {
        $select.select2({
            placeholder: 'Search product',
            width: '100%',
            dropdownParent: itemsWrapper,
        });
    }

    function initSupplierSelect($select) {
        $select.select2({
            placeholder: 'Select Supplier',
            width: '100%',
            dropdownParent: itemsWrapper,
        });
    }

    function addItemRow() {
        const row = $(buildRow(itemIndex++));
        $('#po-items-body').append(row);
        initProductSelect(row.find('.product-select'));
        initSupplierSelect(row.find('.supplier-select'));
    }

    function ensureAtLeastOneRow() {
        if ($('#po-items-body tr').length === 0) {
            addItemRow();
        }
    }

    function updateRowTotals(row) {
        const qty = parseFloat(row.find('.qty-input').val()) || 0;
        const unitPrice = parseFloat(row.find('.unit-price-input').val()) || 0;
        const total = (qty * unitPrice).toFixed(2);
        row.find('.total-price-input').val(total);
    }

    $(function () {
        ensureAtLeastOneRow();
        
        // Initialize Select2 for the first row if not already initialized
        const firstRow = $('#po-items-body tr').first();
        if (firstRow.length) {
            const productSelect = firstRow.find('.product-select');
            const supplierSelect = firstRow.find('.supplier-select');
            
            if (!productSelect.hasClass('select2-hidden-accessible')) {
                initProductSelect(productSelect);
            }
            if (!supplierSelect.hasClass('select2-hidden-accessible')) {
                initSupplierSelect(supplierSelect);
            }
        }
        
        // Pre-select product if product_id is provided in URL
        @if(isset($selectedProductId) && $selectedProductId)
            const selectedProductId = {{ $selectedProductId }};
            // Use setTimeout to ensure Select2 is fully initialized
            setTimeout(function() {
                const firstRow = $('#po-items-body tr').first();
                const productSelect = firstRow.find('.product-select');
                
                if (productSelect.length && productSelect.find('option[value="' + selectedProductId + '"]').length > 0) {
                    productSelect.val(selectedProductId).trigger('change');
                }
            }, 100);
        @endif

        $('#btn-add-item').on('click', function () {
            addItemRow();
        });

        $(document).on('change', '.product-select', function () {
            const selected = $(this).find('option:selected');
            const price = parseFloat(selected.data('price')) || 0;
            const supplierId = selected.data('supplier-id') || '';
            const row = $(this).closest('tr');
            const priceInput = row.find('.unit-price-input');
            const supplierSelect = row.find('.supplier-select');
            
            // Always update supplier when product changes
            if (supplierId) {
                supplierSelect.val(supplierId).trigger('change');
            } else {
                supplierSelect.val('').trigger('change');
            }
            
            // Always update price when product changes
            priceInput.val(price.toFixed(2));
            updateRowTotals(row);
        });

        $(document).on('input', '.qty-input, .unit-price-input', function () {
            updateRowTotals($(this).closest('tr'));
        });

        $(document).on('click', '.btn-remove-item', function () {
            const body = $('#po-items-body');
            if (body.find('tr').length === 1) {
                const firstRow = body.find('tr').first();
                firstRow.find('input').val('');
                firstRow.find('.qty-input').val(1);
                firstRow.find('.total-price-input').val('0.00');
                firstRow.find('.product-select').val('').trigger('change');
                firstRow.find('.supplier-select').val('').trigger('change');
            } else {
                $(this).closest('tr').remove();
            }
        });
    });
</script>
@endpush

