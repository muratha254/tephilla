@extends('layouts.master')

@section('title')
    Review Receipt
@endsection

@section('breadcrumb')
    @parent
    <li><a href="{{ route('receipt-confirmation.index') }}">Receipt Confirmation</a></li>
    <li class="active">Review Receipt</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Review Receipt - {{ $penjualan->receiptno }}</h3>
                    <div class="box-tools pull-right">
                        <a href="{{ route('receipt-confirmation.index') }}" class="btn btn-default btn-sm">
                            <i class="fa fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>

                <div class="box-body">
                    <!-- Receipt Information -->
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">Receipt Number</th>
                                    <td>{{ $penjualan->receiptno }}</td>
                                </tr>
                                <tr>
                                    <th>Date of Sale</th>
                                    <td>{{ $penjualan->saledate ? \Carbon\Carbon::parse($penjualan->saledate)->format('d/m/Y') : 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Total Items</th>
                                    <td>{{ $penjualan->total_item }}</td>
                                </tr>
                                <tr>
                                    <th>Total Amount</th>
                                    <td>Ksh {{ format_uang($penjualan->bayar) }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">Status</th>
                                    <td>
                                        @if(!empty($waitingUpdate))
                                            <span class="label label-default">Waiting update</span>
                                            <span class="text-muted small"> — Review inactive until quick-added items are updated in Products.</span>
                                        @else
                                        @php
                                            $status = $penjualan->confirmation_status ?? 'pending';
                                            $wasEditedFromDefect = $penjualan->was_edited_from_defect ?? false;
                                            
                                            if ($status === 'confirmed' && $wasEditedFromDefect) {
                                                $displayStatus = 'Confirmed with Edit';
                                                $badgeClass = 'success';
                                            } elseif ($status === 'pending') {
                                                $displayStatus = 'Pending';
                                                $badgeClass = 'warning';
                                            } elseif ($status === 'confirmed') {
                                                $displayStatus = 'Confirmed';
                                                $badgeClass = 'success';
                                            } elseif ($status === 'defect') {
                                                $displayStatus = 'Defect';
                                                $badgeClass = 'danger';
                                            } elseif ($status === 'review') {
                                                $displayStatus = 'Review';
                                                $badgeClass = 'info';
                                            } else {
                                                $displayStatus = ucfirst($status);
                                                $badgeClass = 'secondary';
                                            }
                                        @endphp
                                        <span class="label label-{{ $badgeClass }}">{{ $displayStatus }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Cashier</th>
                                    <td>{{ $penjualan->user->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Member</th>
                                    <td>{{ $penjualan->member->nama ?? 'Walk-in Customer' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Items to Review -->
                    <div class="row" style="margin-top: 20px;">
                        <div class="col-md-12">
                            @if(!empty($waitingUpdate))
                                <div class="alert alert-warning">
                                    <i class="fa fa-clock-o"></i> <strong>Waiting update.</strong> This receipt has quick-added item(s) that are not yet updated in Products. Review is inactive until an admin updates those items (supplier, purchase price, etc.) in the Products menu.
                                </div>
                            @endif
                            <h4>Items</h4>
                            @php
                                $status = $penjualan->confirmation_status ?? 'pending';
                                $itemCount = $penjualan->details->count();
                                $uniqueProducts = $penjualan->details->pluck('id_produk')->unique()->count();
                            @endphp
                            
                            @if($uniqueProducts > 1)
                                <p class="text-info"><i class="fa fa-info-circle"></i> This receipt contains multiple different items. Please review and confirm each item individually.</p>
                            @endif
                            <table class="table table-striped table-bordered" id="items-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Product Name</th>
                                        <th>Shop</th>
                                        <th>Product Code</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Discount (%)</th>
                                        <th>Subtotal</th>
                                        <th>Item Status</th>
                                        <th width="25%">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($penjualan->details as $index => $detail)
                                        @php
                                            $itemStatus = $detail->item_confirmation_status ?? 'pending';
                                            $statusBadge = [
                                                'pending' => 'warning',
                                                'confirmed' => 'success',
                                                'defect' => 'danger'
                                            ][$itemStatus] ?? 'secondary';
                                        @endphp
                                        <tr id="item-row-{{ $detail->id_penjualan_detail }}">
                                            <td>{{ $index + 1 }}</td>
                                            <td><span class="label label-success" style="display:inline-block;max-width:100%;white-space:normal;text-align:left;">{{ $detail->produk->nama_produk ?? 'N/A' }}</span></td>
                                            <td>
                                                <select class="form-control input-sm item-shop" 
                                                        data-id="{{ $detail->id_penjualan_detail }}" 
                                                        style="width: 150px;">
                                                    @foreach($shops as $shop)
                                                        <option value="{{ $shop->id }}" 
                                                                {{ ($detail->produk->shop_id ?? null) == $shop->id ? 'selected' : '' }}>
                                                            {{ $shop->shop_name }} ({{ $shop->shop_code }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td><span class="item-code-display">{{ $detail->produk->kode_produk ?? $detail->produk->item_code ?? 'N/A' }}</span></td>
                                            <td>
                                                <input type="number" class="form-control input-sm item-price" 
                                                       data-id="{{ $detail->id_penjualan_detail }}" 
                                                       value="{{ $detail->harga_jual }}" 
                                                       min="0" step="0.01" style="width: 100px;">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control input-sm item-quantity" 
                                                       data-id="{{ $detail->id_penjualan_detail }}" 
                                                       value="{{ $detail->jumlah }}" 
                                                       min="1" style="width: 80px;">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control input-sm item-discount" 
                                                       data-id="{{ $detail->id_penjualan_detail }}" 
                                                       value="{{ $detail->diskon }}" 
                                                       min="0" max="100" style="width: 80px;">
                                            </td>
                                            <td class="item-subtotal" data-id="{{ $detail->id_penjualan_detail }}">
                                                Ksh {{ format_uang($detail->subtotal) }}
                                            </td>
                                            <td>
                                                <span class="label label-{{ $statusBadge }}" id="status-badge-{{ $detail->id_penjualan_detail }}">
                                                    {{ ucfirst($itemStatus) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if(!empty($waitingUpdate))
                                                    <span class="text-muted">Inactive — waiting update</span>
                                                @elseif($uniqueProducts > 1)
                                                    <!-- Multiple items - show item-level actions -->
                                                    <button onclick="confirmItem({{ $penjualan->id_penjualan }}, {{ $detail->id_penjualan_detail }})" 
                                                            class="btn btn-success btn-xs" 
                                                            id="confirm-btn-{{ $detail->id_penjualan_detail }}"
                                                            {{ $itemStatus === 'confirmed' ? 'disabled' : '' }}>
                                                        <i class="fa fa-check"></i> Confirm
                                                    </button>
                                                    <button onclick="markItemDefect({{ $penjualan->id_penjualan }}, {{ $detail->id_penjualan_detail }})" 
                                                            class="btn btn-danger btn-xs"
                                                            id="defect-btn-{{ $detail->id_penjualan_detail }}"
                                                            {{ $itemStatus === 'defect' ? 'disabled' : '' }}>
                                                        <i class="fa fa-exclamation-triangle"></i> Defect
                                                    </button>
                                                    <button onclick="updateItem({{ $penjualan->id_penjualan }}, {{ $detail->id_penjualan_detail }})" 
                                                            class="btn btn-warning btn-xs">
                                                        <i class="fa fa-save"></i> Update
                                                    </button>
                                                @else
                                                    <!-- Single item - show receipt-level actions -->
                                                    @if($status !== 'confirmed')
                                                        <button onclick="confirmReceipt({{ $penjualan->id_penjualan }})" class="btn btn-success btn-xs">
                                                            <i class="fa fa-check"></i> Confirm
                                                        </button>
                                                    @endif
                                                    
                                                    @if($status !== 'defect')
                                                        <button onclick="markDefect({{ $penjualan->id_penjualan }})" class="btn btn-danger btn-xs">
                                                            <i class="fa fa-exclamation-triangle"></i> Defect
                                                        </button>
                                                    @endif
                                                    
                                                    <button onclick="updateItem({{ $penjualan->id_penjualan }}, {{ $detail->id_penjualan_detail }})" 
                                                            class="btn btn-info btn-xs">
                                                        <i class="fa fa-save"></i> Update
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function confirmItem(receiptId, itemId) {
            if (confirm('Are you sure you want to confirm this item?')) {
                $.ajax({
                    url: '{{ url('receipt-confirmation') }}/' + receiptId + '/items/' + itemId + '/confirm',
                    method: 'POST',
                    data: {
                        '_token': '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            updateItemStatus(itemId, 'confirmed');
                            
                            if (response.all_confirmed) {
                                alert('All items are confirmed. The receipt has been automatically confirmed.');
                                setTimeout(function() {
                                    window.location.href = '{{ route('receipt-confirmation.index') }}';
                                }, 1000);
                            } else if (response.receipt_status === 'defect') {
                                alert('Item confirmed, but receipt has defect items. Receipt status changed to Defect.');
                                setTimeout(function() {
                                    location.reload();
                                }, 1000);
                            } else {
                                alert('Item confirmed successfully.');
                            }
                        } else {
                            alert('Error: ' + (response.error || 'Unknown error'));
                        }
                    },
                    error: function(xhr) {
                        var errorMsg = 'Unable to confirm item';
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            errorMsg = xhr.responseJSON.error;
                        }
                        alert(errorMsg);
                    }
                });
            }
        }

        function markItemDefect(receiptId, itemId) {
            if (confirm('Are you sure you want to mark this item as defect?')) {
                $.ajax({
                    url: '{{ url('receipt-confirmation') }}/' + receiptId + '/items/' + itemId + '/mark-defect',
                    method: 'POST',
                    data: {
                        '_token': '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            updateItemStatus(itemId, 'defect');
                            
                            if (response.all_defect) {
                                alert('All items are marked as defect. Receipt status changed to Defect.');
                                setTimeout(function() {
                                    window.location.href = '{{ route('receipt-confirmation.index') }}';
                                }, 1000);
                            } else if (response.receipt_status === 'defect') {
                                alert('Item marked as defect. Receipt status changed to Defect due to defect items.');
                                setTimeout(function() {
                                    location.reload();
                                }, 1000);
                            } else {
                                alert('Item marked as defect.');
                            }
                        } else {
                            alert('Error: ' + (response.error || 'Unknown error'));
                        }
                    },
                    error: function(xhr) {
                        var errorMsg = 'Unable to mark item as defect';
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            errorMsg = xhr.responseJSON.error;
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        } else if (xhr.status === 404) {
                            errorMsg = 'Route not found. Please check if the route is properly configured.';
                        } else if (xhr.status === 403) {
                            errorMsg = 'You do not have permission to perform this action.';
                        } else if (xhr.status === 500) {
                            errorMsg = 'Server error. Please check if the item_confirmation_status column exists in the database.';
                        }
                        alert(errorMsg);
                        console.error('Error details:', xhr);
                    }
                });
            }
        }

        function updateItem(receiptId, itemId) {
            var row = $('#item-row-' + itemId);
            var price = row.find('.item-price').val();
            var quantity = row.find('.item-quantity').val();
            var discount = row.find('.item-discount').val();
            var shopId = row.find('.item-shop').val();

            if (!price || price <= 0) {
                alert('Please enter a valid price.');
                return;
            }

            if (!quantity || quantity <= 0) {
                alert('Please enter a valid quantity.');
                return;
            }

            $.ajax({
                url: '{{ url('receipt-confirmation') }}/' + receiptId + '/items/' + itemId + '/update',
                method: 'POST',
                data: {
                    '_token': '{{ csrf_token() }}',
                    'harga_jual': price,
                    'jumlah': quantity,
                    'diskon': discount || 0,
                    'shop_id': shopId
                },
                success: function(response) {
                    if (response.success) {
                        alert('Item updated successfully.');
                        location.reload();
                    } else {
                        alert('Error: ' + (response.error || 'Unknown error'));
                    }
                },
                error: function(xhr) {
                    var errorMsg = 'Unable to update item';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMsg = xhr.responseJSON.error;
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    alert(errorMsg);
                }
            });
        }

        function updateItemStatus(itemId, status) {
            var badge = $('#status-badge-' + itemId);
            var confirmBtn = $('#confirm-btn-' + itemId);
            var defectBtn = $('#defect-btn-' + itemId);
            
            var badgeClass = {
                'pending': 'warning',
                'confirmed': 'success',
                'defect': 'danger'
            }[status] || 'secondary';
            
            badge.removeClass('label-warning label-success label-danger')
                 .addClass('label-' + badgeClass)
                 .text(status.charAt(0).toUpperCase() + status.slice(1));
            
            if (status === 'confirmed') {
                confirmBtn.prop('disabled', true);
                defectBtn.prop('disabled', false);
            } else if (status === 'defect') {
                confirmBtn.prop('disabled', false);
                defectBtn.prop('disabled', true);
            } else {
                confirmBtn.prop('disabled', false);
                defectBtn.prop('disabled', false);
            }
        }

        // Auto-calculate subtotal when price, quantity, or discount changes
        $(document).on('change', '.item-price, .item-quantity, .item-discount', function() {
            var row = $(this).closest('tr');
            var price = parseFloat(row.find('.item-price').val()) || 0;
            var quantity = parseInt(row.find('.item-quantity').val()) || 0;
            var discount = parseFloat(row.find('.item-discount').val()) || 0;
            
            var subtotal = price * quantity;
            if (discount > 0) {
                subtotal = subtotal - (subtotal * discount / 100);
            }
            
            var itemId = row.find('.item-price').data('id');
            row.find('.item-subtotal[data-id="' + itemId + '"]').text('Ksh ' + subtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        });

        function confirmReceipt(id) {
            if (confirm('Are you sure you want to confirm this receipt?')) {
                var url = '{{ url('receipt-confirmation') }}/' + id + '/confirm';
                $.ajax({
                    url: url,
                    method: 'POST',
                    data: {
                        '_token': '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Receipt confirmed successfully.');
                            location.reload();
                        } else {
                            alert('Error: ' + (response.error || 'Unknown error'));
                        }
                    },
                    error: function(xhr) {
                        var errorMsg = 'Unable to confirm receipt';
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            errorMsg = xhr.responseJSON.error;
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        } else if (xhr.status === 404) {
                            errorMsg = 'Route not found. Please check if the route is properly configured.';
                        } else if (xhr.status === 403) {
                            errorMsg = 'You do not have permission to perform this action.';
                        } else if (xhr.status === 500) {
                            errorMsg = 'Server error. Please check if the confirmation_status column exists in the database.';
                        }
                        alert(errorMsg);
                        console.error('Error details:', xhr);
                    }
                });
            }
        }

        function markDefect(id) {
            if (confirm('Are you sure you want to mark this receipt as defect?')) {
                var url = '{{ url('receipt-confirmation') }}/' + id + '/mark-defect';
                $.ajax({
                    url: url,
                    method: 'POST',
                    data: {
                        '_token': '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Receipt marked as defect.');
                            location.reload();
                        } else {
                            alert('Error: ' + (response.error || 'Unknown error'));
                        }
                    },
                    error: function(xhr) {
                        var errorMsg = 'Unable to mark receipt as defect';
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            errorMsg = xhr.responseJSON.error;
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        } else if (xhr.status === 404) {
                            errorMsg = 'Route not found. Please check if the route is properly configured.';
                        } else if (xhr.status === 403) {
                            errorMsg = 'You do not have permission to perform this action.';
                        } else if (xhr.status === 500) {
                            errorMsg = 'Server error. Please check if the confirmation_status column exists in the database.';
                        }
                        alert(errorMsg);
                        console.error('Error details:', xhr);
                    }
                });
            }
        }
    </script>
@endpush

