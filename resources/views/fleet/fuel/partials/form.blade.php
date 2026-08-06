@php
    $selectedVehicle = old('fleet_vehicle_id', $refill->fleet_vehicle_id ?? '');
    $selectedDriver = old('fleet_driver_id', $refill->fleet_driver_id ?? '');
    $selectedFuelType = old('fuel_type', $refill->fuel_type ?? '');
    $selectedSource = old('source', $refill->source ?? 'tank');
    $selectedVendor = old('fleet_fuel_vendor_id', $refill->fleet_fuel_vendor_id ?? '');
    $costPerLiter = old('cost_per_liter', '');
    if ($costPerLiter === '' && (float) ($fuelStockUnitPrice ?? 0) > 0) {
        $costPerLiter = number_format((float) $fuelStockUnitPrice, 2, '.', '');
    }
    if ($costPerLiter === '' && ! empty($refill) && (float) $refill->liters > 0 && $refill->cost !== null) {
        $costPerLiter = number_format((float) $refill->cost / (float) $refill->liters, 2, '.', '');
    }
    $amountValue = old('cost', $refill->cost ?? '');
@endphp

<form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="fleet-fuel-add-form" id="fleet-fuel-add-form" data-unit-price="{{ $fuelStockUnitPrice ?? 0 }}">
    @csrf
    @if (! empty($formMethod) && strtoupper($formMethod) !== 'POST')
        @method($formMethod)
    @endif

    <div class="fleet-fuel-add-layout">
        <div class="fleet-fuel-add-left">
            <div class="fleet-maint-section fleet-maint-section-blue">
                <div class="fleet-maint-section-head">
                    <i class="fa fa-info-circle"></i>
                    <h2>Fuel Info</h2>
                </div>
                <div class="fleet-maint-section-body">
                    <div class="fleet-maint-field">
                        <label>Vehicle <span class="required">*</span></label>
                        <select name="fleet_vehicle_id" id="fuel-vehicle" class="fleet-maint-input" required>
                            <option value="">Select Vehicle</option>
                            @foreach ($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}" data-fuel-type="{{ $vehicle->fuel_type }}" {{ (string) $selectedVehicle === (string) $vehicle->id ? 'selected' : '' }}>
                                    {{ $vehicle->displayName() }} ({{ $vehicle->registration_number }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="fleet-maint-field">
                        <label>Added Driver <span class="required">*</span></label>
                        <select name="fleet_driver_id" id="fuel-driver" class="fleet-maint-input" required>
                            <option value="">Select Driver</option>
                            @foreach ($drivers as $driver)
                                <option value="{{ $driver->id }}" {{ (string) $selectedDriver === (string) $driver->id ? 'selected' : '' }}>{{ $driver->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="fleet-maint-field">
                        <label>Fuel Type <span class="required">*</span></label>
                        <select name="fuel_type" id="fuel-type" class="fleet-maint-input" required>
                            <option value="">Select Type</option>
                            @foreach ($fuelTypes as $fuelType)
                                <option value="{{ $fuelType }}" {{ $selectedFuelType === $fuelType ? 'selected' : '' }}>{{ $fuelType }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="fleet-maint-field">
                        <label>Fill Date <span class="required">*</span></label>
                        <input type="date" name="refill_date" id="fuel-date" class="fleet-maint-input" value="{{ old('refill_date', optional($refill?->refill_date)->format('Y-m-d') ?: now()->format('Y-m-d')) }}" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="fleet-fuel-add-right">
            <div class="fleet-maint-section fleet-maint-section-green">
                <div class="fleet-maint-section-head">
                    <i class="fa fa-money"></i>
                    <h2>Costs &amp; Source</h2>
                </div>
                <div class="fleet-maint-section-body">
                    <div class="fleet-maint-row fleet-maint-row-2">
                        <div class="fleet-maint-field">
                            <label>Quantity <span class="required">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="liters" id="fuel-quantity" class="fleet-maint-input js-fuel-calc-input" value="{{ old('liters', $refill->liters ?? '') }}" placeholder="0.00" required>
                        </div>
                        <div class="fleet-maint-field">
                            <label>Odometer <span class="required">*</span></label>
                            <input type="number" min="0" name="odometer" id="fuel-odometer" class="fleet-maint-input" value="{{ old('odometer', $refill->odometer ?? '') }}" placeholder="00000" required>
                        </div>
                    </div>

                    <input type="hidden" name="cost_per_liter" id="fuel-cost-per-liter" value="{{ $costPerLiter }}">

                    <div class="fleet-maint-field">
                        <label>Amount <span class="required">*</span></label>
                        <div class="fleet-maint-cost-wrap">
                            <span class="fleet-maint-cost-prefix">{{ kes_symbol() }}</span>
                            <input type="number" step="0.01" min="0" name="cost" id="fuel-amount" class="fleet-maint-input fleet-maint-cost-input js-fuel-calc-input" value="{{ $amountValue }}" placeholder="0.00" required>
                        </div>
                        <small class="fleet-fuel-amount-hint">Auto-calculated as quantity × cost per liter</small>
                    </div>

                    <div class="fleet-fuel-source-group">
                        <label class="fleet-fuel-source-label">Tank or Vendor</label>

                        <label class="fleet-fuel-source-option">
                            <input type="radio" name="source" value="tank" class="js-fuel-source" {{ $selectedSource === 'tank' ? 'checked' : '' }}>
                            <span>Fuel Tank</span>
                            <span class="fleet-fuel-stock-pill">
                                Liters in stock: <strong id="fuel-stock-liters">{{ number_format($fuelStockLiters) }}</strong> L
                                <a href="{{ route('stock.create') }}" class="fleet-fuel-stock-add" title="Add stock"><i class="fa fa-plus"></i></a>
                            </span>
                        </label>

                        <label class="fleet-fuel-source-option">
                            <input type="radio" name="source" value="vendor" class="js-fuel-source" {{ $selectedSource === 'vendor' ? 'checked' : '' }}>
                            <span>Vendor</span>
                        </label>

                        <div class="fleet-fuel-vendor-wrap {{ $selectedSource === 'vendor' ? '' : 'is-hidden' }}" id="fuel-vendor-wrap">
                            <select name="fleet_fuel_vendor_id" id="fuel-vendor" class="fleet-maint-input">
                                <option value="">Select Vendor</option>
                                @foreach ($vendors as $vendor)
                                    <option value="{{ $vendor->id }}" {{ (string) $selectedVendor === (string) $vendor->id ? 'selected' : '' }}>{{ $vendor->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <input type="hidden" name="fleet_stock_item_id" value="{{ old('fleet_stock_item_id', $refill->fleet_stock_item_id ?? $fuelStockItemId) }}">
                    </div>
                </div>
            </div>

            <div class="fleet-maint-section fleet-maint-section-yellow">
                <div class="fleet-maint-section-head">
                    <i class="fa fa-file-text-o"></i>
                    <h2>Receipt &amp; Comments</h2>
                </div>
                <div class="fleet-maint-section-body">
                    <div class="fleet-maint-field">
                        <label>Fuel Receipt</label>
                        <div class="fleet-fuel-file-wrap">
                            <label class="fleet-fuel-file-btn">
                                Browse
                                <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf">
                            </label>
                            <span class="fleet-fuel-file-name" id="fuel-receipt-name">{{ ! empty($refill?->receipt_path) ? basename($refill->receipt_path) : 'Choose File' }}</span>
                        </div>
                        @if (! empty($refill?->receipt_path))
                            <small class="fleet-fuel-file-current">Current: {{ basename($refill->receipt_path) }}</small>
                        @endif
                    </div>

                    <div class="fleet-maint-field">
                        <label>Label Comment</label>
                        <textarea name="notes" id="fuel-notes" class="fleet-maint-textarea" rows="4" placeholder="Fuel Comments">{{ old('notes', $refill->notes ?? '') }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="fleet-fuel-add-actions">
        <a href="{{ route('fuel.index') }}" class="fleet-btn fleet-btn-default">Cancel</a>
        <button type="submit" class="fleet-btn fleet-btn-primary">{{ $refill ? 'Update Fuel' : 'Save Fuel' }}</button>
    </div>
</form>
