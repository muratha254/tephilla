@extends('layouts.master')

@section('title')
    Sold Out of Stock Items
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Sold Out of Stock Items</li>
@endsection

@push('css')
<style>
    .sold-out-of-stock-row {
        background-color: #fff8e1 !important;
    }

    .sold-out-of-stock-row td {
        color: #f57c00 !important;
    }
</style>
@endpush

@section('content')
<div class="row">
    <!-- Summary Metrics -->
    <div class="col-lg-12">
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-exclamation-triangle"></i> Summary</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="info-box bg-yellow">
                            <span class="info-box-icon"><i class="fa fa-warning"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Items Sold Out of Stock</span>
                                <span class="info-box-number" id="total-sold-out-of-stock">0</span>
                                <span class="progress-description">
                                    These items were sold when stock was insufficient
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sold Out of Stock Items Table -->
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-exclamation-circle text-warning"></i> Sold Out of Stock Items</h3>
                <div class="btn-group pull-right">
                    <a href="{{ route('produk.index') }}" class="btn btn-info btn-flat"><i class="fa fa-list"></i> View All Products</a>
                </div>
            </div>
            <div class="box-body">
                <form method="GET" action="{{ route('produk.sold_out_of_stock') }}" id="filterForm" class="mb-3">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="shop_id">Shop</label>
                                <select name="shop_id" id="shop_id" class="form-control">
                                    <option value="">All Shops</option>
                                    @foreach(\App\Models\Shop::orderBy('shop_name')->get() as $shop)
                                        <option value="{{ $shop->id }}" {{ request('shop_id') == $shop->id ? 'selected' : '' }}>
                                            {{ $shop->shop_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="supplier_id">Supplier</label>
                                <select name="supplier_id" id="supplier_id" class="form-control">
                                    <option value="">All Suppliers</option>
                                    @foreach(\App\Models\Supplier::orderBy('nama')->get() as $supplier)
                                        <option value="{{ $supplier->id_supplier }}" {{ request('supplier_id') == $supplier->id_supplier ? 'selected' : '' }}>
                                            {{ $supplier->nama }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="category_id">Category</label>
                                <select name="category_id" id="category_id" class="form-control">
                                    <option value="">All Categories</option>
                                    @foreach(\App\Models\Kategori::orderBy('nama_kategori')->get() as $category)
                                        <option value="{{ $category->id_kategori }}" {{ request('category_id') == $category->id_kategori ? 'selected' : '' }}>
                                            {{ $category->nama_kategori }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div class="btn-group btn-block">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-filter"></i> Filter
                                    </button>
                                    <a href="{{ route('produk.sold_out_of_stock') }}" class="btn btn-default">
                                        <i class="fa fa-refresh"></i> Reset
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <table id="sold-out-of-stock-table" class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th>Product Code</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Shop</th>
                            <th>Supplier</th>
                            <th>Cost Price</th>
                            <th>Selling Price</th>
                            <th>Current Stock</th>
                            <th>Total Sold</th>
                            <th>Sold Out of Stock</th>
                            <th width="15%">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Restock Modal -->
<div class="modal fade" id="modal-restock" tabindex="-1" role="dialog" aria-labelledby="modal-restock-label">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="modal-restock-label">Add Stock</h4>
            </div>
            <form id="form-restock" method="post">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="id_produk" id="restock-product-id">
                    <div class="form-group">
                        <label for="restock-product-name">Product</label>
                        <input type="text" class="form-control" id="restock-product-name" readonly>
                    </div>
                    <div class="alert alert-info">
                        <strong>Note:</strong> When you add stock, the system will automatically deduct the quantity sold out of stock from the new stock being added.
                    </div>
                    <div class="form-group">
                        <label for="restock-current-stock">Current Stock</label>
                        <input type="text" class="form-control" id="restock-current-stock" readonly>
                    </div>
                    <div class="form-group">
                        <label for="restock-sold-out">Sold Out of Stock</label>
                        <input type="text" class="form-control" id="restock-sold-out" readonly style="background-color: #fff3cd; font-weight: bold;">
                    </div>
                    <div class="form-group">
                        <label for="restock-additional">Additional Stock to Add <span class="text-danger">*</span></label>
                        <input type="number" name="additional_stock" id="restock-additional" class="form-control" required min="1" placeholder="Enter quantity to add">
                        <small class="text-muted">Enter the quantity you want to add. The sold out of stock quantity will be automatically deducted.</small>
                    </div>
                    <div class="form-group">
                        <label for="restock-new-stock">New Stock After Addition</label>
                        <input type="text" class="form-control" id="restock-new-stock" readonly style="background-color: #d4edda; font-weight: bold;">
                    </div>
                    <div class="form-group">
                        <label for="restock-date">Date <span class="text-danger">*</span></label>
                        <input type="date" name="date_in" id="restock-date" class="form-control" required value="{{ date('Y-m-d') }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success btn-flat">
                        <i class="fa fa-save"></i> Add Stock
                    </button>
                    <button type="button" class="btn btn-default btn-flat" data-dismiss="modal">
                        <i class="fa fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const restockUrl = "{{ route('produk.sold_out_of_stock.restock') }}";
    let currentProductData = null;

    function restockProduct(productId, soldOutOfStock) {
        // Get product data from the table row
        const row = $('#sold-out-of-stock-table').DataTable().row(function(idx, data, node) {
            return data.id_produk == productId;
        });
        
        if (row.length === 0) {
            alert('Product not found');
            return;
        }

        const data = row.data();
        
        // Use the parameter value directly (it's passed from the button onclick)
        // But also ensure we parse it correctly
        const soldOutValue = parseInt(soldOutOfStock) || 0;
        const currentStockValue = parseInt(data.current_stock) || 0;
        
        // Recalculate sold out of stock to ensure accuracy
        // If current stock is negative, sold out of stock = abs(current stock)
        let calculatedSoldOut = soldOutValue;
        if (currentStockValue < 0) {
            calculatedSoldOut = Math.abs(currentStockValue);
        }
        
        currentProductData = {
            id: productId,
            name: data.nama_produk,
            currentStock: currentStockValue,
            soldOutOfStock: calculatedSoldOut
        };
        
        // Debug: log the values to check
        console.log('Restock Product:', {
            productId: productId,
            currentStock: currentProductData.currentStock,
            soldOutOfStock: currentProductData.soldOutOfStock,
            parameterValue: soldOutOfStock,
            calculatedSoldOut: calculatedSoldOut
        });
        openRestockModal();
    }

    function openRestockModal() {
        if (currentProductData) {
            $('#restock-product-id').val(currentProductData.id);
            $('#restock-product-name').val(currentProductData.name);
            $('#restock-current-stock').val(currentProductData.currentStock);
            $('#restock-sold-out').val(currentProductData.soldOutOfStock);
            $('#restock-additional').val('');
            $('#restock-date').val('{{ date('Y-m-d') }}');
            updateNewStockPreview();
            $('#modal-restock').modal('show');
        } else {
            alert('Please select a product first');
        }
    }

    function updateNewStockPreview() {
        if (!currentProductData) return;
        
        const currentStock = parseInt(currentProductData.currentStock) || 0;
        let soldOut = parseInt(currentProductData.soldOutOfStock) || 0;
        const additional = parseInt($('#restock-additional').val()) || 0;
        
        // Recalculate sold out of stock from current stock if it's negative
        // This ensures we use the correct value
        if (currentStock < 0) {
            soldOut = Math.abs(currentStock);
        }
        
        // New stock = additional - sold out of stock
        // Example: if adding 9 and sold out of stock is 1, new stock = 9 - 1 = 8
        const newStock = additional - soldOut;
        
        // Debug
        console.log('Preview Calculation:', {
            additional: additional,
            soldOut: soldOut,
            currentStock: currentStock,
            newStock: newStock,
            soldOutOfStockFromData: currentProductData.soldOutOfStock
        });
        
        $('#restock-new-stock').val(newStock >= 0 ? newStock : 0);
        
        if (newStock < 0) {
            $('#restock-new-stock').css('background-color', '#f8d7da');
        } else {
            $('#restock-new-stock').css('background-color', '#d4edda');
        }
    }

    // Calculate new stock as user types
    $('#restock-additional').on('input', function() {
        updateNewStockPreview();
    });

    // Reset modal when closed
    $('#modal-restock').on('hidden.bs.modal', function() {
        $('#form-restock')[0].reset();
        currentProductData = null;
    });

    // Handle form submission
    $('#form-restock').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        
        // Validate that additional stock is sufficient
        const soldOut = parseInt(currentProductData.soldOutOfStock) || 0;
        const additional = parseInt($('#restock-additional').val()) || 0;
        const newStock = additional - soldOut;
        
        if (additional < soldOut) {
            alert('Insufficient stock to add. You need to add at least ' + soldOut + ' units to cover the sold out of stock items. You are adding ' + additional + ' units.');
            return;
        }
        
        submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Adding Stock...');
        
        $.post(restockUrl, formData)
            .done(function(response) {
                alert(response.message || 'Stock added successfully');
                $('#modal-restock').modal('hide');
                $('#sold-out-of-stock-table').DataTable().ajax.reload(null, false);
            })
            .fail(function(xhr) {
                let errorMsg = 'Unable to add stock';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = xhr.responseJSON.errors;
                    errorMsg = Object.values(errors).flat().join('\n');
                }
                alert(errorMsg);
            })
            .always(function() {
                submitBtn.prop('disabled', false).html(originalText);
            });
    });

    $(function () {
        const table = $('#sold-out-of-stock-table').DataTable({
            responsive: true,
            processing: true,
            serverSide: false,
            autoWidth: false,
            ajax: {
                url: '{{ route('produk.sold_out_of_stock.data') }}',
                data: function (d) {
                    d.shop_id = $('#shop_id').val();
                    d.supplier_id = $('#supplier_id').val();
                    d.category_id = $('#category_id').val();
                }
            },
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', searchable: false, sortable: false },
                { data: 'kode_produk', name: 'kode_produk' },
                { data: 'nama_produk', name: 'nama_produk' },
                { data: 'nama_kategori', name: 'nama_kategori' },
                { data: 'shop_name', name: 'shop_name' },
                { data: 'supplier_name', name: 'supplier_name' },
                { data: 'harga_beli', name: 'harga_beli', className: 'text-right' },
                { data: 'harga_jual', name: 'harga_jual', className: 'text-right' },
                { data: 'current_stock', name: 'current_stock', className: 'text-right' },
                { data: 'total_sold', name: 'total_sold', className: 'text-right' },
                { 
                    data: 'sold_out_of_stock', 
                    name: 'sold_out_of_stock', 
                    className: 'text-right',
                    render: function(data, type, row) {
                        return '<span class="label label-warning">' + data + '</span>';
                    }
                },
                { data: 'aksi', name: 'aksi', searchable: false, sortable: false },
            ],
            createdRow: function(row, data, dataIndex) {
                $(row).addClass('sold-out-of-stock-row');
            },
            drawCallback: function(settings) {
                // Update summary metrics
                const json = settings.json;
                if (json && json.recordsTotal !== undefined) {
                    $('#total-sold-out-of-stock').text(json.recordsTotal);
                }
            }
        });

        // Reload table when filters change
        $('#filterForm').on('submit', function(e) {
            e.preventDefault();
            table.ajax.reload();
        });
    });
</script>
@endpush

