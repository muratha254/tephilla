@extends('layouts.master')

@section('title')
    Out of Stock Items
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Out of Stock Items</li>
@endsection

@push('css')
<style>
    .out-of-stock-row {
        background-color: #fff5f5 !important;
    }

    .out-of-stock-row td {
        color: #a94442 !important;
    }
</style>
@endpush

@section('content')
<div class="row">
    <!-- Summary Metrics -->
    <div class="col-lg-12">
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-exclamation-triangle"></i> Summary</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="info-box bg-red">
                            <span class="info-box-icon"><i class="fa fa-warning"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Items Out of Stock</span>
                                <span class="info-box-number" id="total-out-of-stock">0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Out of Stock Items Table -->
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-exclamation-circle text-danger"></i> Out of Stock Items</h3>
                <div class="btn-group pull-right">
                    <a href="{{ route('produk.index') }}" class="btn btn-info btn-flat"><i class="fa fa-list"></i> View All Products</a>
                    {{-- Purchase Order button - Currently Inactive
                    <a href="{{ route('purchase-orders.create') }}" class="btn btn-success btn-flat"><i class="fa fa-plus"></i> Create Purchase Order</a>
                    --}}
                </div>
            </div>
            <div class="box-body">
                <form method="GET" action="{{ route('produk.out_of_stock') }}" id="filterForm" class="mb-3">
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
                                    <a href="{{ route('produk.out_of_stock') }}" class="btn btn-default">
                                        <i class="fa fa-refresh"></i> Reset
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <table id="out-of-stock-table" class="table table-striped table-bordered table-hover">
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
                            <th>Stock</th>
                            <th>Reorder Level</th>
                            <th width="15%">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Update Stock Modal -->
<div class="modal fade" id="modal-update-stock" tabindex="-1" role="dialog" aria-labelledby="modal-update-stock-label">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="modal-update-stock-label">Update Stock</h4>
            </div>
            <form id="form-update-stock" method="post">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="id_produk" id="update-stock-product-id">
                    <div class="form-group">
                        <label for="update-stock-product-name">Product</label>
                        <input type="text" class="form-control" id="update-stock-product-name" readonly>
                    </div>
                    <div class="form-group">
                        <label for="update-stock-current">Current Stock</label>
                        <input type="text" class="form-control" id="update-stock-current" readonly>
                    </div>
                    <div class="form-group">
                        <label for="additional_stock">Additional Stock <span class="text-danger">*</span></label>
                        <input type="number" name="additional_stock" id="additional_stock" class="form-control" required min="1" placeholder="Enter quantity to add">
                        <small class="text-muted">Enter the quantity you want to add to the current stock</small>
                    </div>
                    <div class="form-group">
                        <label for="date_in">Date <span class="text-danger">*</span></label>
                        <input type="date" name="date_in" id="date_in" class="form-control" required value="{{ date('Y-m-d') }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success btn-flat">
                        <i class="fa fa-save"></i> Update Stock
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
    const restockUrl = "{{ route('produk.restock') }}";
    let currentProductData = null;

    function updateStock(productId, productName, currentStock) {
        currentProductData = {
            id: productId,
            name: productName,
            stock: currentStock
        };
        openUpdateStockModal();
    }

    function openUpdateStockModal() {
        if (currentProductData) {
            $('#update-stock-product-id').val(currentProductData.id);
            $('#update-stock-product-name').val(currentProductData.name);
            $('#update-stock-current').val(currentProductData.stock);
            $('#additional_stock').val('');
            $('#date_in').val('{{ date('Y-m-d') }}');
            $('#modal-update-stock').modal('show');
        } else {
            alert('Please select a product first');
        }
    }

    // Reset modal when closed
    $('#modal-update-stock').on('hidden.bs.modal', function() {
        $('#form-update-stock')[0].reset();
        currentProductData = null;
    });

    // Handle form submission
    $('#form-update-stock').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        
        submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Updating...');
        
        $.post(restockUrl, formData)
            .done(function(response) {
                alert('Stock updated successfully');
                $('#modal-update-stock').modal('hide');
                $('#out-of-stock-table').DataTable().ajax.reload(null, false);
            })
            .fail(function(xhr) {
                let errorMsg = 'Unable to update stock';
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
        const table = $('#out-of-stock-table').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: {
                url: '{{ route('produk.out_of_stock.data') }}',
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
                { data: 'stok', name: 'stok', className: 'text-right' },
                { data: 'reorder', name: 'reorder', className: 'text-right' },
                { data: 'aksi', name: 'aksi', searchable: false, sortable: false },
            ],
            createdRow: function(row, data, dataIndex) {
                $(row).addClass('out-of-stock-row');
            },
            drawCallback: function(settings) {
                // Update summary metrics
                const json = settings.json;
                if (json && json.total_out_of_stock !== undefined) {
                    $('#total-out-of-stock').text(json.total_out_of_stock);
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

