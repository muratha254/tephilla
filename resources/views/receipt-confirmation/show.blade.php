@extends('layouts.master')

@section('title')
    Receipt Details
@endsection

@section('breadcrumb')
    @parent
    <li><a href="{{ route('receipt-confirmation.index') }}">Receipt Confirmation</a></li>
    <li class="active">Receipt Details</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Receipt Details - {{ $penjualan->receiptno }}</h3>
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
                                <tr>
                                    <th>Discount</th>
                                    <td>{{ $penjualan->getDiscountDisplayLabel() }}</td>
                                </tr>
                                <tr>
                                    <th>Payment Method</th>
                                    <td>{{ $penjualan->payment_method ?? 'Cash' }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">Status</th>
                                    <td>
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
                                <tr>
                                    <th>Shop Names</th>
                                    <td>
                                        @php
                                            $shops = $penjualan->details->map(function($detail) {
                                                return $detail->produk->shop->shop_name ?? 'N/A';
                                            })->unique()->values();
                                        @endphp
                                        {{ $shops->implode(', ') }}
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Items Sold -->
                    <div class="row" style="margin-top: 20px;">
                        <div class="col-md-12">
                            <h4>Items Sold</h4>
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Product Name</th>
                                        <th>Shop Code</th>
                                        <th>Product Code</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($penjualan->details as $index => $detail)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td><span class="label label-success" style="display:inline-block;max-width:100%;white-space:normal;text-align:left;">{{ $detail->produk->nama_produk ?? 'N/A' }}</span></td>
                                            <td><span class="label label-info">{{ $detail->produk->shop->shop_code ?? 'N/A' }}</span></td>
                                            <td>{{ $detail->produk->kode_produk ?? $detail->produk->item_code ?? 'N/A' }}</td>
                                            <td>Ksh {{ format_uang($detail->harga_jual) }}</td>
                                            <td>{{ format_uang($detail->jumlah) }}</td>
                                            <td>Ksh {{ format_uang($detail->subtotal) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="row" style="margin-top: 20px;">
                        <div class="col-md-12">
                            @if(!empty($waitingUpdate))
                                <div class="alert alert-warning">
                                    <i class="fa fa-clock-o"></i> <strong>Quick Add pending.</strong>
                                    This receipt has item(s) still in <a href="{{ route('produk.incomplete') }}">Products → Quick Add</a>.
                                    Confirmation is disabled until those items are completed or removed.
                                </div>
                            @endif

                            @php
                                $status = $penjualan->confirmation_status ?? 'pending';
                            @endphp
                            
                            @if($status !== 'confirmed' && empty($waitingUpdate))
                                <button onclick="confirmReceipt({{ $penjualan->id_penjualan }})" class="btn btn-success">
                                    <i class="fa fa-check"></i> Confirm Receipt
                                </button>
                            @endif
                            
                            @if($status !== 'defect')
                                <button onclick="markDefect({{ $penjualan->id_penjualan }})" class="btn btn-danger">
                                    <i class="fa fa-exclamation-triangle"></i> Mark as Defect
                                </button>
                            @endif
                            
                            @if($status !== 'confirmed')
                                <a href="{{ route('receipt-confirmation.edit', $penjualan->id_penjualan) }}" class="btn btn-warning">
                                    <i class="fa fa-edit"></i> Edit Receipt
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function confirmReceipt(id) {
            @if(!empty($waitingUpdate))
            alert('This receipt has item(s) still in Quick Add. Complete or remove them in Products → Quick Add before confirming.');
            return;
            @endif
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

