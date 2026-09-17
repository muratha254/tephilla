@php
    $isEdit = ! empty($trip);
    $defaultBillingType = old('billing_type', optional($trip)->billing_type ?? 'Fixed');
    $defaultTripType = old('trip_type', optional($trip)->trip_type ?? 'Single Trip');
    $containerNumber = old('container_number', optional($trip)->container_number);
    $containerDropPoint = old('container_empty_drop_point', optional($trip)->container_empty_drop_point);
    $hasContainer = (bool) ($containerNumber || $containerDropPoint);
    $defaultStops = old('additional_stops', optional($trip)->additional_stops ?? ['']);
    if (! is_array($defaultStops) || $defaultStops === []) {
        $defaultStops = [''];
    }
    $defaultBaseAmount = old('base_amount', optional($trip)->base_amount ?? '0.00');
    $cancelUrl = $cancelUrl ?? ($isEdit ? route('trips.show', $trip) : route('trips.index'));
@endphp

<form action="{{ $formAction }}" method="POST" class="fleet-trip-booking-form" id="trip-booking-form">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif
    <input type="hidden" name="billing_type" id="billing-type-input" value="{{ $defaultBillingType }}">

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
                                        <option value="{{ $type }}" {{ $defaultTripType === $type ? 'selected' : '' }}>{{ $type }}</option>
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
                                    <option value="{{ $vehicle->id }}" {{ (string) old('fleet_vehicle_id', optional($trip)->fleet_vehicle_id) === (string) $vehicle->id ? 'selected' : '' }}>
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
                                    <option value="{{ $driver->id }}" {{ (string) old('fleet_driver_id', optional($trip)->fleet_driver_id) === (string) $driver->id ? 'selected' : '' }}>{{ $driver->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @if ($isEdit && ! empty($statuses))
                    <div class="fleet-trip-row">
                        <div class="fleet-trip-field">
                            <label>Trip Status <span class="required">*</span></label>
                            <select name="status" class="fleet-trip-input" required>
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" {{ old('status', $trip->status) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @endif
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
                                        <option value="{{ $customer->id }}" {{ (string) old('fleet_customer_id', optional($trip)->fleet_customer_id) === (string) $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
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
                                    value="{{ fleet_date_input_value(old('start_date', optional($trip)->start_date)) }}"
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
                                    value="{{ fleet_date_input_value(old('end_date', optional($trip)->end_date)) }}"
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
                                <input type="text" name="pickup_location" class="fleet-trip-input fleet-trip-location-input" value="{{ old('pickup_location', optional($trip)->pickup_location) }}" placeholder="Enter Pickup Location" required>
                            </div>
                        </div>
                        <div class="fleet-trip-field">
                            <label>Drop Location <span class="required">*</span></label>
                            <div class="fleet-trip-location-wrap drop">
                                <span class="fleet-trip-location-icon"><i class="fa fa-map-marker"></i></span>
                                <input type="text" name="drop_location" class="fleet-trip-input fleet-trip-location-input" value="{{ old('drop_location', optional($trip)->drop_location) }}" placeholder="Enter Drop Location" required>
                            </div>
                        </div>
                    </div>

                    <div class="fleet-trip-row fleet-trip-stops-container-row">
                        <div class="fleet-trip-field">
                            <label>Additional Stops</label>
                            <div id="additional-stops-list">
                                @foreach ($defaultStops as $index => $stop)
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
                                <input type="checkbox" id="uses-container-toggle" {{ $hasContainer ? 'checked' : '' }}>
                                <span>This trip involves a container shipment</span>
                            </label>

                            <div class="fleet-trip-container-fields {{ $hasContainer ? 'is-visible' : '' }}" id="container-fields">
                                <div class="fleet-trip-field">
                                    <label for="container_number">Container Number</label>
                                    <input
                                        type="text"
                                        id="container_number"
                                        name="container_number"
                                        class="fleet-trip-input"
                                        value="{{ $containerNumber }}"
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
                                            value="{{ $containerDropPoint }}"
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
                                <button type="button" class="fleet-billing-option {{ $defaultBillingType === $billingType ? 'active' : '' }}" data-value="{{ $billingType }}">{{ $billingType }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div id="billing-fixed-fields" class="fleet-billing-mode">
                        <div class="fleet-trip-field">
                            <label for="base_amount_fixed">Base Amount</label>
                            <div class="fleet-trip-amount-wrap">
                                <span class="fleet-trip-currency">{{ kes_symbol() }}</span>
                                <input type="number" step="0.01" min="0" id="base_amount_fixed" name="base_amount" class="fleet-trip-input fleet-trip-amount-input" value="{{ $defaultBaseAmount }}" placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    <div id="billing-variable-fields" class="fleet-billing-mode" hidden>
                        <div class="fleet-trip-field">
                            <label for="billing_quantity" id="billing-quantity-label">Quantity</label>
                            <input type="number" step="0.001" min="0" id="billing_quantity" name="billing_quantity" class="fleet-trip-input" value="{{ old('billing_quantity', optional($trip)->billing_quantity) }}" placeholder="0">
                        </div>
                        <div class="fleet-trip-field">
                            <label for="billing_rate" id="billing-rate-label">Rate</label>
                            <div class="fleet-trip-amount-wrap">
                                <span class="fleet-trip-currency">{{ kes_symbol() }}</span>
                                <input type="number" step="0.01" min="0" id="billing_rate" name="billing_rate" class="fleet-trip-input fleet-trip-amount-input" value="{{ old('billing_rate', optional($trip)->billing_rate) }}" placeholder="0.00">
                            </div>
                        </div>
                        <div class="fleet-trip-field">
                            <label for="base_amount_variable">Total Amount</label>
                            <div class="fleet-trip-amount-wrap fleet-trip-total-readonly">
                                <span class="fleet-trip-currency">{{ kes_symbol() }}</span>
                                <input type="number" step="0.01" min="0" id="base_amount_variable" class="fleet-trip-input fleet-trip-amount-input" value="{{ $defaultBaseAmount }}" placeholder="0.00" readonly>
                            </div>
                            <p class="fleet-trip-field-help" id="billing-calculation-note">Quantity × Rate = Total trip cost</p>
                        </div>
                    </div>

                    <div class="fleet-trip-field">
                        <label>Tax</label>
                        <select name="tax_type" class="fleet-trip-input">
                            @foreach ($formOptions['tax_types'] as $taxType)
                                <option value="{{ $taxType }}" {{ old('tax_type', optional($trip)->tax_type ?? 'No Tax') === $taxType ? 'selected' : '' }}>{{ $taxType }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="fleet-trip-field">
                        <label>Coupons / Discount</label>
                        <div class="fleet-trip-coupon-wrap">
                            <input type="text" name="coupon_code" id="coupon-code" class="fleet-trip-input" value="{{ old('coupon_code', optional($trip)->coupon_code) }}" placeholder="Code">
                            <button type="button" class="fleet-trip-apply-btn" id="apply-coupon-btn">Apply</button>
                        </div>
                    </div>

                    @if (! $isEdit)
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
                    @endif

                    <div class="fleet-trip-book-actions">
                        <a href="{{ $cancelUrl }}" class="fleet-btn fleet-btn-cancel-trip">Cancel</a>
                        <button type="submit" class="fleet-btn fleet-btn-save-trip"><i class="fa fa-check"></i> {{ $submitLabel }}</button>
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