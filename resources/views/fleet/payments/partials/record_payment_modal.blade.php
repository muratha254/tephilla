<div class="fleet-modal" id="record-payment-modal" hidden>
    <div class="fleet-modal-backdrop" data-close-record-payment-modal></div>
    <div class="fleet-modal-dialog fleet-payments-modal-dialog">
        <div class="fleet-modal-header">
            <h3 id="record-payment-modal-title">Record Payment</h3>
            <button type="button" class="fleet-modal-close" data-close-record-payment-modal aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="{{ route('payments.store') }}" class="fleet-payments-form" id="modal-payment-form">
            @csrf
            <div class="fleet-modal-body">
                <div class="fleet-alert fleet-alert-error" id="modal-payment-errors" @if(!$errors->any()) hidden @endif>
                    @if ($errors->any())
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <input type="hidden" id="modal-payment-customer-id" value="{{ $retryCustomerId ?: $customerFilter }}">

                <div class="fleet-form-group" id="modal-payment-customer-group">
                    <label>Customer <span class="required">*</span></label>
                    <select id="modal-payment-customer-select" class="fleet-input" required>
                        <option value="">Select Customer</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" {{ (int) ($retryCustomerId ?: $customerFilter) === $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="fleet-payments-modal-customer" id="modal-payment-customer-summary" hidden>
                    <span>Customer</span>
                    <strong id="modal-payment-customer-name">-</strong>
                </div>

                <div id="modal-payment-customer-balance" class="fleet-payments-customer-balance" hidden>
                    <div><span>Total Invoiced</span><strong id="modal-payment-customer-invoiced">-</strong></div>
                    <div><span>Amount Paid</span><strong id="modal-payment-customer-paid" class="is-paid">-</strong></div>
                    <div><span>Outstanding Balance</span><strong id="modal-payment-customer-outstanding" class="is-outstanding">-</strong></div>
                </div>

                <div class="fleet-form-group">
                    <label>Trip / Invoice <span class="required">*</span></label>
                    <select name="fleet_trip_id" id="modal-payment-trip-select" class="fleet-input" required disabled>
                        <option value="">Loading trips...</option>
                    </select>
                    <p class="fleet-field-help" id="modal-payment-trip-help">Only trips with an outstanding balance are listed.</p>
                </div>

                <div id="modal-payment-trip-balance" class="fleet-payments-trip-balance" hidden>
                    <div><span>Trip Total</span><strong id="modal-payment-trip-total">-</strong></div>
                    <div><span>Already Paid</span><strong id="modal-payment-trip-paid">-</strong></div>
                    <div><span>Remaining</span><strong id="modal-payment-trip-remaining">-</strong></div>
                </div>

                <div class="fleet-form-group">
                    <label>Payment Date <span class="required">*</span></label>
                    <input type="date" name="payment_date" class="fleet-input" value="{{ old('payment_date', now()->format('Y-m-d')) }}" required>
                </div>

                <div class="fleet-form-group">
                    <label>Amount (KSh) <span class="required">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="modal-payment-amount" class="fleet-input" value="{{ old('amount') }}" placeholder="0.00" required>
                    <p class="fleet-field-help" id="modal-payment-amount-help">Pre-filled with the trip remaining balance. You can change the amount if needed.</p>
                </div>

                <div class="fleet-form-group">
                    <label>Payment Method <span class="required">*</span></label>
                    <select name="payment_method" class="fleet-input" required>
                        @foreach ($paymentMethods as $method)
                            <option value="{{ $method }}" {{ old('payment_method', 'Cash') === $method ? 'selected' : '' }}>{{ $method }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="fleet-form-group">
                    <label>Reference Number</label>
                    <input type="text" name="reference_no" class="fleet-input" value="{{ old('reference_no') }}" placeholder="M-Pesa / receipt code">
                </div>

                <div class="fleet-form-group">
                    <label>Notes</label>
                    <input type="text" name="notes" class="fleet-input" value="{{ old('notes') }}" placeholder="Deposit, partial payment, etc.">
                </div>

                <label class="fleet-trip-payment-receipt-toggle">
                    <input type="checkbox" name="open_receipt" value="1" {{ old('open_receipt', '1') ? 'checked' : '' }}>
                    <span>Open printable receipt after saving</span>
                </label>
            </div>
            <div class="fleet-modal-footer">
                <button type="button" class="fleet-btn fleet-btn-light" data-close-record-payment-modal>Cancel</button>
                <button type="submit" class="fleet-btn fleet-btn-primary">
                    <i class="fa fa-check"></i> Record Payment
                </button>
            </div>
        </form>
    </div>
</div>
