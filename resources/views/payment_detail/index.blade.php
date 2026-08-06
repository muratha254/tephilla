@extends('layouts.master')

@section('title')
    Payment
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Payment Transaction</li>
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <div class="box-tools pull-right">
                    <a href="{{ route('payment.pending') }}" class="btn btn-primary btn-sm btn-flat" title="Return to Consignment List (pending)">
                        <i class="fa fa-arrow-left"></i> Back to consignment list
                    </a>
                </div>
                <table>
                    <tr>
                        <td>Supplier</td>
                        <td> : {{ $supplier->nama ?? '' }}</td>
                    </tr>
                    <tr>
                        <td>Telephone</td>
                        <td> : {{ $supplier->telepon ?? '' }}</td>
                    </tr>
                    <tr>
                        <td>Address</td>
                        <td> : {{ $supplier->alamat ?? '' }}</td>
                    </tr>
                </table>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-lg-12">
                        <!-- Hidden supplier information -->
                        <input type="hidden" id="supplier_id" name="supplier_id" value="{{ $supplier->id_supplier }}">
                        <input type="hidden" id="supplier_name" value="{{ $supplier->nama }}">
                        
                        <div class="form-group">
                            <label for="totalrp" class="col-lg-2 control-label">Total Outstanding</label>
                            <div class="col-lg-4">
                                <input type="text" id="totalrp" class="form-control" 
                                    data-value="{{ isset($totalOutstandingWithOpening) ? $totalOutstandingWithOpening : '0.00' }}"
                                    value="KSH {{ isset($totalOutstandingWithOpening) ? number_format($totalOutstandingWithOpening, 2) : '0.00' }}" 
                                    readonly>
                            </div>
                        </div>
                        @if(isset($openingBalanceOutstanding) && $openingBalanceOutstanding > 0)
                        <div class="form-group">
                            <label class="col-lg-2 control-label">Opening Balance</label>
                            <div class="col-lg-4">
                                <input type="text" class="form-control" 
                                    value="KSH {{ number_format($openingBalanceOutstanding, 2) }}" 
                                    readonly style="background-color: #f0f0f0;">
                                <small class="text-muted">Outstanding opening balance: KSH {{ number_format($openingBalanceOutstanding, 2) }}</small>
                            </div>
                        </div>
                        @endif
                        <div class="form-group">
                            <label for="paymentDate" class="col-lg-2 control-label">Payment Date</label>
                            <div class="col-lg-4">
                                <input type="text" id="paymentDate" name="paymentDate" class="form-control datepicker" value="{{ date('d/m/Y') }}" required placeholder="dd/mm/yyyy">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="startDate" class="col-lg-2 control-label">Start Date <span class="text-danger">*</span></label>
                            <div class="col-lg-4">
                                <input type="text" id="startDate" name="startDate" class="form-control datepicker" value="{{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d/m/Y') : (request('start_date') ? \Carbon\Carbon::parse(request('start_date'))->format('d/m/Y') : date('d/m/Y')) }}" required placeholder="dd/mm/yyyy">
                                <small class="text-muted">Filter items from this date</small>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="endDate" class="col-lg-2 control-label">End Date <span class="text-danger">*</span></label>
                            <div class="col-lg-4">
                                <input type="text" id="endDate" name="endDate" class="form-control datepicker" value="{{ $endDate ? \Carbon\Carbon::parse($endDate)->format('d/m/Y') : (request('end_date') ? \Carbon\Carbon::parse(request('end_date'))->format('d/m/Y') : date('d/m/Y')) }}" required placeholder="dd/mm/yyyy">
                                <small class="text-muted">Filter items until this date</small>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="paymentMode" class="col-lg-2 control-label">Payment Mode</label>
                            <div class="col-lg-4">
                                <select id="paymentMode" name="paymentMode" class="form-control" required>
                                    <option value="">Select Payment Mode</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Mpesa">Mpesa</option>
                                    <option value="Cheque">Cheque</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="bulkPayment" class="col-lg-2 control-label">Payment Amount</label>
                            <div class="col-lg-4">
                                <input type="number" id="bulkPayment" class="form-control" min="0" step="0.01" value="0">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-lg-12">
                        <table class="table table-striped table-bordered" id="payment-table">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Product</th>
                                    <th>Shop</th>
                                    <th>Amount</th>
                                    <th>Outstanding Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if(isset($openingBalanceOutstanding) && $openingBalanceOutstanding > 0)
                                <tr data-is-opening-balance="true" 
                                    data-original-balance="{{ $openingBalanceOutstanding }}"
                                    style="background-color: #f9f9f9;">
                                    <td><strong>Opening Balance</strong></td>
                                    <td><em>Initial Balance</em></td>
                                    <td><em>-</em></td>
                                    <td class="original-amount">{{ number_format($openingBalanceOutstanding, 2) }}</td>
                                    <td class="balance">{{ number_format($openingBalanceOutstanding, 2) }}</td>
                                </tr>
                                @endif
                                @foreach($invoiceItems as $item)
                                    <tr data-invoice-id="{{ $item->invoice_id }}" 
                                        data-item-id="{{ $item->id }}" 
                                        data-produk-id="{{ $item->produk_id }}"
                                        data-created-at="{{ $item->created_at ? date('Y-m-d', strtotime($item->created_at)) : '' }}"
                                        data-original-balance="{{ $item->outstanding_balance }}">
                                        <td>{{ $item->uniqid }}</td>
                                        <td>{{ $item->nama_produk }}</td>
                                        <td>{{ $item->shop_name ?? 'N/A' }}</td>
                                        <td class="original-amount">{{ number_format($item->amount, 2) }}</td>
                                        <td class="balance">{{ number_format($item->outstanding_balance, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-right"><strong>Total Outstanding:</strong></td>
                                    <td><strong>{{ number_format($totalOutstandingWithOpening ?? $totalOutstanding, 2) }}</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <button type="button" class="btn btn-default btn-lg btn-block" id="btn-payment-preview-pdf" style="margin-bottom:10px;">
                            <i class="fa fa-file-pdf-o"></i> Export PDF preview (items to be paid — same layout as after payment)
                        </button>
                        <button type="button" class="btn btn-primary btn-lg btn-block btn-simpan">
                            Process Payment
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
<script>
$(document).ready(function () {
    console.log('Payment detail page loaded');
    
    // Initialize datepicker with dd/mm/yyyy format
    $('.datepicker').datepicker({
        format: 'dd/mm/yyyy',
        autoclose: true,
        todayHighlight: true
    });
    
    // Test button click handler attachment
    console.log('Button count:', $('.btn-simpan').length);
    if ($('.btn-simpan').length === 0) {
        console.error('Process Payment button not found!');
    }

    // Function to filter table by date range
    function filterTableByDateRange() {
        // Ensure original balances are stored from ALL rows (before any distribution)
        if (originalBalances.length === 0) {
            // Store from current balances (which should be original on first load)
            storeOriginalBalances();
        } else {
            // Restore all balances to original before filtering
            restoreOriginalBalances();
        }
        
        var startDate = $('#startDate').val();
        var endDate = $('#endDate').val();
        
        if (!startDate || !endDate) {
            // If dates are not set, show all rows
            $('#payment-table tbody tr').show();
            // Recalculate total from all visible rows
            updateTotalOutstanding();
            return;
        }
        
        // Convert dd/mm/yyyy to Date object
        function parseDate(dateStr) {
            var parts = dateStr.split('/');
            if (parts.length === 3) {
                return new Date(parts[2], parts[1] - 1, parts[0]);
            }
            return new Date(dateStr); // Fallback
        }
        
        var start = parseDate(startDate);
        var end = parseDate(endDate);
        end.setHours(23, 59, 59, 999); // Include the entire end date
        
        // Filter rows based on date range
        $('#payment-table tbody tr').each(function() {
            var $row = $(this);
            var rowDateStr = $row.data('created-at');
            
            // Always show opening balance row
            if ($row.data('is-opening-balance') === true) {
                $row.show();
                return;
            }
            
            if (rowDateStr) {
                var rowDate = new Date(rowDateStr);
                // Show row if date is within range
                if (rowDate >= start && rowDate <= end) {
                    $row.show();
                } else {
                    $row.hide();
                }
            } else {
                // If no date, hide the row
                $row.hide();
            }
        });
        
        // Recalculate total from visible rows and auto-fill payment amount
        updateTotalOutstanding();
    }
    
    // Store original balances before any distribution
    var originalBalances = [];
    
    // Function to store original balances from ALL rows (not just visible)
    // This ensures we can restore balances correctly even after filtering
    function storeOriginalBalances() {
        originalBalances = [];
        $('#payment-table tbody tr').each(function() {
            var $row = $(this);
            // Try to get from data attribute first, then from text
            var balance = parseFloat($row.data('original-balance')) || 0;
            if (balance === 0) {
                var balanceText = $row.find('.balance').text().replace(/,/g, '');
                balance = parseFloat(balanceText) || 0;
                // Store it in data attribute for future use
                $row.data('original-balance', balance);
            }
            originalBalances.push(balance);
        });
        console.log('Stored original balances:', originalBalances);
    }
    
    // Function to restore original balances
    function restoreOriginalBalances() {
        $('#payment-table tbody tr').each(function(index) {
            var $row = $(this);
            if (originalBalances[index] !== undefined) {
                var balance = originalBalances[index];
                $row.find('.balance').text(balance.toFixed(2));
                // Also update data attribute
                $row.data('original-balance', balance);
            }
        });
    }
    
    // Function to update total outstanding based on visible rows
    function updateTotalOutstanding() {
        // Ensure original balances are stored from ALL rows
        if (originalBalances.length === 0) {
            storeOriginalBalances();
        }
        
        // Restore all balances to original before calculating
        restoreOriginalBalances();
        
        var total = 0;
        // Calculate total from visible rows using their original balances (by row index)
        $('#payment-table tbody tr:visible').each(function() {
            var $row = $(this);
            var rowIndex = $row.index();
            // Get original balance from data attribute or array
            var balance = parseFloat($row.data('original-balance')) || 0;
            if (balance === 0 && originalBalances[rowIndex] !== undefined) {
                balance = originalBalances[rowIndex];
            }
            total += balance;
        });
        
        // Update currentBalances with visible rows' original balances
        currentBalances = [];
        $('#payment-table tbody tr:visible').each(function() {
            var rowIndex = $(this).index();
            if (originalBalances[rowIndex] !== undefined) {
                currentBalances.push(originalBalances[rowIndex]);
            } else {
                currentBalances.push(0);
            }
        });
        
        // Update footer total
        var totalFormatted = total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        $('#payment-table tfoot td:last').text(totalFormatted);
        
        // Update the Total Outstanding input field
        $('#totalrp').val('KSH ' + totalFormatted);
        $('#totalrp').data('value', total);
        
        // Auto-fill Payment Amount with Total Outstanding and distribute
        if (total > 0) {
            $('#bulkPayment').val(total.toFixed(2));
            distributePayment(total);
        } else {
            $('#bulkPayment').val('0');
        }
    }
    
    // Attach change event handlers to date inputs
    $('#startDate, #endDate').on('change', function() {
        // Restore original balances first (in case they were distributed)
        if (originalBalances.length > 0) {
            restoreOriginalBalances();
        } else {
            // If original balances not stored yet, store them now
            storeOriginalBalances();
        }
        
        filterTableByDateRange();
    });
    
    // Initialize DataTable
    var table = $('#payment-table').DataTable({
        responsive: true,
        processing: true,
        serverSide: false,
        info: true,
        ordering: false,
        paging: false
    });
    
    // Auto-fill Payment Amount with Total Outstanding on page load
    // This happens after filterTableByDateRange() which is called in the document.ready above
    setTimeout(function() {
        var initialTotalOutstanding = parseFloat($('#totalrp').data('value')) || 0;
        if (initialTotalOutstanding > 0) {
            $('#bulkPayment').val(initialTotalOutstanding.toFixed(2));
            // Trigger input event to distribute payment
            $('#bulkPayment').trigger('input');
        }
    }, 200);

    // Store current outstanding balances - will be updated by filterTableByDateRange
    var currentBalances = [];
    
    // Initialize currentBalances from all visible rows (before any distribution)
    function initializeCurrentBalances() {
        // Store original balances first
        storeOriginalBalances();
        currentBalances = originalBalances.slice(); // Copy array
    }
    
    // Initialize on page load (before filterTableByDateRange which may distribute)
    initializeCurrentBalances();

    function distributePayment(totalPayment) {
        var remaining = parseFloat(totalPayment) || 0;
        var totalDistributed = 0;
        
        // Use originalBalances if available, otherwise use currentBalances
        var balancesToUse = (originalBalances.length > 0) ? originalBalances : currentBalances;
        
        $('.balance').each(function(index) {
            var $row = $(this).closest('tr');
            if (!$row.is(':visible')) {
                return; // Skip hidden rows
            }
            
            var balance = balancesToUse[index] || 0;
            if (balance > 0 && remaining > 0) {
                var payment = Math.min(balance, remaining);
                // Round payment to 2 decimal places
                payment = Math.round(payment * 100) / 100;
                var newBalance = Math.max(0, balance - payment);
                $(this).text(newBalance.toFixed(2));
                remaining = Math.round((remaining - payment) * 100) / 100;
                totalDistributed = Math.round((totalDistributed + payment) * 100) / 100;
            }
        });
        
        // If there's a very small remaining amount due to rounding (less than 0.01), 
        // add it to the last item with a balance
        if (remaining > 0 && remaining < 0.01) {
            var lastBalance = $('.balance').filter(function() {
                return parseFloat($(this).text()) > 0;
            }).last();
            if (lastBalance.length > 0) {
                var lastBalanceValue = parseFloat(lastBalance.text()) || 0;
                var adjustedBalance = Math.max(0, lastBalanceValue - remaining);
                lastBalance.text(adjustedBalance.toFixed(2));
            }
        }
    }

    // Handle payment input - use a flag to prevent infinite loops
    var isDistributing = false;
    $('#bulkPayment').on('input', function() {
        if (isDistributing) {
            return; // Prevent recursive calls
        }
        
        isDistributing = true;
        var bulkPayment = parseFloat($(this).val()) || 0;
        var rawValue = $('#totalrp').data('value');
        var totalOutstanding = parseFloat(rawValue) || 0;
        
        console.log('Bulk Payment:', bulkPayment);
        console.log('Raw Value:', rawValue);
        console.log('Total Outstanding:', totalOutstanding);

        if (bulkPayment > totalOutstanding) {
            alert('Payment amount cannot exceed total outstanding: ' + totalOutstanding);
            $(this).val(totalOutstanding);
            bulkPayment = totalOutstanding;
        }

        distributePayment(bulkPayment);
        isDistributing = false;
    });

    /** @param {boolean} forPreview If true, payment mode is optional (defaults to Cash for JSON only). */
    function gatherPaymentPayloadForPaymentPage(forPreview) {
        forPreview = !!forPreview;
        function convertToYMD(dateStr) {
            if (!dateStr) return '';
            var parts = dateStr.split('/');
            if (parts.length === 3) {
                return parts[2] + '-' + parts[1] + '-' + parts[0];
            }
            return dateStr;
        }
        function isValidDateFormat(dateStr) {
            var dateRegex = /^(\d{2})\/(\d{2})\/(\d{4})$/;
            if (!dateRegex.test(dateStr)) {
                return false;
            }
            var parts = dateStr.split('/');
            var day = parseInt(parts[0], 10);
            var month = parseInt(parts[1], 10);
            var year = parseInt(parts[2], 10);
            if (month < 1 || month > 12) return false;
            if (day < 1 || day > 31) return false;
            if (year < 1900 || year > 2100) return false;
            return true;
        }
        var supplierId = $('#supplier_id').val();
        if (!supplierId) {
            alert('Invalid supplier information');
            return null;
        }
        var paymentDateRaw = $('#paymentDate').val();
        if (!paymentDateRaw) {
            alert('Please select a payment date');
            return null;
        }
        if (!isValidDateFormat(paymentDateRaw)) {
            alert('Invalid date format. Please use dd/mm/yyyy format');
            return null;
        }
        var startDateRaw = $('#startDate').val();
        var endDateRaw = $('#endDate').val();
        if (!startDateRaw || !endDateRaw) {
            alert('Please select both Start Date and End Date to filter items for payment.');
            return null;
        }
        if (!isValidDateFormat(startDateRaw) || !isValidDateFormat(endDateRaw)) {
            alert('Invalid date format for date range. Please use dd/mm/yyyy format');
            return null;
        }
        var startDateYMD = convertToYMD(startDateRaw);
        var endDateYMD = convertToYMD(endDateRaw);
        if (startDateYMD > endDateYMD) {
            alert('Start Date cannot be after End Date');
            return null;
        }
        var paymentDate = convertToYMD(paymentDateRaw);
        var startDate = startDateYMD;
        var endDate = endDateYMD;
        var paymentMode = $('#paymentMode').val();
        if (!forPreview) {
            if (!paymentMode || paymentMode === '') {
                alert('Please select a payment mode before processing the payment.');
                $('#paymentMode').focus();
                return null;
            }
        } else {
            paymentMode = paymentMode || 'Cash';
        }
        var bulkPayment = parseFloat($('#bulkPayment').val()) || 0;
        if (bulkPayment <= 0) {
            alert('Please enter a payment amount');
            return null;
        }
        if (originalBalances.length === 0) {
            storeOriginalBalances();
        }
        restoreOriginalBalances();
        currentBalances = originalBalances.slice();
        distributePayment(bulkPayment);
        var invoiceData = [];
        var openingBalancePayment = 0;
        var totalPaymentAmount = 0;
        $('#payment-table tbody tr:visible').each(function() {
            var row = $(this);
            var $balanceCell = row.find('.balance');
            var previousBalance = parseFloat(row.data('original-balance')) || 0;
            var currentBalance = parseFloat($balanceCell.text().replace(/,/g, '')) || 0;
            var amountToPay = Math.round((previousBalance - currentBalance) * 100) / 100;
            if (amountToPay > 0) {
                if (row.data('is-opening-balance') === true) {
                    openingBalancePayment = amountToPay;
                } else {
                    var invoiceId = row.data('invoice-id');
                    var itemId = row.data('item-id');
                    if (invoiceId && itemId) {
                        invoiceData.push({
                            invoice_id: parseInt(invoiceId, 10),
                            item_id: parseInt(itemId, 10),
                            amount_to_pay: amountToPay,
                            remaining_balance: currentBalance
                        });
                    }
                }
                totalPaymentAmount += amountToPay;
            }
        });
        totalPaymentAmount = Math.round(totalPaymentAmount * 100) / 100;
        bulkPayment = Math.round(parseFloat($('#bulkPayment').val()) * 100) / 100;
        var totalOutstanding = parseFloat($('#totalrp').data('value')) || 0;
        if (totalPaymentAmount === 0 || Math.abs(totalPaymentAmount - bulkPayment) > 0.02) {
            invoiceData = [];
            openingBalancePayment = 0;
            totalPaymentAmount = 0;
            $('#payment-table tbody tr:visible').each(function() {
                var row = $(this);
                var $balanceCell = row.find('.balance');
                var currentBalance = parseFloat($balanceCell.text().replace(/,/g, '')) || 0;
                var previousBalance = parseFloat(row.data('original-balance')) || 0;
                var amountToPay = previousBalance - currentBalance;
                amountToPay = Math.round(amountToPay * 100) / 100;
                if (amountToPay > 0 || (previousBalance > 0 && currentBalance === 0)) {
                    if (currentBalance === 0 && previousBalance > 0 && amountToPay <= 0) {
                        amountToPay = previousBalance;
                    }
                    if (amountToPay > 0) {
                        if (row.data('is-opening-balance') === true) {
                            openingBalancePayment = amountToPay;
                        } else {
                            var invoiceId = row.data('invoice-id');
                            var itemId = row.data('item-id');
                            if (invoiceId && itemId) {
                                invoiceData.push({
                                    invoice_id: parseInt(invoiceId, 10),
                                    item_id: parseInt(itemId, 10),
                                    amount_to_pay: amountToPay,
                                    remaining_balance: currentBalance
                                });
                            }
                        }
                        totalPaymentAmount += amountToPay;
                    }
                }
            });
            totalPaymentAmount = Math.round(totalPaymentAmount * 100) / 100;
        }
        if (totalPaymentAmount === 0 && Math.abs(bulkPayment - totalOutstanding) < 0.02) {
            totalPaymentAmount = bulkPayment;
            if (invoiceData.length === 0 && openingBalancePayment <= 0) {
                $('#payment-table tbody tr:visible').each(function() {
                    var row = $(this);
                    var previousBalance = parseFloat(row.data('original-balance')) || 0;
                    if (previousBalance > 0) {
                        if (row.data('is-opening-balance') === true) {
                            openingBalancePayment = previousBalance;
                        } else {
                            var invoiceId = row.data('invoice-id');
                            var itemId = row.data('item-id');
                            if (invoiceId && itemId) {
                                invoiceData.push({
                                    invoice_id: parseInt(invoiceId, 10),
                                    item_id: parseInt(itemId, 10),
                                    amount_to_pay: previousBalance,
                                    remaining_balance: 0
                                });
                            }
                        }
                    }
                });
            }
        }
        if (Math.abs(totalPaymentAmount - bulkPayment) > 0.02) {
            alert('Payment amounts do not match. Total calculated: ' + totalPaymentAmount.toFixed(2) + ', Entered: ' + bulkPayment.toFixed(2) + '. Please try again.');
            return null;
        }
        if (Math.abs(totalPaymentAmount - bulkPayment) > 0 && Math.abs(totalPaymentAmount - bulkPayment) <= 0.02) {
            bulkPayment = totalPaymentAmount;
            $('#bulkPayment').val(bulkPayment.toFixed(2));
        }
        if (invoiceData.length === 0 && openingBalancePayment <= 0) {
            alert('No payments to process. Please check that items are selected for payment.');
            return null;
        }
        var paymentData = {
            invoices: invoiceData,
            opening_balance_payment: openingBalancePayment,
            totalPay: bulkPayment,
            supplier_id: parseInt(supplierId, 10),
            payment_date: paymentDate,
            payment_method: paymentMode
        };
        return {
            paymentData: paymentData,
            paymentDate: paymentDate,
            startDate: startDate,
            endDate: endDate,
            paymentMode: paymentMode,
            supplierId: supplierId,
            bulkPayment: bulkPayment
        };
    }

    // Use event delegation for better reliability
    $(document).on('click', '.btn-simpan', function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('Process Payment button clicked');
        
        try {
            // Prevent double-clicking
            var $btn = $(this);
            if ($btn.prop('disabled')) {
                console.log('Button already disabled, returning');
                return false;
            }
            
            // Disable button and show loading state
            $btn.prop('disabled', true);
            var originalText = $btn.html();
            $btn.html('<i class="fa fa-spinner fa-spin"></i> Processing...');
            
            console.log('Starting payment validation...');
            var payload = gatherPaymentPayloadForPaymentPage();
            if (!payload) {
                $btn.prop('disabled', false);
                $btn.html(originalText);
                return false;
            }
            var paymentData = payload.paymentData;
            var paymentDate = payload.paymentDate;
            var startDate = payload.startDate;
            var endDate = payload.endDate;
            var paymentMode = payload.paymentMode;
            var supplierId = payload.supplierId;
            var bulkPayment = payload.bulkPayment;

        console.log('Sending payment data:', paymentData);

        // Send AJAX request
        $.ajax({
            url: "{{ route('payment.store') }}",
            type: "POST",
            dataType: 'json',
            timeout: 30000, // 30 second timeout
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                invoice_data: JSON.stringify(paymentData),
                payment_date: paymentDate,
                payment_method: paymentMode,
                paymentMode: paymentMode,
                supplier_id: supplierId,
                total_amount: bulkPayment,
                start_date: startDate,
                end_date: endDate
            },
            success: function(response) {
                console.log('Success response:', response);
                if(response.success) {
                    alert('Payment processed successfully! You will be redirected to view and print the payment details.');
                    if(response.redirect) {
                        window.location.href = response.redirect;
                    } else if(response.payment_id) {
                        window.location.href = "{{ url('/payment') }}/" + response.payment_id + "/view";
                    } else {
                        window.location.href = "{{ route('payment.index') }}";
                    }
                } else {
                    alert('Error processing payment: ' + (response.message || 'Unknown error'));
                    $btn.prop('disabled', false);
                    $btn.html(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('Payment error details:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                $btn.prop('disabled', false);
                $btn.html(originalText);
                
                if (status === 'timeout') {
                    alert('Request timed out. Please try again.');
                } else {
                    try {
                        var errorResponse = JSON.parse(xhr.responseText);
                        alert('Error: ' + (errorResponse.message || error));
                    } catch(e) {
                        alert('Error processing payment: ' + error);
                    }
                }
            }
        });
        } catch(error) {
            console.error('Error in payment processing:', error);
            alert('An error occurred: ' + error.message);
            var $btn = $('.btn-simpan');
            $btn.prop('disabled', false);
            $btn.html('Process Payment');
        }
    });

    $(document).on('click', '#btn-payment-preview-pdf', function(e) {
        e.preventDefault();
        var payload = gatherPaymentPayloadForPaymentPage(true);
        if (!payload) {
            return;
        }
        var token = $('meta[name="csrf-token"]').attr('content');
        var form = $('<form>', { method: 'POST', action: "{{ route('payment.preview-pdf', $supplier->id_supplier) }}", target: '_blank' });
        form.append($('<input>', { type: 'hidden', name: '_token', value: token }));
        form.append($('<input>', { type: 'hidden', name: 'invoice_data', value: JSON.stringify(payload.paymentData) }));
        form.append($('<input>', { type: 'hidden', name: 'start_date', value: payload.startDate }));
        form.append($('<input>', { type: 'hidden', name: 'end_date', value: payload.endDate }));
        form.append($('<input>', { type: 'hidden', name: 'payment_date', value: payload.paymentDate }));
        $('body').append(form);
        form[0].submit();
        form.remove();
    });
    
    console.log('Payment detail scripts loaded');
});
</script>
@endpush

