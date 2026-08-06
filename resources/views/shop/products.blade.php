@extends('layouts.master')

@section('title')
    Shop Products
@endsection

@section('breadcrumb')
    @parent
    <li><a href="{{ route('shop.index') }}">Shops</a></li>
    <li class="active">Products for {{ $shop->shop_name }}</li>
@endsection

@section('content')
<style>
    .out-of-stock-row {
        background-color: #f8d7da !important;
    }
    .out-of-stock-row:hover {
        background-color: #f5c6cb !important;
    }
    .out-of-stock-row td {
        border-left: 3px solid #dc3545 !important;
    }
</style>
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-home"></i> {{ $shop->shop_name }} ({{ $shop->shop_code }})</h3>
                <div class="box-tools">
                    <a href="{{ route('shop.index') }}" class="btn btn-default btn-flat">
                        <i class="fa fa-arrow-left"></i> Back to Shops
                    </a>
                </div>
            </div>
            <div class="box-body table-responsive">
                <table id="products-table" class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th>Item Code</th>
                            <th>Product Name</th>
                            <th>Supplier</th>
                            <th>Cost Price</th>
                            <th>Selling Price</th>
                            <th>Stock</th>
                            <th width="20%">Actions</th>
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

<!-- History Modal -->
<div class="modal fade" id="modal-history" tabindex="-1" role="dialog" aria-labelledby="modal-history-label">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="modal-history-label">Product History</h4>
            </div>
            <div class="modal-body">
                <div class="clearfix" style="margin-bottom: 15px;">
                    <div class="pull-left">
                        <strong id="history-product-name"></strong><br>
                        <small id="history-product-meta"></small>
                    </div>
                    <div class="pull-right">
                        <a id="btn-history-print" href="#" class="btn btn-default btn-flat" target="_blank">
                            <i class="fa fa-print"></i> Print
                        </a>
                        <button id="btn-history-update-stock" type="button" class="btn btn-warning btn-flat" onclick="openUpdateStockModal()">
                            <i class="fa fa-edit"></i> Update Stock
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Previous Stock</th>
                                <th>Change</th>
                                <th>Current Stock</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody id="history-table-body">
                            <tr>
                                <td colspan="6" class="text-center text-muted">Select a product to view history.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default btn-flat" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const historyUrlTemplate = "{{ route('shop.products.history', [$shop->id, 'PRODUCT_ID_PLACEHOLDER']) }}";
    const historyPrintUrlTemplate = "{{ route('shop.products.history.print', [$shop->id, 'PRODUCT_ID_PLACEHOLDER']) }}";
    const restockUrl = "{{ route('produk.restock') }}";
    const productShowUrlTemplate = "{{ url('/produk') }}/PRODUCT_ID_PLACEHOLDER";
    let currentProductId = null;
    let currentProductData = null;

    $(function () {
        $('#products-table').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: {
                url: '{{ route('shop.products.data', $shop->id) }}',
            },
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', searchable: false, sortable: false },
                { data: 'item_code', name: 'item_code' },
                { data: 'nama_produk', name: 'nama_produk' },
                { data: 'supplier_name', name: 'supplier_name' },
                { data: 'harga_beli', name: 'harga_beli', className: 'text-right' },
                { data: 'harga_jual', name: 'harga_jual', className: 'text-right' },
                { data: 'stok', name: 'stok', className: 'text-right' },
                { data: 'stok_raw', name: 'stok_raw', visible: false },
                { data: 'aksi', name: 'aksi', searchable: false, sortable: false },
            ],
            createdRow: function(row, data, dataIndex) {
                // Check if stock is out (stok <= 0)
                // The stok_raw field contains the raw numeric value
                var stockValue = parseFloat(data.stok_raw || data.stok || 0);
                if (stockValue <= 0) {
                    $(row).addClass('out-of-stock-row');
                }
            },
        });
    });

    function viewHistory(productId) {
        const url = historyUrlTemplate.replace('PRODUCT_ID_PLACEHOLDER', productId);
        const printUrl = historyPrintUrlTemplate.replace('PRODUCT_ID_PLACEHOLDER', productId);

        $('#history-table-body').html('<tr><td colspan="6" class="text-center text-muted">Loading history...</td></tr>');
        $('#history-product-name').text('Loading...');
        $('#history-product-meta').text('');
        $('#btn-history-print').attr('href', printUrl);
        currentProductId = productId;

        $.get(url)
            .done(function(response) {
                console.log('History response:', response);
                
                $('#history-product-name').text(response.product.name + ' (' + response.product.item_code + ')');
                $('#history-product-meta').text('Supplier: ' + (response.product.supplier || 'N/A'));

                if (!response.history || response.history.length === 0) {
                    $('#history-table-body').html('<tr><td colspan="6" class="text-center text-muted">No history recorded for this product yet.</td></tr>');
                } else {
                    let rows = '';
                    response.history.forEach(function(entry) {
                        const changeSign = entry.restock_amount >= 0 ? '+' : '';
                        rows += '<tr>' +
                            '<td>' + (entry.created_at || '-') + '</td>' +
                            '<td><span class="label label-info">' + (entry.type || 'Unknown') + '</span></td>' +
                            '<td class="text-right">' + (entry.previous_stock || 0) + '</td>' +
                            '<td class="text-right"><strong>' + changeSign + (entry.restock_amount || 0) + '</strong></td>' +
                            '<td class="text-right"><strong>' + (entry.current_stock || 0) + '</strong></td>' +
                            '<td>' + (entry.notes || '-') + '</td>' +
                            '</tr>';
                    });
                    $('#history-table-body').html(rows);
                }

                $('#modal-history').modal('show');
            })
            .fail(function(xhr, status, error) {
                console.error('History load error:', xhr, status, error);
                let errorMsg = 'Unable to load product history.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.status === 404) {
                    errorMsg = 'Product not found.';
                } else if (xhr.status === 500) {
                    errorMsg = 'Server error. Please try again.';
                }
                alert(errorMsg);
                $('#modal-history').modal('hide');
            });
    }

    function updateStock(productId, productName, currentStock) {
        currentProductId = productId;
        currentProductData = {
            id: productId,
            name: productName,
            stock: currentStock
        };
        openUpdateStockModal();
    }

    function openUpdateStockModal() {
        if (currentProductData) {
            // Use stored product data (from table button click)
            $('#update-stock-product-id').val(currentProductData.id);
            $('#update-stock-product-name').val(currentProductData.name);
            $('#update-stock-current').val(currentProductData.stock);
            $('#additional_stock').val('');
            $('#date_in').val('{{ date('Y-m-d') }}');
            $('#modal-update-stock').modal('show');
        } else if (currentProductId) {
            // Get product data from API (from history modal button)
            const url = productShowUrlTemplate.replace('PRODUCT_ID_PLACEHOLDER', currentProductId);
            $.get(url)
                .done(function(product) {
                    $('#update-stock-product-id').val(product.id_produk);
                    $('#update-stock-product-name').val(product.nama_produk + ' (' + product.item_code + ')');
                    $('#update-stock-current').val(product.stok || 0);
                    $('#additional_stock').val('');
                    $('#date_in').val('{{ date('Y-m-d') }}');
                    $('#modal-update-stock').modal('show');
                })
                .fail(function() {
                    alert('Unable to load product information');
                });
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
                $('#products-table').DataTable().ajax.reload(null, false);
                // Also reload history if modal is open
                if ($('#modal-history').is(':visible') && currentProductId) {
                    viewHistory(currentProductId);
                }
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
</script>
@endpush













