@extends('layouts.fleet')

@section('title', 'Edit Trip Invoice')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-vehicle-form.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-trips.css') }}?v=8">
@endpush

@section('content')
@php
    $billingType = old('billing_type', $trip->billing_type ?: 'Fixed');
    $isVariable = $billingType !== 'Fixed';
@endphp

<div class="fleet-page-head">
    <div class="fleet-page-title-row">
        <h1 class="fleet-page-title">Edit Invoice — {{ $trip->invoiceNumber() }}</h1>
        <div class="fleet-page-title-actions">
            <a href="{{ route('trips.invoice-pdf', $trip) }}" class="fleet-btn fleet-btn-outline" target="_blank" rel="noopener"><i class="fa fa-file-text-o"></i> View PDF</a>
            <a href="{{ route('trips.show', $trip) }}" class="fleet-btn fleet-btn-outline"><i class="fa fa-arrow-left"></i> Back to Trip</a>
        </div>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('trips.index') }}">Trips</a></li>
        <li><a href="{{ route('trips.show', $trip) }}">Details</a></li>
        <li>Edit Invoice</li>
    </ul>
</div>

@if ($errors->any())
    <div class="fleet-alert fleet-alert-error">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="fleet-trip-invoice-edit-layout">
    <div class="fleet-panel fleet-trip-invoice-summary-card">
        <div class="fleet-panel-header">Invoice Summary</div>
        <div class="fleet-panel-body">
            <div class="fleet-detail-grid">
                <div class="fleet-detail-item"><span>Invoice No</span><strong>{{ $trip->invoiceNumber() }}</strong></div>
                <div class="fleet-detail-item"><span>Trip Ref</span><strong>{{ $trip->displayTripCode() }}</strong></div>
                <div class="fleet-detail-item"><span>Customer</span><strong>{{ $trip->customer_name }}</strong></div>
                <div class="fleet-detail-item"><span>Route</span><strong>{{ $trip->routeLocationShort($trip->pickup_location) }} → {{ $trip->routeLocationShort($trip->drop_location) }}</strong></div>
            </div>
            <div class="fleet-trip-invoice-totals-preview">
                <div><span>Subtotal</span><strong id="preview-subtotal">{{ format_kes($trip->subtotalAmount()) }}</strong></div>
                <div><span>Tax</span><strong id="preview-tax">{{ format_kes($trip->taxAmount()) }}</strong></div>
                <div><span>Total Due</span><strong id="preview-total" class="is-total">{{ format_kes($trip->totalAmount()) }}</strong></div>
                <div><span>Amount Paid</span><strong class="is-paid">{{ format_kes($trip->paidAmount()) }}</strong></div>
                <div><span>Balance Due</span><strong class="is-outstanding">{{ format_kes($trip->remainingAmount()) }}</strong></div>
            </div>
        </div>
    </div>

    <div class="fleet-panel fleet-trip-invoice-form-card">
        <div class="fleet-panel-header">Invoice Charges</div>
        <div class="fleet-panel-body">
            <form method="POST" action="{{ route('trips.invoice.update', $trip) }}" class="fleet-trip-invoice-edit-form" id="trip-invoice-edit-form">
                @csrf
                @method('PUT')
                <input type="hidden" name="billing_type" id="billing-type-input" value="{{ $billingType }}">

                <div class="fleet-trip-field">
                    <label>Billing Type <span class="required">*</span></label>
                    <div class="fleet-billing-grid" id="billing-type-grid">
                        @foreach ($formOptions['billing_types'] as $type)
                            <button type="button" class="fleet-billing-option {{ $billingType === $type ? 'active' : '' }}" data-value="{{ $type }}">{{ $type }}</button>
                        @endforeach
                    </div>
                </div>

                <div id="billing-fixed-fields" class="fleet-billing-mode" @if($isVariable) hidden @endif>
                    <div class="fleet-trip-field">
                        <label for="base_amount_fixed">Base Amount</label>
                        <div class="fleet-trip-amount-wrap">
                            <span class="fleet-trip-currency">{{ kes_symbol() }}</span>
                            <input type="number" step="0.01" min="0" id="base_amount_fixed" name="base_amount" class="fleet-trip-input fleet-trip-amount-input" value="{{ old('base_amount', $trip->base_amount) }}" placeholder="0.00">
                        </div>
                    </div>
                </div>

                <div id="billing-variable-fields" class="fleet-billing-mode" @if(!$isVariable) hidden @endif>
                    <div class="fleet-trip-field">
                        <label for="billing_quantity" id="billing-quantity-label">Quantity</label>
                        <input type="number" step="0.001" min="0" id="billing_quantity" name="billing_quantity" class="fleet-trip-input" value="{{ old('billing_quantity', $trip->billing_quantity) }}" placeholder="0">
                    </div>
                    <div class="fleet-trip-field">
                        <label for="billing_rate" id="billing-rate-label">Rate</label>
                        <div class="fleet-trip-amount-wrap">
                            <span class="fleet-trip-currency">{{ kes_symbol() }}</span>
                            <input type="number" step="0.01" min="0" id="billing_rate" name="billing_rate" class="fleet-trip-input fleet-trip-amount-input" value="{{ old('billing_rate', $trip->billing_rate) }}" placeholder="0.00">
                        </div>
                    </div>
                    <div class="fleet-trip-field">
                        <label for="base_amount_variable">Calculated Base Amount</label>
                        <div class="fleet-trip-amount-wrap fleet-trip-total-readonly">
                            <span class="fleet-trip-currency">{{ kes_symbol() }}</span>
                            <input type="number" step="0.01" min="0" id="base_amount_variable" class="fleet-trip-input fleet-trip-amount-input" value="{{ old('base_amount', $trip->base_amount) }}" readonly>
                        </div>
                        <p class="fleet-trip-field-help" id="billing-calculation-note">Quantity × Rate = Base amount on invoice</p>
                    </div>
                </div>

                <div class="fleet-trip-field">
                    <label for="discount_amount">Discount Amount</label>
                    <div class="fleet-trip-amount-wrap">
                        <span class="fleet-trip-currency">{{ kes_symbol() }}</span>
                        <input type="number" step="0.01" min="0" id="discount_amount" name="discount_amount" class="fleet-trip-input fleet-trip-amount-input" value="{{ old('discount_amount', $trip->discount_amount) }}" placeholder="0.00">
                    </div>
                </div>

                <div class="fleet-trip-field">
                    <label>Tax</label>
                    <select name="tax_type" id="tax_type" class="fleet-trip-input">
                        @foreach ($formOptions['tax_types'] as $taxType)
                            <option value="{{ $taxType }}" {{ old('tax_type', $trip->tax_type ?: 'No Tax') === $taxType ? 'selected' : '' }}>{{ $taxType }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="fleet-trip-field">
                    <label for="coupon_code">Coupon Code</label>
                    <input type="text" name="coupon_code" id="coupon_code" class="fleet-trip-input" value="{{ old('coupon_code', $trip->coupon_code) }}" placeholder="Optional coupon reference">
                </div>

                <div class="fleet-trip-invoice-edit-actions">
                    <button type="submit" class="fleet-btn fleet-btn-primary"><i class="fa fa-save"></i> Save Invoice</button>
                    <a href="{{ route('trips.show', $trip) }}" class="fleet-btn fleet-btn-light">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var billingInput = document.getElementById('billing-type-input');
    var billingButtons = document.querySelectorAll('.fleet-billing-option');
    var billingUnitConfig = @json($formOptions['billing_unit_config']);
    var fixedFields = document.getElementById('billing-fixed-fields');
    var variableFields = document.getElementById('billing-variable-fields');
    var baseAmountFixed = document.getElementById('base_amount_fixed');
    var baseAmountVariable = document.getElementById('base_amount_variable');
    var billingQuantity = document.getElementById('billing_quantity');
    var billingRate = document.getElementById('billing_rate');
    var billingQuantityLabel = document.getElementById('billing-quantity-label');
    var billingRateLabel = document.getElementById('billing-rate-label');
    var discountAmount = document.getElementById('discount_amount');
    var taxType = document.getElementById('tax_type');
    var previewSubtotal = document.getElementById('preview-subtotal');
    var previewTax = document.getElementById('preview-tax');
    var previewTotal = document.getElementById('preview-total');
    var currencySymbol = @json(kes_symbol());

    function formatMoney(value) {
        return currencySymbol + ' ' + Number(value || 0).toLocaleString('en-KE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function currentBillingType() {
        return billingInput ? billingInput.value : 'Fixed';
    }

    function isFixedBilling() {
        return currentBillingType() === 'Fixed';
    }

    function updateBillingLabels() {
        var config = billingUnitConfig[currentBillingType()];
        if (billingQuantityLabel) billingQuantityLabel.textContent = config ? config.quantity : 'Quantity';
        if (billingRateLabel) billingRateLabel.textContent = config ? config.rate : 'Rate';
    }

    function getBaseAmount() {
        if (isFixedBilling()) {
            return parseFloat(baseAmountFixed && baseAmountFixed.value ? baseAmountFixed.value : '0') || 0;
        }

        var qty = parseFloat(billingQuantity && billingQuantity.value ? billingQuantity.value : '0') || 0;
        var rate = parseFloat(billingRate && billingRate.value ? billingRate.value : '0') || 0;
        return Math.round((qty * rate) * 100) / 100;
    }

    function calculateVariableTotal() {
        var total = getBaseAmount();
        if (baseAmountVariable) baseAmountVariable.value = total.toFixed(2);
        updatePreview();
        return total;
    }

    function parseTaxRate() {
        var value = taxType ? taxType.value : 'No Tax';
        if (!value || value === 'No Tax') return 0;
        var match = value.match(/(\d+(?:\.\d+)?)\s*%/);
        return match ? parseFloat(match[1]) : 0;
    }

    function updatePreview() {
        var base = getBaseAmount();
        var discount = parseFloat(discountAmount && discountAmount.value ? discountAmount.value : '0') || 0;
        var subtotal = Math.max(0, Math.round((base - discount) * 100) / 100);
        var tax = Math.round(subtotal * (parseTaxRate() / 100) * 100) / 100;
        var total = Math.round((subtotal + tax) * 100) / 100;

        if (previewSubtotal) previewSubtotal.textContent = formatMoney(subtotal);
        if (previewTax) previewTax.textContent = formatMoney(tax);
        if (previewTotal) previewTotal.textContent = formatMoney(total);
    }

    function toggleBillingMode() {
        var fixed = isFixedBilling();
        if (fixedFields) fixedFields.hidden = !fixed;
        if (variableFields) variableFields.hidden = fixed;
        if (baseAmountFixed) baseAmountFixed.required = fixed;
        if (billingQuantity) billingQuantity.required = !fixed;
        if (billingRate) billingRate.required = !fixed;
        updateBillingLabels();
        updatePreview();
    }

    billingButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            billingButtons.forEach(function (item) { item.classList.remove('active'); });
            this.classList.add('active');
            if (billingInput) billingInput.value = this.getAttribute('data-value');
            toggleBillingMode();
        });
    });

    if (billingQuantity) billingQuantity.addEventListener('input', calculateVariableTotal);
    if (billingRate) billingRate.addEventListener('input', calculateVariableTotal);
    if (baseAmountFixed) baseAmountFixed.addEventListener('input', updatePreview);
    if (discountAmount) discountAmount.addEventListener('input', updatePreview);
    if (taxType) taxType.addEventListener('change', updatePreview);

    toggleBillingMode();
    calculateVariableTotal();
})();
</script>
@endpush
