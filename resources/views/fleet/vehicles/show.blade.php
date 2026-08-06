@extends('layouts.fleet')

@section('title', 'Vehicle Details')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-vehicle-form.css') }}?v=2">
@endpush

@section('content')
@php
    $formatDate = function ($date) {
        return $date ? $date->format('Y-m-d') : '-';
    };
    $display = function ($value) {
        return ($value !== null && $value !== '') ? $value : '-';
    };
    $imageUrl = $vehicle->image_path ? asset('storage/' . $vehicle->image_path) : null;
    $documents = $vehicle->documentItems();
    $fuelPct = $vehicle->fuelBalancePercent();
    $fuelSpaceAvailable = $vehicle->availableFuelCapacity();
@endphp

<div class="fleet-page-head">
    <div class="fleet-vdetail-head">
        <h1 class="fleet-page-title"><i class="fa fa-truck fleet-vdetail-title-icon"></i> Vehicle Details</h1>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('vehicles.index') }}">Vehicle</a></li>
        <li>Vehicle Details</li>
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

<div class="fleet-vdetail-stats">
    <div class="fleet-vdetail-stat-card fuel">
        <div class="fleet-vdetail-stat-icon"><i class="fa fa-tint"></i></div>
        <div class="fleet-vdetail-stat-body">
            <span>Fuel Balance</span>
            <strong>{{ number_format($vehicle->fuelBalance(), 0) }} L</strong>
            <div class="fleet-vdetail-stat-bar">
                <span style="width: {{ $fuelPct }}%;"></span>
            </div>
        </div>
    </div>
    <div class="fleet-vdetail-stat-card distance">
        <div class="fleet-vdetail-stat-icon"><i class="fa fa-road"></i></div>
        <div class="fleet-vdetail-stat-body">
            <span>Total Distance</span>
            <strong>{{ number_format($totalDistance, 0) }} KM</strong>
            <small>Last sync value</small>
        </div>
    </div>
    <div class="fleet-vdetail-stat-card trips">
        <div class="fleet-vdetail-stat-icon"><i class="fa fa-calendar-check-o"></i></div>
        <div class="fleet-vdetail-stat-body">
            <span>Total Trips</span>
            <strong>{{ $totalTrips }}</strong>
            <small>All-time bookings</small>
        </div>
    </div>
    <div class="fleet-vdetail-stat-card status">
        <div class="fleet-vdetail-stat-icon"><i class="fa fa-check-circle"></i></div>
        <div class="fleet-vdetail-stat-body">
            <span>Booking Status</span>
            <strong class="fleet-vdetail-availability {{ $bookingAvailability['class'] }}">{{ $bookingAvailability['label'] }}</strong>
            <small>{{ $bookingAvailability['hint'] }}</small>
        </div>
    </div>
</div>

<div class="fleet-vdetail-layout">
    <div class="fleet-vdetail-sidebar">
        <div class="fleet-panel fleet-vdetail-profile-card">
            <div class="fleet-vdetail-profile-top">
                <div class="fleet-vdetail-avatar">
                    @if ($imageUrl)
                        <img src="{{ $imageUrl }}" alt="{{ $vehicle->displayName() }}">
                    @else
                        <i class="fa fa-truck"></i>
                    @endif
                </div>
                <div class="fleet-vdetail-profile-name">{{ $vehicle->displayName() }}</div>
                <div class="fleet-vdetail-profile-plate">{{ $vehicle->registration_number }}</div>
                <span class="fleet-vdetail-type-badge">{{ $vehicle->typeBadge() }}</span>
            </div>

            <div class="fleet-vdetail-profile-meta">
                <div class="fleet-vdetail-meta-row">
                    <span>Group</span>
                    <strong>{{ $display($vehicle->vehicle_group) }}</strong>
                </div>
                <div class="fleet-vdetail-meta-row">
                    <span>Ownership</span>
                    <span class="fleet-vdetail-owner-badge {{ $vehicle->ownerBadgeClass() }}">{{ $display($vehicle->owner_type) }}</span>
                </div>
            </div>

            <div class="fleet-vdetail-profile-actions">
                <a href="{{ route('vehicles.edit', $vehicle) }}" class="fleet-btn fleet-btn-primary fleet-vdetail-edit-btn">
                    <i class="fa fa-pencil"></i> Edit
                </a>
                <button type="button" class="fleet-btn fleet-vdetail-fuel-btn{{ $fuelSpaceAvailable <= 0 ? ' is-disabled' : '' }}" id="fleet-vdetail-add-fuel"{{ $fuelSpaceAvailable <= 0 ? ' disabled' : '' }} title="{{ $fuelSpaceAvailable <= 0 ? 'Tank is full' : 'Record fuel refill' }}">
                    <i class="fa fa-plus"></i> Fuel
                </button>
            </div>
        </div>

        <div class="fleet-panel fleet-vdetail-docs-card">
            <div class="fleet-vdetail-docs-head">
                <i class="fa fa-paperclip"></i>
                <span>Documents</span>
            </div>
            <div class="fleet-vdetail-docs-body">
                @forelse ($documents as $document)
                    <div class="fleet-vdetail-doc-item">
                        <strong>{{ $document['label'] }}</strong>
                        <span>{{ $document['reference'] ?: '-' }}</span>
                        <small>Expires: {{ $document['expiry'] }}</small>
                    </div>
                @empty
                    <div class="fleet-vdetail-doc-empty">No documents on file.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="fleet-vdetail-main">
        <div class="fleet-panel fleet-vdetail-tabs-card">
            <div class="fleet-vdetail-tabs" role="tablist">
                <button type="button" class="fleet-vdetail-tab" data-tab="specs">Full Specs</button>
                <button type="button" class="fleet-vdetail-tab active" data-tab="fuel">Fuel Lifecycle</button>
                <button type="button" class="fleet-vdetail-tab" data-tab="bookings">Bookings</button>
                <button type="button" class="fleet-vdetail-tab" data-tab="service">Service</button>
                <button type="button" class="fleet-vdetail-tab" data-tab="geofence">Geofence</button>
            </div>

            <div class="fleet-vdetail-panel active" data-panel="fuel">
                <div class="fleet-vdetail-fuel-layout">
                    <div class="fleet-vdetail-fuel-main">
                        <div class="fleet-vdetail-section-head">
                            <i class="fa fa-tint"></i>
                            <h3>Fuel Balance Breakdown</h3>
                        </div>

                        <div class="fleet-vdetail-fuel-grid">
                            <div class="fleet-vdetail-fuel-metric">
                                <span>OPENING</span>
                                <strong>{{ number_format($vehicle->openingFuelBalance(), 0) }} L</strong>
                            </div>
                            <div class="fleet-vdetail-fuel-metric refuel">
                                <span>REFUELLED</span>
                                <strong>+{{ number_format($vehicle->refueledAmount(), 0) }} L</strong>
                            </div>
                            <div class="fleet-vdetail-fuel-metric consume">
                                <span>CONSUMED</span>
                                <strong>-{{ number_format($vehicle->consumedFuel(), 0) }} L</strong>
                            </div>
                        </div>

                        <div class="fleet-vdetail-fuel-inventory">
                            Current Estimated Inventory:
                            <strong>{{ number_format($vehicle->fuelBalance(), 0) }} Liters</strong>
                        </div>

                        <p class="fleet-vdetail-fuel-note">
                            Calculation: Opening + Total Refills - Trip Consumption (Distance / Efficiency).
                        </p>
                    </div>

                    <div class="fleet-vdetail-efficiency-card">
                        <div class="fleet-vdetail-efficiency-head">Fueling Efficiency Profile</div>
                        <div class="fleet-vdetail-efficiency-row">
                            <span>Vehicle Efficiency</span>
                            <strong>{{ $vehicle->fuel_efficiency ? number_format((float) $vehicle->fuel_efficiency, 2) . ' KM/L' : '0.00 KM/L' }}</strong>
                        </div>
                        <div class="fleet-vdetail-efficiency-row">
                            <span>Assigned Driver</span>
                            <strong>{{ $display($vehicle->driver ?: 'N/A') }}</strong>
                        </div>
                        <div class="fleet-vdetail-efficiency-row">
                            <span>Sync Status</span>
                            <strong class="fleet-vdetail-sync-active"><i class="fa fa-check-circle"></i> Active</strong>
                        </div>
                    </div>
                </div>

                <div class="fleet-vdetail-fuel-history">
                    <div class="fleet-vdetail-fuel-history-head">
                        <h4>Refill History</h4>
                        <button type="button" class="fleet-btn fleet-btn-primary fleet-btn-sm{{ $fuelSpaceAvailable <= 0 ? ' is-disabled' : '' }}" id="fleet-vdetail-add-fuel-tab"{{ $fuelSpaceAvailable <= 0 ? ' disabled' : '' }}>
                            <i class="fa fa-plus"></i> Add Refill
                        </button>
                    </div>
                    <div class="fleet-table-wrap">
                        <table class="fleet-vehicle-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Liters</th>
                                    <th>Cost</th>
                                    <th>Method</th>
                                    <th>Reference</th>
                                    <th>Notes</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($vehicle->fuelRefills as $index => $refill)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $refill->formattedDate() }}</td>
                                    <td><strong>+{{ number_format((float) $refill->liters, 2) }} L</strong></td>
                                    <td>{{ $refill->cost ? format_kes($refill->cost) : '-' }}</td>
                                    <td>{{ $refill->payment_method }}</td>
                                    <td>{{ $refill->reference_no ?: '-' }}</td>
                                    <td>{{ $refill->notes ?: '-' }}</td>
                                    <td>
                                        <form action="{{ route('vehicles.fuel-refills.destroy', [$vehicle, $refill]) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Remove this fuel refill? Tank balance will be reduced.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="fleet-action-btn delete" title="Delete"><i class="fa fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="fleet-empty-row">No fuel refills recorded yet.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="fleet-vdetail-panel" data-panel="specs">
                <div class="fleet-vdetail-section-head">
                    <i class="fa fa-list-alt"></i>
                    <h3>Full Specifications</h3>
                </div>
                <div class="fleet-detail-grid">
                    <div class="fleet-detail-item"><span>Registration Number</span><strong>{{ $vehicle->registration_number }}</strong></div>
                    <div class="fleet-detail-item"><span>Vehicle Name/Alias</span><strong>{{ $display($vehicle->name) }}</strong></div>
                    <div class="fleet-detail-item"><span>Type</span><strong>{{ $display($vehicle->type) }}</strong></div>
                    <div class="fleet-detail-item"><span>Color</span><strong><span class="fleet-color-swatch" style="background: {{ $vehicle->color }};"></span> {{ $vehicle->color }}</strong></div>
                    <div class="fleet-detail-item"><span>Status</span><strong>{{ $display($vehicle->status) }}</strong></div>
                    <div class="fleet-detail-item"><span>Fuel Type</span><strong>{{ $display($vehicle->fuel_type) }}</strong></div>
                    <div class="fleet-detail-item"><span>Fuel Efficiency</span><strong>{{ $vehicle->fuel_efficiency ? $vehicle->fuel_efficiency . ' km/L' : '-' }}</strong></div>
                    <div class="fleet-detail-item"><span>Opening Fuel</span><strong>{{ $display($vehicle->opening_fuel) }} L</strong></div>
                    <div class="fleet-detail-item"><span>Current Fuel</span><strong>{{ $display($vehicle->current_fuel) }} L</strong></div>
                    <div class="fleet-detail-item"><span>Fuel Capacity</span><strong>{{ $display($vehicle->fuel_capacity) }} L</strong></div>
                    <div class="fleet-detail-item"><span>Vehicle Group</span><strong>{{ $display($vehicle->vehicle_group) }}</strong></div>
                    <div class="fleet-detail-item"><span>Driver</span><strong>{{ $display($vehicle->driver ?: 'Unassigned') }}</strong></div>
                    <div class="fleet-detail-item"><span>Model</span><strong>{{ $display($vehicle->model) }}</strong></div>
                    <div class="fleet-detail-item"><span>Manufacturer</span><strong>{{ $display($vehicle->manufacturer) }}</strong></div>
                    <div class="fleet-detail-item"><span>Chassis Number</span><strong>{{ $display($vehicle->chassis_number) }}</strong></div>
                    <div class="fleet-detail-item"><span>Engine Number</span><strong>{{ $display($vehicle->engine_number) }}</strong></div>
                    <div class="fleet-detail-item"><span>Owner Type</span><strong>{{ $display($vehicle->owner_type) }}</strong></div>
                    <div class="fleet-detail-item"><span>Purchase Price</span><strong>{{ $vehicle->purchase_price ? format_kes($vehicle->purchase_price) : '-' }}</strong></div>
                    <div class="fleet-detail-item"><span>Registration Expiry</span><strong>{{ $formatDate($vehicle->registration_expiry) }}</strong></div>
                    <div class="fleet-detail-item"><span>Insurance Expiry</span><strong>{{ $formatDate($vehicle->insurance_expiry) }}</strong></div>
                    <div class="fleet-detail-item"><span>Fitness Expiry</span><strong>{{ $formatDate($vehicle->fitness_expiry) }}</strong></div>
                    <div class="fleet-detail-item"><span>GPS Tracking</span><strong>{{ $vehicle->gps_enabled ? 'Enabled' : 'Disabled' }}</strong></div>
                </div>
            </div>

            <div class="fleet-vdetail-panel" data-panel="bookings">
                <div class="fleet-vdetail-section-head">
                    <i class="fa fa-calendar"></i>
                    <h3>Bookings</h3>
                </div>
                <div class="fleet-table-wrap">
                    <table class="fleet-vehicle-table">
                        <thead>
                            <tr>
                                <th>Trip Code</th>
                                <th>Customer</th>
                                <th>Route</th>
                                <th>Start Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($trips as $trip)
                            <tr>
                                <td><strong>{{ $trip->displayTripCode() }}</strong></td>
                                <td>{{ $trip->customer_name }}</td>
                                <td>{{ $trip->routeLocationShort($trip->pickup_location) }} → {{ $trip->routeLocationShort($trip->drop_location) }}</td>
                                <td>{{ optional($trip->start_date)->format('d M Y') ?: '-' }}</td>
                                <td><span class="fleet-status-tag fleet-status-active">{{ $trip->statusLabel() }}</span></td>
                                <td>
                                    <a href="{{ route('trips.show', $trip) }}" class="fleet-action-btn view" title="View trip"><i class="fa fa-eye"></i></a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="fleet-empty-row">No bookings assigned to this vehicle yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="fleet-vdetail-panel" data-panel="service">
                <div class="fleet-vdetail-section-head">
                    <i class="fa fa-wrench"></i>
                    <h3>Service History</h3>
                </div>
                @if ($vehicle->maintenances->count() > 0)
                <div class="fleet-table-wrap">
                    <table class="fleet-vehicle-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Schedule</th>
                                <th>Mechanic</th>
                                <th>Priority</th>
                                <th>Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vehicle->maintenances as $maintenance)
                            <tr>
                                <td>{{ $maintenance->statusLabel() }}</td>
                                <td>{{ $maintenance->formattedDateRange() }}</td>
                                <td>{{ $maintenance->mechanic ?: '-' }}</td>
                                <td>{{ $maintenance->priority }}</td>
                                <td>{{ format_kes($maintenance->total_cost) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="fleet-vdetail-empty-state">
                    <i class="fa fa-wrench"></i>
                    <p>No service records yet.</p>
                    <a href="{{ route('maintenance.create') }}" class="fleet-btn fleet-btn-primary">Add Maintenance</a>
                </div>
                @endif
            </div>

            <div class="fleet-vdetail-panel" data-panel="geofence">
                <div class="fleet-vdetail-section-head">
                    <i class="fa fa-map-marker"></i>
                    <h3>Geofence &amp; GPS</h3>
                </div>
                @if ($vehicle->gps_enabled)
                    <div class="fleet-detail-grid">
                        <div class="fleet-detail-item"><span>GPS Device ID</span><strong>{{ $display($vehicle->gps_device_id) }}</strong></div>
                        <div class="fleet-detail-item"><span>IMEI Number</span><strong>{{ $display($vehicle->gps_imei) }}</strong></div>
                        <div class="fleet-detail-item"><span>SIM Number</span><strong>{{ $display($vehicle->gps_sim_number) }}</strong></div>
                        <div class="fleet-detail-item"><span>Tracking Status</span><strong class="fleet-vdetail-sync-active"><i class="fa fa-check-circle"></i> Active</strong></div>
                    </div>
                @else
                    <div class="fleet-vdetail-empty-state">
                        <i class="fa fa-map-marker"></i>
                        <p>GPS tracking is not enabled for this vehicle.</p>
                        <a href="{{ route('vehicles.edit', $vehicle) }}" class="fleet-btn fleet-btn-primary">Configure GPS</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="fleet-fuel-refill-modal" tabindex="-1" role="dialog" aria-labelledby="fleet-fuel-refill-title">
    <div class="modal-dialog" role="document">
        <div class="modal-content fleet-fuel-modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="fleet-fuel-refill-title">
                    <i class="fa fa-tint"></i> Record Fuel Refill
                </h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="{{ route('vehicles.fuel-refills.store', $vehicle) }}">
                @csrf
                <div class="modal-body">
                    <div class="fleet-fuel-modal-summary">
                        <span>Current: <strong>{{ number_format($vehicle->fuelBalance(), 2) }} L</strong></span>
                        <span>Capacity: <strong>{{ number_format((float) $vehicle->fuel_capacity, 2) }} L</strong></span>
                        <span>Available: <strong>{{ number_format($vehicle->availableFuelCapacity(), 2) }} L</strong></span>
                    </div>

                    <div class="fleet-fuel-modal-grid">
                        <div class="fleet-field">
                            <label>Refill Date <span class="required">*</span></label>
                            <input type="date" name="refill_date" class="fleet-input" value="{{ old('refill_date', now()->format('Y-m-d')) }}" required>
                        </div>
                        <div class="fleet-field">
                            <label>Liters <span class="required">*</span></label>
                            <input type="number" step="0.01" min="0.01" max="{{ $vehicle->availableFuelCapacity() }}" name="liters" id="vehicle-fuel-liters" class="fleet-input js-vehicle-fuel-calc" value="{{ old('liters') }}" placeholder="0.00" required>
                            <small class="fleet-field-hint">Max {{ number_format($vehicle->availableFuelCapacity(), 2) }} L</small>
                        </div>
                        <div class="fleet-field">
                            <label>Cost per Liter (KSh)</label>
                            <input type="number" step="0.01" min="0" name="cost_per_liter" id="vehicle-fuel-cost-per-liter" class="fleet-input js-vehicle-fuel-calc" value="{{ old('cost_per_liter') }}" placeholder="0.00">
                        </div>
                        <div class="fleet-field">
                            <label>Total Cost</label>
                            <input type="text" id="vehicle-fuel-total-cost" class="fleet-input is-readonly" value="" placeholder="Auto-calculated" readonly>
                            <input type="hidden" name="cost" id="vehicle-fuel-total-cost-value" value="{{ old('cost') }}">
                        </div>
                        <div class="fleet-field">
                            <label>Payment Method <span class="required">*</span></label>
                            <select name="payment_method" class="fleet-select fleet-input" required>
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method }}" {{ old('payment_method', 'Cash') === $method ? 'selected' : '' }}>{{ $method }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fleet-field">
                            <label>Reference No.</label>
                            <input type="text" name="reference_no" class="fleet-input" value="{{ old('reference_no') }}" placeholder="Receipt / M-Pesa code">
                        </div>
                        <div class="fleet-field fleet-field-wide">
                            <label>Notes</label>
                            <input type="text" name="notes" class="fleet-input" value="{{ old('notes') }}" placeholder="Station name, pump no., etc.">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="fleet-btn fleet-btn-outline" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="fleet-btn fleet-btn-primary"><i class="fa fa-check"></i> Save Refill</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var tabs = document.querySelectorAll('.fleet-vdetail-tab');
    var panels = document.querySelectorAll('.fleet-vdetail-panel');

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var target = this.getAttribute('data-tab');

            tabs.forEach(function (item) { item.classList.remove('active'); });
            panels.forEach(function (panel) {
                panel.classList.toggle('active', panel.getAttribute('data-panel') === target);
            });

            this.classList.add('active');
        });
    });

    var fuelBtn = document.getElementById('fleet-vdetail-add-fuel');
    var fuelTabBtn = document.getElementById('fleet-vdetail-add-fuel-tab');
    var fuelModal = $('#fleet-fuel-refill-modal');

    function openFuelModal() {
        fuelModal.modal('show');
    }

    if (fuelBtn) {
        fuelBtn.addEventListener('click', openFuelModal);
    }

    if (fuelTabBtn) {
        fuelTabBtn.addEventListener('click', openFuelModal);
    }

    @if (session('open_fuel_modal') || $errors->has('liters'))
        openFuelModal();
    @endif

    var vehicleLiters = document.getElementById('vehicle-fuel-liters');
    var vehicleCostPerLiter = document.getElementById('vehicle-fuel-cost-per-liter');
    var vehicleTotalDisplay = document.getElementById('vehicle-fuel-total-cost');
    var vehicleTotalValue = document.getElementById('vehicle-fuel-total-cost-value');

    function updateVehicleFuelTotal() {
        if (!vehicleLiters || !vehicleCostPerLiter || !vehicleTotalDisplay || !vehicleTotalValue) {
            return;
        }

        var liters = parseFloat(vehicleLiters.value);
        var costPerLiter = parseFloat(vehicleCostPerLiter.value);

        if (!isFinite(liters) || !isFinite(costPerLiter) || liters <= 0 || costPerLiter < 0) {
            vehicleTotalDisplay.value = '';
            vehicleTotalValue.value = '';
            return;
        }

        var total = Math.round(liters * costPerLiter * 100) / 100;
        vehicleTotalDisplay.value = 'KSh ' + total.toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        vehicleTotalValue.value = total.toFixed(2);
    }

    if (vehicleLiters && vehicleCostPerLiter) {
        vehicleLiters.addEventListener('input', updateVehicleFuelTotal);
        vehicleCostPerLiter.addEventListener('input', updateVehicleFuelTotal);
        updateVehicleFuelTotal();
    }
})();
</script>
@endpush
