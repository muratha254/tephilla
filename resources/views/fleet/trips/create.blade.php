@extends('layouts.fleet')

@section('title', 'New Booking')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-vendors.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-trips.css') }}?v=7">
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">New Booking</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('trips.index') }}">Trips</a></li>
        <li>Add</li>
    </ul>
</div>

@if ($errors->any())
<div class="alert alert-danger">
    <ul class="fleet-form-errors">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form action="{{ route('trips.store') }}" method="POST" class="fleet-trip-booking-form" id="trip-booking-form">
    @csrf
    <input type="hidden" name="billing_type" id="billing-type-input" value="{{ old('billing_type', 'Fixed') }}">

    <div class="fleet-trip-booking-layout">
        <div class="fleet-trip-booking-main">
            <div class="fleet-trip-section fleet-trip-section-blue">
                <div class="fleet-trip-section-head">
                    <i class="fa fa-truck"></i>
                    <h2>Resources</h2>
                </div>
                <div class="fleet-trip-section-body">
                    <div class="fleet-trip-row fleet-trip-row-3">
                        <div class="fleet-trip-field">
                            <label>Trip Type <span class="required">*</span></label>
                            <div class="fleet-trip-customer-select-wrap">
                                <select name="trip_type" id="trip-type-select" class="fleet-trip-input" required>
                                    @foreach ($formOptions['trip_types'] as $type)
                                        <option value="{{ $type }}" {{ old('trip_type', 'Single Trip') === $type ? 'selected' : '' }}>{{ $type }}</option>
                                    @endforeach
                                </select>
                                <button type="button" class="fleet-trip-create-customer-btn" id="open-add-trip-type-modal">
                                    <i class="fa fa-plus"></i> Add Type
                                </button>
                            </div>
                        </div>
                        <div class="fleet-trip-field">
                            <label>Vehicle <span class="required">*</span></label>
                            <select name="fleet_vehicle_id" class="fleet-trip-input" required>
                                <option value="">Select Vehicle</option>
                                @foreach ($formOptions['vehicles'] as $vehicle)
                                    <option value="{{ $vehicle->id }}" {{ (string) old('fleet_vehicle_id') === (string) $vehicle->id ? 'selected' : '' }}>
                                        {{ $vehicle->displayName() }} ({{ $vehicle->registration_number }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fleet-trip-field">
                            <label>Driver Name</label>
                            <select name="fleet_driver_id" class="fleet-trip-input">
                                <option value="">Select Driver</option>
                                @foreach ($formOptions['drivers'] as $driver)
                                    <option value="{{ $driver->id }}" {{ (string) old('fleet_driver_id') === (string) $driver->id ? 'selected' : '' }}>{{ $driver->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="fleet-trip-section fleet-trip-section-blue">
                <div class="fleet-trip-section-head">
                    <i class="fa fa-map-marker"></i>
                    <h2>Journey Details</h2>
                </div>
                <div class="fleet-trip-section-body">
                    <div class="fleet-trip-row">
                        <div class="fleet-trip-field fleet-trip-customer-field fleet-trip-field-full">
                            <label>Customer <span class="required">*</span></label>
                            <div class="fleet-trip-customer-select-wrap">
                                <select name="fleet_customer_id" id="trip-customer-select" class="fleet-trip-input" required>
                                    <option value="">Select</option>
                                    @foreach ($formOptions['customers'] as $customer)
                                        <option value="{{ $customer->id }}" {{ (string) old('fleet_customer_id') === (string) $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" class="fleet-trip-create-customer-btn" id="open-create-customer-modal">
                                    <i class="fa fa-plus"></i> Create Customer
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="fleet-trip-row">
                        <div class="fleet-trip-field">
                            <label for="trip-start-date">Start Date <span class="required">*</span></label>
                            <div class="fleet-trip-date-wrap">
                                <button type="button" class="fleet-trip-date-icon" data-trip-date-trigger="trip-start-date" aria-label="Open start date calendar">
                                    <i class="fa fa-calendar"></i>
                                </button>
                                <input
                                    type="text"
                                    id="trip-start-date"
                                    name="start_date"
                                    class="fleet-trip-input fleet-date-input"
                                    value="{{ fleet_date_input_value(old('start_date')) }}"
                                    placeholder="dd/mm/yyyy"
                                    autocomplete="off"
                                    readonly
                                    required
                                >
                            </div>
                        </div>
                        <div class="fleet-trip-field">
                            <label for="trip-end-date">End Date <span class="required">*</span></label>
                            <div class="fleet-trip-date-wrap">
                                <button type="button" class="fleet-trip-date-icon" data-trip-date-trigger="trip-end-date" aria-label="Open end date calendar">
                                    <i class="fa fa-calendar"></i>
                                </button>
                                <input
                                    type="text"
                                    id="trip-end-date"
                                    name="end_date"
                                    class="fleet-trip-input fleet-date-input"
                                    value="{{ fleet_date_input_value(old('end_date')) }}"
                                    placeholder="dd/mm/yyyy"
                                    autocomplete="off"
                                    readonly
                                    required
                                >
                            </div>
                        </div>
                    </div>

                    <div class="fleet-trip-row">
                        <div class="fleet-trip-field">
                            <label>Pickup Location <span class="required">*</span></label>
                            <div class="fleet-trip-location-wrap pickup">
                                <span class="fleet-trip-location-icon"><i class="fa fa-map-marker"></i></span>
                                <input type="text" name="pickup_location" class="fleet-trip-input fleet-trip-location-input" value="{{ old('pickup_location') }}" placeholder="Enter Pickup Location" required>
                            </div>
                        </div>
                        <div class="fleet-trip-field">
                            <label>Drop Location <span class="required">*</span></label>
                            <div class="fleet-trip-location-wrap drop">
                                <span class="fleet-trip-location-icon"><i class="fa fa-map-marker"></i></span>
                                <input type="text" name="drop_location" class="fleet-trip-input fleet-trip-location-input" value="{{ old('drop_location') }}" placeholder="Enter Drop Location" required>
                            </div>
                        </div>
                    </div>

                    <div class="fleet-trip-row fleet-trip-stops-container-row">
                        <div class="fleet-trip-field">
                            <label>Additional Stops</label>
                            <div id="additional-stops-list">
                                @php $oldStops = old('additional_stops', ['']); @endphp
                                @foreach ($oldStops as $index => $stop)
                                <div class="fleet-trip-stop-row">
                                    <input type="text" name="additional_stops[]" class="fleet-trip-input" value="{{ $stop }}" placeholder="Add Stop Location">
                                    @if ($index > 0)
                                        <button type="button" class="fleet-trip-stop-remove" aria-label="Remove stop">&times;</button>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                            <button type="button" class="fleet-trip-stop-add" id="add-stop-btn"><i class="fa fa-plus"></i></button>
                        </div>

                        <div class="fleet-trip-field" id="container-details-section">
                            <label>Container Details</label>
                            <label class="fleet-trip-container-toggle">
                                <input type="checkbox" id="uses-container-toggle" {{ old('container_number') || old('container_empty_drop_point') ? 'checked' : '' }}>
                                <span>This trip involves a container shipment</span>
                            </label>

                            <div class="fleet-trip-container-fields {{ old('container_number') || old('container_empty_drop_point') ? 'is-visible' : '' }}" id="container-fields">
                                <div class="fleet-trip-field">
                                    <label for="container_number">Container Number</label>
                                    <input
                                        type="text"
                                        id="container_number"
                                        name="container_number"
                                        class="fleet-trip-input"
                                        value="{{ old('container_number') }}"
                                        placeholder="e.g. MSCU1234567"
                                    >
                                </div>
                                <div class="fleet-trip-field">
                                    <label for="container_empty_drop_point">Container Empty Drop Point</label>
                                    <div class="fleet-trip-location-wrap drop">
                                        <span class="fleet-trip-location-icon"><i class="fa fa-map-marker"></i></span>
                                        <input
                                            type="text"
                                            id="container_empty_drop_point"
                                            name="container_empty_drop_point"
                                            class="fleet-trip-input fleet-trip-location-input"
                                            value="{{ old('container_empty_drop_point') }}"
                                            placeholder="Where to return/drop the empty container"
                                        >
                                    </div>
                                </div>
                                <p class="fleet-trip-field-help">Optional. Use when transporting goods in a container and you need to record where the empty container should be dropped off.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="fleet-trip-booking-side">
            <div class="fleet-trip-section fleet-trip-section-green">
                <div class="fleet-trip-section-head">
                    <i class="fa fa-money"></i>
                    <h2>Financials</h2>
                </div>
                <div class="fleet-trip-section-body">
                    <div class="fleet-trip-field">
                        <label>Billing Type <span class="required">*</span></label>
                        <div class="fleet-billing-grid" id="billing-type-grid">
                            @foreach ($formOptions['billing_types'] as $billingType)
                                <button type="button" class="fleet-billing-option {{ old('billing_type', 'Fixed') === $billingType ? 'active' : '' }}" data-value="{{ $billingType }}">{{ $billingType }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div id="billing-fixed-fields" class="fleet-billing-mode">
                        <div class="fleet-trip-field">
                            <label for="base_amount_fixed">Base Amount</label>
                            <div class="fleet-trip-amount-wrap">
                                <span class="fleet-trip-currency">{{ kes_symbol() }}</span>
                                <input type="number" step="0.01" min="0" id="base_amount_fixed" name="base_amount" class="fleet-trip-input fleet-trip-amount-input" value="{{ old('base_amount', '0.00') }}" placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    <div id="billing-variable-fields" class="fleet-billing-mode" hidden>
                        <div class="fleet-trip-field">
                            <label for="billing_quantity" id="billing-quantity-label">Quantity</label>
                            <input type="number" step="0.001" min="0" id="billing_quantity" name="billing_quantity" class="fleet-trip-input" value="{{ old('billing_quantity') }}" placeholder="0">
                        </div>
                        <div class="fleet-trip-field">
                            <label for="billing_rate" id="billing-rate-label">Rate</label>
                            <div class="fleet-trip-amount-wrap">
                                <span class="fleet-trip-currency">{{ kes_symbol() }}</span>
                                <input type="number" step="0.01" min="0" id="billing_rate" name="billing_rate" class="fleet-trip-input fleet-trip-amount-input" value="{{ old('billing_rate') }}" placeholder="0.00">
                            </div>
                        </div>
                        <div class="fleet-trip-field">
                            <label for="base_amount_variable">Total Amount</label>
                            <div class="fleet-trip-amount-wrap fleet-trip-total-readonly">
                                <span class="fleet-trip-currency">{{ kes_symbol() }}</span>
                                <input type="number" step="0.01" min="0" id="base_amount_variable" class="fleet-trip-input fleet-trip-amount-input" value="{{ old('base_amount', '0.00') }}" placeholder="0.00" readonly>
                            </div>
                            <p class="fleet-trip-field-help" id="billing-calculation-note">Quantity × Rate = Total trip cost</p>
                        </div>
                    </div>

                    <div class="fleet-trip-field">
                        <label>Tax</label>
                        <select name="tax_type" class="fleet-trip-input">
                            @foreach ($formOptions['tax_types'] as $taxType)
                                <option value="{{ $taxType }}" {{ old('tax_type', 'No Tax') === $taxType ? 'selected' : '' }}>{{ $taxType }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="fleet-trip-field">
                        <label>Coupons / Discount</label>
                        <div class="fleet-trip-coupon-wrap">
                            <input type="text" name="coupon_code" id="coupon-code" class="fleet-trip-input" value="{{ old('coupon_code') }}" placeholder="Code">
                            <button type="button" class="fleet-trip-apply-btn" id="apply-coupon-btn">Apply</button>
                        </div>
                    </div>

                    <div class="fleet-trip-deposit-section">
                        <div class="fleet-trip-deposit-head">
                            <label for="deposit_amount">Deposit / Advance Payment</label>
                            <p class="fleet-trip-field-help">Optional upfront deposit recorded with this booking.</p>
                        </div>
                        <div class="fleet-trip-field">
                            <label for="deposit_amount">Deposit Amount</label>
                            <div class="fleet-trip-amount-wrap">
                                <span class="fleet-trip-currency">{{ kes_symbol() }}</span>
                                <input type="number" step="0.01" min="0" id="deposit_amount" name="deposit_amount" class="fleet-trip-input fleet-trip-amount-input" value="{{ old('deposit_amount') }}" placeholder="0.00">
                            </div>
                        </div>
                        <div class="fleet-trip-field">
                            <label for="deposit_payment_method">Payment Method</label>
                            <select name="deposit_payment_method" id="deposit_payment_method" class="fleet-trip-input">
                                @foreach ($formOptions['payment_methods'] as $method)
                                    <option value="{{ $method }}" {{ old('deposit_payment_method', 'Cash') === $method ? 'selected' : '' }}>{{ $method }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fleet-trip-field">
                            <label for="deposit_reference_no">Reference No.</label>
                            <input type="text" id="deposit_reference_no" name="deposit_reference_no" class="fleet-trip-input" value="{{ old('deposit_reference_no') }}" placeholder="M-Pesa / receipt code">
                        </div>
                        <div class="fleet-trip-field">
                            <label for="deposit_notes">Deposit Notes</label>
                            <input type="text" id="deposit_notes" name="deposit_notes" class="fleet-trip-input" value="{{ old('deposit_notes') }}" placeholder="Deposit on booking">
                        </div>
                    </div>

                    <div class="fleet-trip-book-actions">
                        <button type="submit" class="fleet-btn fleet-btn-save-trip"><i class="fa fa-check"></i> Create Booking</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<div class="fleet-modal" id="trip-add-type-modal" hidden>
    <div class="fleet-modal-backdrop" data-close-trip-type-modal></div>
    <div class="fleet-modal-dialog fleet-trip-customer-modal">
        <div class="fleet-modal-header">
            <h3>Add Trip Type</h3>
            <button type="button" class="fleet-modal-close" data-close-trip-type-modal aria-label="Close">&times;</button>
        </div>
        <form id="trip-add-type-form">
            <div class="fleet-modal-body">
                <div id="trip-add-type-errors" class="fleet-alert fleet-alert-error" hidden></div>
                <div class="fleet-trip-field">
                    <label for="quick-trip-type-name">Trip Type Name <span class="required">*</span></label>
                    <input type="text" id="quick-trip-type-name" name="name" class="fleet-trip-input" placeholder="e.g. Container Haulage" required>
                </div>
            </div>
            <div class="fleet-modal-footer">
                <button type="button" class="fleet-btn fleet-btn-default" data-close-trip-type-modal>Cancel</button>
                <button type="submit" class="fleet-btn fleet-btn-primary" id="trip-add-type-submit">
                    <i class="fa fa-save"></i> Save Trip Type
                </button>
            </div>
        </form>
    </div>
</div>

<div class="fleet-modal" id="trip-create-customer-modal" hidden>
    <div class="fleet-modal-backdrop" data-close-trip-customer-modal></div>
    <div class="fleet-modal-dialog fleet-trip-customer-modal">
        <div class="fleet-modal-header">
            <h3>Create Customer</h3>
            <button type="button" class="fleet-modal-close" data-close-trip-customer-modal aria-label="Close">&times;</button>
        </div>
        <form id="trip-create-customer-form">
            <div class="fleet-modal-body">
                <div id="trip-create-customer-errors" class="fleet-alert fleet-alert-error" hidden></div>
                <div class="fleet-trip-field">
                    <label for="quick-customer-name">Name <span class="required">*</span></label>
                    <input type="text" id="quick-customer-name" name="name" class="fleet-trip-input" placeholder="Customer Name" required>
                </div>
                <div class="fleet-trip-field">
                    <label for="quick-customer-mobile">Mobile <span class="required">*</span></label>
                    <input type="text" id="quick-customer-mobile" name="mobile" class="fleet-trip-input" placeholder="Customer Mobile" required>
                </div>
                <div class="fleet-trip-field">
                    <label for="quick-customer-email">Email</label>
                    <input type="email" id="quick-customer-email" name="email" class="fleet-trip-input" placeholder="Customer Email">
                </div>
                <div class="fleet-trip-field">
                    <label for="quick-customer-address">Address <span class="required">*</span></label>
                    <textarea id="quick-customer-address" name="address" class="fleet-trip-input fleet-trip-textarea" rows="3" placeholder="Address" required></textarea>
                </div>
                <p class="fleet-trip-field-help">Default login password will be set to <strong>1234</strong>. You can update it later from Customer Management.</p>
            </div>
            <div class="fleet-modal-footer">
                <button type="button" class="fleet-btn fleet-btn-default" data-close-trip-customer-modal>Cancel</button>
                <button type="submit" class="fleet-btn fleet-btn-primary" id="trip-create-customer-submit">
                    <i class="fa fa-save"></i> Save Customer
                </button>
            </div>
        </form>
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
    var currencySymbol = @json(kes_symbol());

    function currentBillingType() {
        return billingInput ? billingInput.value : 'Fixed';
    }

    function isFixedBilling() {
        return currentBillingType() === 'Fixed';
    }

    function updateBillingLabels() {
        var config = billingUnitConfig[currentBillingType()];
        if (billingQuantityLabel) {
            billingQuantityLabel.textContent = config ? config.quantity : 'Quantity';
        }
        if (billingRateLabel) {
            billingRateLabel.textContent = config ? config.rate : 'Rate';
        }
    }

    function calculateVariableTotal() {
        var qty = parseFloat(billingQuantity && billingQuantity.value ? billingQuantity.value : '0') || 0;
        var rate = parseFloat(billingRate && billingRate.value ? billingRate.value : '0') || 0;
        var total = Math.round((qty * rate) * 100) / 100;

        if (baseAmountVariable) {
            baseAmountVariable.value = total.toFixed(2);
        }

        return total;
    }

    function syncBillingMode() {
        var fixed = isFixedBilling();

        if (fixedFields) {
            fixedFields.hidden = !fixed;
        }
        if (variableFields) {
            variableFields.hidden = fixed;
        }

        if (baseAmountFixed) {
            baseAmountFixed.disabled = !fixed;
            if (!fixed) {
                baseAmountFixed.removeAttribute('name');
            } else {
                baseAmountFixed.setAttribute('name', 'base_amount');
            }
        }

        if (baseAmountVariable) {
            if (fixed) {
                baseAmountVariable.removeAttribute('name');
            } else {
                baseAmountVariable.setAttribute('name', 'base_amount');
                calculateVariableTotal();
            }
        }

        if (billingQuantity) {
            billingQuantity.required = !fixed;
        }
        if (billingRate) {
            billingRate.required = !fixed;
        }

        updateBillingLabels();
    }

    billingButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            billingButtons.forEach(function (item) { item.classList.remove('active'); });
            this.classList.add('active');
            if (billingInput) {
                billingInput.value = this.getAttribute('data-value');
            }
            syncBillingMode();
        });
    });

    if (billingQuantity) {
        billingQuantity.addEventListener('input', calculateVariableTotal);
    }
    if (billingRate) {
        billingRate.addEventListener('input', calculateVariableTotal);
    }

    syncBillingMode();

    var stopsList = document.getElementById('additional-stops-list');
    var addStopBtn = document.getElementById('add-stop-btn');

    function bindRemoveButtons() {
        stopsList.querySelectorAll('.fleet-trip-stop-remove').forEach(function (btn) {
            btn.onclick = function () {
                this.closest('.fleet-trip-stop-row').remove();
            };
        });
    }

    if (addStopBtn && stopsList) {
        addStopBtn.addEventListener('click', function () {
            var row = document.createElement('div');
            row.className = 'fleet-trip-stop-row';
            row.innerHTML = '<input type="text" name="additional_stops[]" class="fleet-trip-input" placeholder="Add Stop Location">' +
                '<button type="button" class="fleet-trip-stop-remove" aria-label="Remove stop">&times;</button>';
            stopsList.appendChild(row);
            bindRemoveButtons();
        });
        bindRemoveButtons();
    }

    var applyCouponBtn = document.getElementById('apply-coupon-btn');
    if (applyCouponBtn) {
        applyCouponBtn.addEventListener('click', function () {
            var code = document.getElementById('coupon-code');
            if (code && code.value.trim() === '') {
                alert('Please enter a coupon code.');
                return;
            }
            alert('Coupon applied (demo).');
        });
    }

    var containerToggle = document.getElementById('uses-container-toggle');
    var containerFields = document.getElementById('container-fields');
    var containerNumber = document.getElementById('container_number');
    var containerDropPoint = document.getElementById('container_empty_drop_point');

    function syncContainerFields() {
        if (!containerToggle || !containerFields) return;
        var visible = containerToggle.checked;
        containerFields.classList.toggle('is-visible', visible);
        if (!visible) {
            if (containerNumber) containerNumber.value = '';
            if (containerDropPoint) containerDropPoint.value = '';
        }
    }

    if (containerToggle) {
        containerToggle.addEventListener('change', syncContainerFields);
        syncContainerFields();
    }

    var csrfToken = document.querySelector('meta[name="csrf-token"]');

    var tripTypeModal = document.getElementById('trip-add-type-modal');
    var openTripTypeModalBtn = document.getElementById('open-add-trip-type-modal');
    var tripTypeForm = document.getElementById('trip-add-type-form');
    var tripTypeSelect = document.getElementById('trip-type-select');
    var tripTypeErrors = document.getElementById('trip-add-type-errors');
    var tripTypeSubmit = document.getElementById('trip-add-type-submit');

    function openTripTypeModal() {
        if (!tripTypeModal) return;
        tripTypeModal.hidden = false;
        document.body.classList.add('fleet-modal-open');
        if (tripTypeErrors) {
            tripTypeErrors.hidden = true;
            tripTypeErrors.innerHTML = '';
        }
        var nameInput = document.getElementById('quick-trip-type-name');
        if (nameInput) nameInput.focus();
    }

    function closeTripTypeModal() {
        if (!tripTypeModal) return;
        tripTypeModal.hidden = true;
        document.body.classList.remove('fleet-modal-open');
    }

    if (openTripTypeModalBtn) {
        openTripTypeModalBtn.addEventListener('click', openTripTypeModal);
    }

    if (tripTypeModal) {
        tripTypeModal.querySelectorAll('[data-close-trip-type-modal]').forEach(function (el) {
            el.addEventListener('click', closeTripTypeModal);
        });
    }

    if (tripTypeForm && tripTypeSelect) {
        tripTypeForm.addEventListener('submit', function (e) {
            e.preventDefault();

            if (tripTypeSubmit) {
                tripTypeSubmit.disabled = true;
            }

            var formData = new FormData(tripTypeForm);

            fetch(@json(route('trips.types.store-quick')), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken ? csrfToken.getAttribute('content') : '',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    var messages = [];
                    if (result.data && result.data.errors) {
                        Object.keys(result.data.errors).forEach(function (key) {
                            result.data.errors[key].forEach(function (message) {
                                messages.push(message);
                            });
                        });
                    } else if (result.data && result.data.message) {
                        messages.push(result.data.message);
                    } else {
                        messages.push('Could not add trip type.');
                    }

                    if (tripTypeErrors) {
                        tripTypeErrors.innerHTML = '<ul><li>' + messages.join('</li><li>') + '</li></ul>';
                        tripTypeErrors.hidden = false;
                    }
                    return;
                }

                var tripType = result.data.trip_type;
                var option = document.createElement('option');
                option.value = tripType.name;
                option.textContent = tripType.name;
                option.selected = true;
                tripTypeSelect.appendChild(option);
                tripTypeForm.reset();
                closeTripTypeModal();
            })
            .catch(function () {
                if (tripTypeErrors) {
                    tripTypeErrors.innerHTML = '<ul><li>Could not add trip type. Please try again.</li></ul>';
                    tripTypeErrors.hidden = false;
                }
            })
            .finally(function () {
                if (tripTypeSubmit) {
                    tripTypeSubmit.disabled = false;
                }
            });
        });
    }

    var customerModal = document.getElementById('trip-create-customer-modal');
    var openCustomerModalBtn = document.getElementById('open-create-customer-modal');
    var customerForm = document.getElementById('trip-create-customer-form');
    var customerSelect = document.getElementById('trip-customer-select');
    var customerErrors = document.getElementById('trip-create-customer-errors');
    var customerSubmit = document.getElementById('trip-create-customer-submit');

    function openCustomerModal() {
        if (!customerModal) return;
        customerModal.hidden = false;
        document.body.classList.add('fleet-modal-open');
        if (customerErrors) {
            customerErrors.hidden = true;
            customerErrors.innerHTML = '';
        }
    }

    function closeCustomerModal() {
        if (!customerModal) return;
        customerModal.hidden = true;
        document.body.classList.remove('fleet-modal-open');
    }

    if (openCustomerModalBtn) {
        openCustomerModalBtn.addEventListener('click', openCustomerModal);
    }

    if (customerModal) {
        customerModal.querySelectorAll('[data-close-trip-customer-modal]').forEach(function (el) {
            el.addEventListener('click', closeCustomerModal);
        });
    }

    if (customerForm && customerSelect) {
        customerForm.addEventListener('submit', function (e) {
            e.preventDefault();

            if (customerSubmit) {
                customerSubmit.disabled = true;
            }

            var formData = new FormData(customerForm);

            fetch(@json(route('customers.store-quick')), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken ? csrfToken.getAttribute('content') : '',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    var messages = [];
                    if (result.data && result.data.errors) {
                        Object.keys(result.data.errors).forEach(function (key) {
                            result.data.errors[key].forEach(function (message) {
                                messages.push(message);
                            });
                        });
                    } else if (result.data && result.data.message) {
                        messages.push(result.data.message);
                    } else {
                        messages.push('Could not create customer.');
                    }

                    if (customerErrors) {
                        customerErrors.innerHTML = '<ul><li>' + messages.join('</li><li>') + '</li></ul>';
                        customerErrors.hidden = false;
                    }
                    return;
                }

                var customer = result.data.customer;
                var option = document.createElement('option');
                option.value = customer.id;
                option.textContent = customer.name;
                option.selected = true;
                customerSelect.appendChild(option);
                customerForm.reset();
                closeCustomerModal();
            })
            .catch(function () {
                if (customerErrors) {
                    customerErrors.innerHTML = '<ul><li>Could not create customer. Please try again.</li></ul>';
                    customerErrors.hidden = false;
                }
            })
            .finally(function () {
                if (customerSubmit) {
                    customerSubmit.disabled = false;
                }
            });
        });
    }
})();
</script>
<script src="{{ asset('AdminLTE-2/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.js') }}"></script>
<script>
$(function () {
    var $startDate = $('#trip-start-date');
    var $endDate = $('#trip-end-date');

    $startDate.datepicker({
        format: 'dd/mm/yyyy',
        autoclose: true,
        todayHighlight: true,
        orientation: 'bottom auto',
        endDate: $endDate.val() || false
    });

    $endDate.datepicker({
        format: 'dd/mm/yyyy',
        autoclose: true,
        todayHighlight: true,
        orientation: 'bottom auto',
        startDate: $startDate.val() || false
    });

    $startDate.on('changeDate', function (e) {
        if (!e.date) return;
        $endDate.datepicker('setStartDate', e.date);
        var endPick = $endDate.datepicker('getDate');
        if (endPick && endPick < e.date) {
            $endDate.datepicker('setDate', e.date);
        }
    });

    $endDate.on('changeDate', function (e) {
        if (!e.date) return;
        $startDate.datepicker('setEndDate', e.date);
    });

    $('[data-trip-date-trigger]').on('click', function () {
        $('#' + this.getAttribute('data-trip-date-trigger')).datepicker('show');
    });

    $('.fleet-date-input').on('click', function () {
        $(this).datepicker('show');
    });
});
</script>
@endpush
