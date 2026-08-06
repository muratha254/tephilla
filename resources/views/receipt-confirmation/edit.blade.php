@extends('layouts.master')

@section('title')
    Edit Receipt
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
@endpush

@section('breadcrumb')
    @parent
    <li><a href="{{ route('receipt-confirmation.index') }}">Receipt Confirmation</a></li>
    <li class="active">Edit Receipt</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Edit Receipt - {{ $penjualan->receiptno }}</h3>
                    <div class="box-tools pull-right">
                        <a href="{{ route('receipt-confirmation.show', $penjualan->id_penjualan) }}" class="btn btn-default btn-sm">
                            <i class="fa fa-arrow-left"></i> Back to Details
                        </a>
                    </div>
                </div>

                <form id="edit-receipt-form" method="POST" action="{{ route('receipt-confirmation.update', $penjualan->id_penjualan) }}">
                    @csrf
                    @method('PUT')
                    
                    <div class="box-body">
                        <!-- Receipt Information -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="receiptno">Receipt Number</label>
                                    <input type="text" class="form-control" id="receiptno" name="receiptno" value="{{ $penjualan->receiptno }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="saledate">Date of Sale</label>
                                    <input type="text" class="form-control datepicker" id="saledate" name="saledate" value="{{ $penjualan->saledate ? \Carbon\Carbon::parse($penjualan->saledate)->format('d/m/Y') : '' }}" required placeholder="dd/mm/yyyy">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="total_item">Total Items</label>
                                    <input type="number" class="form-control" id="total_item" name="total_item" value="{{ $penjualan->total_item }}" required min="1">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="total_harga">Total Price</label>
                                    <input type="number" class="form-control" id="total_harga" name="total_harga" value="{{ $penjualan->total_harga }}" required min="0" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="bayar">Amount Paid</label>
                                    <input type="number" class="form-control" id="bayar" name="bayar" value="{{ $penjualan->bayar }}" required min="0" step="0.01">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="diskon">Discount (%)</label>
                                    <input type="number" class="form-control" id="diskon" name="diskon" value="{{ $penjualan->diskon }}" min="0" max="100">
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label>Current Status</label>
                                    <div>
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
                                        @if($status === 'defect')
                                            <small class="text-muted">(After editing, status will change to Pending)</small>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Items Sold (Read-only) -->
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
                    </div>

                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> Update Receipt
                        </button>
                        <a href="{{ route('receipt-confirmation.show', $penjualan->id_penjualan) }}" class="btn btn-default">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <script>
        $(function () {
            $('.datepicker').datepicker({
                format: 'dd/mm/yyyy',
                autoclose: true
            });

            $('#edit-receipt-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                formData += '&_method=PUT';
                
                $.ajax({
                    url: $(this).attr('action'),
                    method: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            alert('Receipt updated successfully.');
                            window.location.href = '{{ route('receipt-confirmation.index') }}';
                        } else {
                            alert('Error: ' + (response.error || 'Unknown error'));
                        }
                    },
                    error: function(xhr) {
                        var errorMsg = 'Unable to update receipt.';
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            errorMsg = xhr.responseJSON.error;
                        }
                        alert(errorMsg);
                    }
                });
            });
        });
    </script>
@endpush

