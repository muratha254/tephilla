@php
    $field = function ($name, $default = '') use ($vehicle) {
        return old($name, optional($vehicle)->{$name} ?? $default);
    };

    $dateField = function ($name) use ($vehicle) {
        $value = old($name);
        if ($value !== null && $value !== '') {
            return $value;
        }

        $date = optional($vehicle)->{$name};

        return $date ? $date->format('Y-m-d') : '';
    };

    $currentFuel = old('opening_fuel', optional($vehicle)->current_fuel ?? optional($vehicle)->opening_fuel ?? '0');
    $colorValue = $field('color', '#D6E1F3');
    $existingImage = optional($vehicle)->image_path ? asset('storage/' . $vehicle->image_path) : null;
    $existingImageName = $existingImage ? basename($vehicle->image_path) : 'No file chosen';
@endphp

<div class="fleet-vehicle-form-grid">
    <div class="fleet-panel fleet-vehicle-image-card">
        <div class="fleet-panel-header">Vehicle Image</div>
        <div class="fleet-panel-body fleet-image-panel">
            <div class="fleet-image-preview" id="vehicle-image-preview">
                @if ($existingImage)
                    <img src="{{ $existingImage }}" alt="Vehicle preview">
                @else
                    <i class="fa fa-car fleet-image-placeholder"></i>
                @endif
            </div>
            <div class="fleet-file-input-wrap">
                <input type="text" class="fleet-file-name" id="vehicle-image-name" value="{{ $existingImageName }}" readonly>
                <label class="fleet-btn fleet-btn-browse">
                    Browse
                    <input type="file" name="image" id="vehicle-image" accept=".jpg,.jpeg,.png" hidden>
                </label>
            </div>
            <p class="fleet-field-help">Supported: JPG, PNG. Max size: 2MB</p>
        </div>
    </div>

    <div class="fleet-panel fleet-vehicle-form-card">
        <div class="fleet-form-tabs" role="tablist">
            <button type="button" class="fleet-form-tab active" data-tab="specifications">Specifications</button>
            <button type="button" class="fleet-form-tab" data-tab="registration">Registration &amp; Docs</button>
            <button type="button" class="fleet-form-tab" data-tab="financials">Financials</button>
            <button type="button" class="fleet-form-tab" data-tab="gps">GPS Setup</button>
        </div>

        <div class="fleet-panel-body">
            <div class="fleet-tab-panel active" data-panel="specifications">
                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>Registration Number <span class="required">*</span></label>
                        <input type="text" name="registration_number" class="fleet-input" value="{{ $field('registration_number') }}" placeholder="e.g. TN-01-AB-1234" required>
                    </div>
                    <div class="fleet-form-group">
                        <label>Vehicle Name/Alias</label>
                        <input type="text" name="name" class="fleet-input" value="{{ $field('name') }}" placeholder="e.g. Red Truck 1">
                    </div>
                </div>

                <div class="fleet-form-row fleet-form-row-3">
                    <div class="fleet-form-group">
                        <label>Type <span class="required">*</span></label>
                        <div class="fleet-form-select-wrap">
                            <select name="type" id="vehicle-type-select" class="fleet-input" required>
                                <option value="">Select</option>
                                @foreach ($formOptions['types'] as $type)
                                    <option value="{{ $type }}" {{ $field('type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="fleet-form-add-btn" id="open-add-vehicle-type-modal">
                                <i class="fa fa-plus"></i> Add Type
                            </button>
                        </div>
                    </div>
                    <div class="fleet-form-group">
                        <label>Color <span class="required">*</span></label>
                        <div class="fleet-color-input-wrap">
                            <input type="color" id="vehicle-color-picker" value="{{ $colorValue }}" class="fleet-color-picker">
                            <input type="text" name="color" id="vehicle-color-hex" class="fleet-input fleet-color-hex" value="{{ $colorValue }}" maxlength="20" required>
                        </div>
                    </div>
                    <div class="fleet-form-group">
                        <label>Current Status</label>
                        <select name="status" class="fleet-input">
                            @foreach ($formOptions['statuses'] as $status)
                                <option value="{{ $status }}" {{ $field('status', 'Active') === $status ? 'selected' : '' }}>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="fleet-form-row fleet-form-row-3">
                    <div class="fleet-form-group">
                        <label>Fuel Type</label>
                        <select name="fuel_type" class="fleet-input">
                            <option value="">Select Fuel Type</option>
                            @foreach ($formOptions['fuel_types'] as $fuelType)
                                <option value="{{ $fuelType }}" {{ $field('fuel_type') === $fuelType ? 'selected' : '' }}>{{ $fuelType }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fleet-form-group">
                        <label>Fuel Efficiency (km/L)</label>
                        <input type="number" step="0.1" min="0" name="fuel_efficiency" class="fleet-input" value="{{ $field('fuel_efficiency') }}" placeholder="e.g. 15.5">
                    </div>
                    <div class="fleet-form-group">
                        <label>Opening Fuel</label>
                        <input type="number" step="0.1" min="0" name="opening_fuel" id="opening-fuel" class="fleet-input" value="{{ $field('opening_fuel') }}" placeholder="Initial Fuel">
                    </div>
                </div>

                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>Current Fuel</label>
                        <input type="text" id="current-fuel-display" class="fleet-input is-readonly" value="{{ $currentFuel }}" readonly>
                    </div>
                    <div class="fleet-form-group">
                        <label>Fuel Tank Capacity (L)</label>
                        <input type="number" step="0.1" min="1" name="fuel_capacity" class="fleet-input" value="{{ $field('fuel_capacity', 50) }}">
                    </div>
                </div>

                <div class="fleet-form-divider"></div>

                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>Vehicle Group <span class="required">*</span></label>
                        <select name="vehicle_group" class="fleet-input" required>
                            <option value="">Select Group</option>
                            @foreach ($formOptions['groups'] as $group)
                                <option value="{{ $group }}" {{ $field('vehicle_group') === $group ? 'selected' : '' }}>{{ $group }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fleet-form-group">
                        <label>Assign Driver</label>
                        <select name="driver" class="fleet-input">
                            <option value="">Unassigned</option>
                            @foreach ($formOptions['drivers'] as $driver)
                                <option value="{{ $driver }}" {{ $field('driver') === $driver ? 'selected' : '' }}>{{ $driver }}</option>
                            @endforeach
                        </select>
                        <p class="fleet-field-help">Assign a default driver for tracking/history.</p>
                    </div>
                </div>

                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>Model <span class="required">*</span></label>
                        <div class="fleet-form-select-wrap">
                            <select name="model" id="vehicle-model-select" class="fleet-input" required>
                                <option value="">Select Model</option>
                                @foreach ($formOptions['models'] as $model)
                                    <option value="{{ $model }}" {{ $field('model') === $model ? 'selected' : '' }}>{{ $model }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="fleet-form-add-btn" id="open-add-model-modal">
                                <i class="fa fa-plus"></i> Add Model
                            </button>
                        </div>
                    </div>
                    <div class="fleet-form-group">
                        <label>Manufacturer <span class="required">*</span></label>
                        <div class="fleet-form-select-wrap">
                            <select name="manufacturer" id="vehicle-manufacturer-select" class="fleet-input" required>
                                <option value="">Select Manufacturer</option>
                                @foreach ($formOptions['manufacturers'] as $manufacturer)
                                    <option value="{{ $manufacturer }}" {{ $field('manufacturer') === $manufacturer ? 'selected' : '' }}>{{ $manufacturer }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="fleet-form-add-btn" id="open-add-manufacturer-modal">
                                <i class="fa fa-plus"></i> Add Manufacturer
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="fleet-tab-panel" data-panel="registration">
                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>Chassis Number</label>
                        <input type="text" name="chassis_number" class="fleet-input" value="{{ $field('chassis_number') }}" placeholder="Chassis number">
                    </div>
                    <div class="fleet-form-group">
                        <label>Engine Number</label>
                        <input type="text" name="engine_number" class="fleet-input" value="{{ $field('engine_number') }}" placeholder="Engine number">
                    </div>
                </div>
                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>Registration Date</label>
                        <input type="date" name="registration_date" class="fleet-input" value="{{ $dateField('registration_date') }}">
                    </div>
                    <div class="fleet-form-group">
                        <label>Registration Expiry</label>
                        <input type="date" name="registration_expiry" class="fleet-input" value="{{ $dateField('registration_expiry') }}">
                    </div>
                </div>
                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>Insurance Provider</label>
                        <input type="text" name="insurance_provider" class="fleet-input" value="{{ $field('insurance_provider') }}" placeholder="Insurance company">
                    </div>
                    <div class="fleet-form-group">
                        <label>Insurance Policy No.</label>
                        <input type="text" name="insurance_policy_no" class="fleet-input" value="{{ $field('insurance_policy_no') }}" placeholder="Policy number">
                    </div>
                </div>
                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>Insurance Expiry</label>
                        <input type="date" name="insurance_expiry" class="fleet-input" value="{{ $dateField('insurance_expiry') }}">
                    </div>
                    <div class="fleet-form-group">
                        <label>Fitness Certificate No.</label>
                        <input type="text" name="fitness_certificate_no" class="fleet-input" value="{{ $field('fitness_certificate_no') }}" placeholder="Certificate number">
                    </div>
                </div>
                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>Fitness Expiry</label>
                        <input type="date" name="fitness_expiry" class="fleet-input" value="{{ $dateField('fitness_expiry') }}">
                    </div>
                </div>
            </div>

            <div class="fleet-tab-panel" data-panel="financials">
                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>Owner Type</label>
                        <select name="owner_type" class="fleet-input">
                            @foreach ($formOptions['owner_types'] as $ownerType)
                                <option value="{{ $ownerType }}" {{ $field('owner_type', 'Owned') === $ownerType ? 'selected' : '' }}>{{ $ownerType }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fleet-form-group">
                        <label>Purchase Date</label>
                        <input type="date" name="purchase_date" class="fleet-input" value="{{ $dateField('purchase_date') }}">
                    </div>
                </div>
                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>Purchase Price</label>
                        <input type="number" step="0.01" min="0" name="purchase_price" class="fleet-input" value="{{ $field('purchase_price') }}" placeholder="0.00">
                    </div>
                    <div class="fleet-form-group">
                        <label>Lease Start</label>
                        <input type="date" name="lease_start" class="fleet-input" value="{{ $dateField('lease_start') }}">
                    </div>
                </div>
                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>Lease End</label>
                        <input type="date" name="lease_end" class="fleet-input" value="{{ $dateField('lease_end') }}">
                    </div>
                </div>
            </div>

            <div class="fleet-tab-panel" data-panel="gps">
                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>GPS Device ID</label>
                        <input type="text" name="gps_device_id" class="fleet-input" value="{{ $field('gps_device_id') }}" placeholder="Device ID">
                    </div>
                    <div class="fleet-form-group">
                        <label>IMEI Number</label>
                        <input type="text" name="gps_imei" class="fleet-input" value="{{ $field('gps_imei') }}" placeholder="IMEI">
                    </div>
                </div>
                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>SIM Number</label>
                        <input type="text" name="gps_sim_number" class="fleet-input" value="{{ $field('gps_sim_number') }}" placeholder="SIM number">
                    </div>
                    <div class="fleet-form-group fleet-form-group-checkbox">
                        <label class="fleet-checkbox-label">
                            <input type="checkbox" name="gps_enabled" value="1" {{ old('gps_enabled', optional($vehicle)->gps_enabled) ? 'checked' : '' }}>
                            Enable GPS Tracking
                        </label>
                    </div>
                </div>
            </div>

            <div class="fleet-form-actions">
                <button type="submit" class="fleet-btn fleet-btn-primary"><i class="fa fa-save"></i> {{ $submitLabel ?? 'Save Vehicle' }}</button>
                <a href="{{ $cancelUrl ?? route('vehicles.index') }}" class="fleet-btn fleet-btn-light">Cancel</a>
            </div>
        </div>
    </div>
</div>
