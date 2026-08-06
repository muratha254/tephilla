<div class="fleet-modal" id="trip-record-payment-modal" hidden>
    <div class="fleet-modal-backdrop" data-close-trip-payment-modal></div>
    <div class="fleet-modal-dialog fleet-trip-payment-modal">
        <div class="fleet-modal-header">
            <h3 id="trip-record-payment-title">Record Payment</h3>
            <button type="button" class="fleet-modal-close" data-close-trip-payment-modal aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="#" class="fleet-trip-payment-form" id="trip-record-payment-form">
            @csrf
            <input type="hidden" name="_payment_trip_id" id="trip-payment-trip-id" value="{{ old('_payment_trip_id') }}">
            <div class="fleet-modal-body">
                <div class="fleet-alert fleet-alert-error" id="trip-payment-errors" @if(!$errors->any()) hidden @endif>
                    @if ($errors->any())
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="fleet-trip-payment-trip-meta">
                    <div><span>Trip</span><strong id="trip-payment-trip-code">-</strong></div>
                    <div><span>Customer</span><strong id="trip-payment-customer-name">-</strong></div>
                </div>

                <div class="fleet-payments-trip-balance">
                    <div><span>Trip Total</span><strong id="trip-payment-trip-total">-</strong></div>
                    <div><span>Already Paid</span><strong id="trip-payment-trip-paid" class="is-paid">-</strong></div>
                    <div><span>Remaining</span><strong id="trip-payment-trip-remaining" class="is-outstanding">-</strong></div>
                </div>

                <div class="fleet-trip-field">
                    <label>Payment Date <span class="required">*</span></label>
                    <input type="date" name="payment_date" id="trip-payment-date" class="fleet-trip-input" value="{{ old('payment_date', now()->format('Y-m-d')) }}" required>
                </div>

                <div class="fleet-trip-field">
                    <label>Amount (KSh) <span class="required">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="trip-payment-amount" class="fleet-trip-input" value="{{ old('amount') }}" placeholder="0.00" required>
                    <p class="fleet-trip-field-help" id="trip-payment-amount-help">Pre-filled with the remaining balance.</p>
                </div>

                <div class="fleet-trip-field">
                    <label>Payment Method <span class="required">*</span></label>
                    <select name="payment_method" id="trip-payment-method" class="fleet-trip-input" required>
                        @foreach ($paymentMethods as $method)
                            <option value="{{ $method }}" {{ old('payment_method', 'Cash') === $method ? 'selected' : '' }}>{{ $method }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="fleet-trip-field">
                    <label>Reference No.</label>
                    <input type="text" name="reference_no" id="trip-payment-reference" class="fleet-trip-input" value="{{ old('reference_no') }}" placeholder="M-Pesa / receipt code">
                </div>

                <div class="fleet-trip-field fleet-trip-field-compact">
                    <label>Notes</label>
                    <input type="text" name="notes" id="trip-payment-notes" class="fleet-trip-input" value="{{ old('notes') }}" placeholder="Deposit, partial payment, etc.">
                </div>

                <label class="fleet-trip-payment-receipt-toggle">
                    <input type="checkbox" name="open_receipt" value="1" {{ old('open_receipt', '1') ? 'checked' : '' }}>
                    <span>Open printable receipt after saving</span>
                </label>
            </div>
            <div class="fleet-modal-footer fleet-trip-payment-modal-footer">
                <button type="button" class="fleet-btn fleet-btn-light" data-close-trip-payment-modal>Cancel</button>
                <button type="submit" class="fleet-btn fleet-btn-primary" id="trip-payment-submit">
                    <i class="fa fa-check"></i> Record Payment
                </button>
            </div>
        </form>
    </div>
</div>
