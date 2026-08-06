@extends('layouts.master')

@section('title')
    Supplier Withdrawal - {{ $supplier->nama }}
@endsection

@section('breadcrumb')
    @parent
    <li><a href="{{ route('supplier.index') }}">Supplier List</a></li>
    <li class="active">Withdrawal</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Withdraw Items - {{ $supplier->nama }}</h3>
                <div class="box-tools">
                    <a href="{{ route('supplier.index') }}" class="btn btn-default btn-sm">
                        <i class="fa fa-arrow-left"></i> Back to Suppliers
                    </a>
                </div>
            </div>
            <div class="box-body">
                <!-- Supplier Info -->
                <div class="row" style="margin-bottom: 20px;">
                    <div class="col-md-6">
                        <h4>Supplier Information</h4>
                        <table class="table table-bordered">
                            <tr>
                                <th width="30%">Name:</th>
                                <td>{{ $supplier->nama }}</td>
                            </tr>
                            <tr>
                                <th>Phone:</th>
                                <td>{{ $supplier->telepon ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Address:</th>
                                <td>{{ $supplier->alamat ?? 'N/A' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Withdrawal Form -->
                <form id="withdrawal-form" method="POST" action="{{ route('supplier.withdrawal.process') }}">
                    @csrf
                    <input type="hidden" name="supplier_id" value="{{ $supplier->id_supplier }}">
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="withdrawal_date">Withdrawal Date <span class="text-danger">*</span></label>
                                <input type="date" name="withdrawal_date" id="withdrawal_date" 
                                       class="form-control" value="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="reason">Reason</label>
                                <input type="text" name="reason" id="reason" class="form-control" 
                                       placeholder="e.g., Return, Defective, etc.">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="2" 
                                  placeholder="Additional notes about this withdrawal"></textarea>
                    </div>

                    <hr>

                    <!-- Products Selection -->
                    <h4>Select Products to Withdraw</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="products-table">
                            <thead>
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="15%">Product Code</th>
                                    <th width="25%">Product Name</th>
                                    <th width="15%">Shop</th>
                                    <th width="10%">Available Stock</th>
                                    <th width="15%">Quantity to Withdraw</th>
                                    <th width="15%">Unit Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($products as $index => $product)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $product->kode_produk }}</td>
                                    <td>{{ $product->nama_produk }}</td>
                                    <td>{{ $product->shop->shop_name ?? 'N/A' }}</td>
                                    <td class="text-center">
                                        <span class="label label-success">{{ $product->stok }}</span>
                                    </td>
                                    <td>
                                        <input type="hidden" name="withdrawals[{{ $index }}][produk_id]" 
                                               value="{{ $product->id_produk }}">
                                        <input type="number" 
                                               name="withdrawals[{{ $index }}][quantity]" 
                                               class="form-control withdrawal-quantity" 
                                               min="0" 
                                               max="{{ $product->stok }}" 
                                               value="0"
                                               data-produk-id="{{ $product->id_produk }}"
                                               data-available-stock="{{ $product->stok }}"
                                               data-unit-price="{{ $product->harga_beli ?? 0 }}">
                                    </td>
                                    <td class="text-right">
                                        Ksh {{ number_format($product->harga_beli ?? 0, 2) }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">
                                        <i class="fa fa-info-circle"></i> No products available for withdrawal. 
                                        All products from this supplier are out of stock.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="row" style="margin-top: 20px;">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <strong><i class="fa fa-info-circle"></i> Note:</strong> 
                                Only products with available stock are shown. The system will automatically 
                                deduct the withdrawn quantities from your inventory.
                            </div>
                        </div>
                    </div>

                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary btn-flat" id="btn-submit">
                            <i class="fa fa-check"></i> Process Withdrawal
                        </button>
                        <a href="{{ route('supplier.index') }}" class="btn btn-default btn-flat">
                            <i class="fa fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(function () {
        // Validate form before submission
        $('#withdrawal-form').on('submit', function(e) {
            e.preventDefault();
            
            // Check if at least one product has quantity > 0
            let hasWithdrawal = false;
            $('.withdrawal-quantity').each(function() {
                if (parseInt($(this).val()) > 0) {
                    hasWithdrawal = true;
                    return false;
                }
            });

            if (!hasWithdrawal) {
                alert('Please select at least one product to withdraw.');
                return false;
            }

            // Validate quantities
            let isValid = true;
            $('.withdrawal-quantity').each(function() {
                const quantity = parseInt($(this).val());
                const maxStock = parseInt($(this).data('available-stock'));
                
                if (quantity > maxStock) {
                    alert('Quantity cannot exceed available stock for product.');
                    $(this).focus();
                    isValid = false;
                    return false;
                }
            });

            if (!isValid) {
                return false;
            }

            // Confirm before submission
            if (!confirm('Are you sure you want to process this withdrawal? This action will deduct items from your stock.')) {
                return false;
            }

            // Disable submit button
            $('#btn-submit').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

            // Filter out items with quantity 0 by disabling their inputs
            // This way they won't be included in form serialization
            $('.withdrawal-quantity').each(function() {
                const quantity = parseInt($(this).val()) || 0;
                if (quantity === 0) {
                    // Disable quantity and corresponding produk_id inputs for items with 0 quantity
                    $(this).prop('disabled', true);
                    $(this).closest('tr').find('input[name*="[produk_id]"]').prop('disabled', true);
                }
            });

            // Submit form with filtered data
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    // Re-enable all inputs
                    $('.withdrawal-quantity, input[name*="[produk_id]"]').prop('disabled', false);
                    
                    // Show success message
                    alert('Withdrawal processed successfully! Withdrawal Number: ' + response.withdrawal_number);
                    
                    // Reset form quantities to 0
                    $('.withdrawal-quantity').val(0);
                    
                    // Remove warning class from rows
                    $('.withdrawal-quantity').closest('tr').removeClass('warning');
                    
                    // Reload the page to refresh product stock
                    window.location.reload();
                },
                error: function(xhr) {
                    // Re-enable all inputs so form can be resubmitted
                    $('.withdrawal-quantity, input[name*="[produk_id]"]').prop('disabled', false);
                    
                    let errorMessage = 'An error occurred while processing the withdrawal.';
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        } else if (xhr.responseJSON.errors) {
                            // Handle validation errors
                            const errors = xhr.responseJSON.errors;
                            let errorMessages = [];
                            for (let field in errors) {
                                errorMessages.push(errors[field].join('\n'));
                            }
                            errorMessage = 'Validation errors:\n' + errorMessages.join('\n');
                        }
                    }
                    alert(errorMessage);
                    $('#btn-submit').prop('disabled', false).html('<i class="fa fa-check"></i> Process Withdrawal');
                }
            });
        });

        // Highlight rows with quantities > 0
        $('.withdrawal-quantity').on('input', function() {
            const quantity = parseInt($(this).val()) || 0;
            const row = $(this).closest('tr');
            
            if (quantity > 0) {
                row.addClass('warning');
            } else {
                row.removeClass('warning');
            }
        });
    });
</script>
@endpush


