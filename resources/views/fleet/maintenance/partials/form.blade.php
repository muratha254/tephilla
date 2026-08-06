@php
    $maintenance = $maintenance ?? null;
    $checklistItems = $checklistItems ?? [];
    $selectedVehicle = old('fleet_vehicle_id', $maintenance->fleet_vehicle_id ?? '');
    $selectedStatus = old('status', $maintenance->status ?? 'Planned');
    $selectedStart = old('start_date', optional($maintenance?->start_date)->format('Y-m-d'));
    $selectedEnd = old('end_date', optional($maintenance?->end_date)->format('Y-m-d'));
    $selectedDetails = old('service_details', $maintenance->service_details ?? '');
    $selectedCost = old('total_cost', $maintenance->total_cost ?? '0.00');
    $selectedVendor = old('fleet_vehicle_vendor_id', $maintenance->fleet_vehicle_vendor_id ?? '');
    $selectedMechanic = old('mechanic', $maintenance->mechanic ?? '');
    $selectedPriority = old('priority', $maintenance->priority ?? 'Medium');
@endphp

<form action="{{ $formAction }}" method="POST" enctype="multipart/form-data" class="fleet-maint-form">
    @csrf
    @if (! empty($formMethod) && strtoupper($formMethod) !== 'POST')
        @method($formMethod)
    @endif

    <div class="fleet-maint-top">
        <div class="fleet-maint-section fleet-maint-section-blue">
            <div class="fleet-maint-section-head">
                <i class="fa fa-truck"></i>
                <h2>Vehicle Service Details</h2>
            </div>
            <div class="fleet-maint-section-body">
                <div class="fleet-maint-row fleet-maint-row-2">
                    <div class="fleet-maint-field">
                        <label>Select Vehicle <span class="required">*</span></label>
                        <select name="fleet_vehicle_id" class="fleet-maint-input" required>
                            <option value="">Select Vehicle</option>
                            @foreach ($formOptions['vehicles'] as $vehicle)
                                <option value="{{ $vehicle->id }}" {{ (string) $selectedVehicle === (string) $vehicle->id ? 'selected' : '' }}>
                                    {{ $vehicle->displayName() }} ({{ $vehicle->registration_number }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fleet-maint-field">
                        <label>Maintenance Status <span class="required">*</span></label>
                        <select name="status" class="fleet-maint-input" required>
                            @foreach ($formOptions['statuses'] as $status)
                                <option value="{{ $status }}" {{ $selectedStatus === $status ? 'selected' : '' }}>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="fleet-maint-row fleet-maint-row-2">
                    <div class="fleet-maint-field">
                        <label>Start Date <span class="required">*</span></label>
                        <input type="date" name="start_date" class="fleet-maint-input" value="{{ $selectedStart }}" required>
                    </div>
                    <div class="fleet-maint-field">
                        <label>End Date <span class="required">*</span></label>
                        <input type="date" name="end_date" class="fleet-maint-input" value="{{ $selectedEnd }}" required>
                    </div>
                </div>

                <div class="fleet-maint-field">
                    <label>Service Details <span class="required">*</span></label>
                    <textarea name="service_details" class="fleet-maint-textarea" rows="5" placeholder="Details" required>{{ $selectedDetails }}</textarea>
                </div>
            </div>
        </div>

        <div class="fleet-maint-section fleet-maint-section-green">
            <div class="fleet-maint-section-head">
                <i class="fa fa-money"></i>
                <h2>Financials</h2>
            </div>
            <div class="fleet-maint-section-body">
                <div class="fleet-maint-field">
                    <label>Total Cost</label>
                    <div class="fleet-maint-cost-wrap">
                        <span class="fleet-maint-cost-prefix">{{ kes_symbol() }}</span>
                        <input type="number" step="0.01" min="0" name="total_cost" class="fleet-maint-input fleet-maint-cost-input" value="{{ $selectedCost }}" placeholder="0.00">
                    </div>
                </div>

                <div class="fleet-maint-field">
                    <label>Select Vendor</label>
                    <select name="fleet_vehicle_vendor_id" class="fleet-maint-input">
                        <option value="">Select Vendor</option>
                        @foreach ($formOptions['vendors'] as $vendor)
                            <option value="{{ $vendor->id }}" {{ (string) $selectedVendor === (string) $vendor->id ? 'selected' : '' }}>
                                {{ $vendor->company }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="fleet-maint-field">
                    <label>Upload Receipt/Invoice</label>
                    @if ($maintenance?->receipt_path)
                        <div class="fleet-maint-current-file">
                            Current: <a href="{{ asset('storage/' . $maintenance->receipt_path) }}" target="_blank" rel="noopener">View receipt</a>
                        </div>
                    @endif
                    <div class="fleet-maint-file-wrap">
                        <input type="file" name="receipt" id="maintenance-receipt" class="fleet-maint-file-input" accept=".jpg,.jpeg,.png,.pdf">
                        <label for="maintenance-receipt" class="fleet-maint-file-label">
                            <span class="fleet-maint-file-name" id="maintenance-receipt-name">{{ $maintenance?->receipt_path ? 'Replace file' : 'Choose File' }}</span>
                            <span class="fleet-maint-file-browse">Browse</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="fleet-maint-bottom">
        <div class="fleet-maint-section fleet-maint-section-yellow">
            <div class="fleet-maint-section-head">
                <i class="fa fa-check-square-o"></i>
                <h2>Job Card Checklist</h2>
            </div>
            <div class="fleet-maint-section-body fleet-maint-checklist-body">
                <table class="fleet-maint-checklist-table">
                    <thead>
                        <tr>
                            <th class="col-done">Done</th>
                            <th>Task / Inspection Item</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($checklistItems as $index => $item)
                        <tr>
                            <td class="col-done">
                                <input type="hidden" name="checklist[{{ $index }}][task]" value="{{ $item['task'] }}">
                                <input type="checkbox" name="checklist[{{ $index }}][done]" value="1" class="fleet-maint-check" {{ ! empty($item['done']) ? 'checked' : '' }}>
                            </td>
                            <td>{{ $item['task'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="fleet-maint-section fleet-maint-section-teal">
            <div class="fleet-maint-section-head">
                <i class="fa fa-user"></i>
                <h2>Mechanic And Priority</h2>
            </div>
            <div class="fleet-maint-section-body">
                <div class="fleet-maint-field">
                    <label>Select Mechanic</label>
                    <select name="mechanic" class="fleet-maint-input">
                        <option value="">Select Mechanic</option>
                        @foreach ($formOptions['mechanics'] as $mechanic)
                            <option value="{{ $mechanic }}" {{ $selectedMechanic === $mechanic ? 'selected' : '' }}>{{ $mechanic }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="fleet-maint-field">
                    <label>Priority <span class="required">*</span></label>
                    <select name="priority" class="fleet-maint-input" required>
                        @foreach ($formOptions['priorities'] as $priority)
                            <option value="{{ $priority }}" {{ $selectedPriority === $priority ? 'selected' : '' }}>{{ $priority }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="fleet-maint-actions">
        <a href="{{ $cancelUrl ?? route('maintenance.index') }}" class="fleet-btn fleet-btn-outline">Cancel</a>
        <button type="submit" class="fleet-btn fleet-btn-primary"><i class="fa fa-save"></i> {{ $submitLabel ?? 'Save Maintenance' }}</button>
    </div>
</form>
