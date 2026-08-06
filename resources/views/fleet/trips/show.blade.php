@extends('layouts.fleet')

@section('title', 'Trip Details')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-trips.css') }}?v=10">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

@section('content')
@php
    $driver = $trip->driver;
    $vehicle = $trip->vehicle;
    $driverInitials = $driver ? $driver->avatarInitials() : '?';
    $pickupShort = $trip->routeLocationShort($trip->pickup_location);
    $dropShort = $trip->routeLocationShort($trip->drop_location);
@endphp

<div class="fleet-page-head">
    <div class="fleet-trip-detail-head">
        <h1 class="fleet-page-title">Trip Details - {{ $trip->displayTripCode() }}</h1>
        <form method="POST" action="{{ route('trips.update-status', $trip) }}" class="fleet-trip-status-form">
            @csrf
            @method('PATCH')
            <label class="fleet-trip-status-form-label" for="trip-detail-status">Status</label>
            <select name="status" id="trip-detail-status" class="fleet-trip-status-select {{ $trip->statusBadgeClass() }}" onchange="this.form.submit()">
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" {{ $trip->status === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('trips.index') }}">Trips</a></li>
        <li>Details</li>
    </ul>
</div>

@if (session('success'))
    @include('fleet.partials.payment_receipt_success')
@endif

<div class="fleet-trip-detail-layout">
    <div class="fleet-trip-detail-col fleet-trip-detail-driver">
        <div class="fleet-panel fleet-trip-detail-card">
            <div class="fleet-trip-driver-card">
                <div class="fleet-trip-driver-avatar">{{ $driverInitials }}</div>
                @if ($driver)
                    <div class="fleet-trip-driver-name">{{ $driver->name }}</div>
                    <div class="fleet-trip-driver-role">Driver</div>
                    <div class="fleet-trip-driver-meta">
                        <span>Mobile</span>
                        <a href="tel:{{ $driver->mobile }}">{{ $driver->mobile ?: '-' }}</a>
                    </div>
                    <div class="fleet-trip-driver-meta">
                        <span>License No</span>
                        <a href="#">{{ $driver->license_number ?: '-' }}</a>
                    </div>
                @else
                    <div class="fleet-trip-driver-name">Unassigned</div>
                    <div class="fleet-trip-driver-role">Driver</div>
                    <div class="fleet-trip-driver-meta">
                        <span>Mobile</span>
                        <strong>-</strong>
                    </div>
                    <div class="fleet-trip-driver-meta">
                        <span>License No</span>
                        <strong>-</strong>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="fleet-trip-detail-col fleet-trip-detail-main">
        <div class="fleet-trip-action-row">
            <a href="{{ route('trips.invoice-pdf', $trip) }}" class="fleet-trip-action-btn fleet-trip-action-link" target="_blank" rel="noopener">
                <i class="fa fa-file-text-o fleet-trip-action-icon invoice"></i>
                <span>Invoice</span>
            </a>
            <a href="{{ route('trips.invoice.edit', $trip) }}" class="fleet-trip-action-btn fleet-trip-action-link">
                <i class="fa fa-pencil fleet-trip-action-icon invoice"></i>
                <span>Edit Invoice</span>
            </a>
            <a href="{{ route('trips.expenses', $trip) }}" class="fleet-trip-action-btn fleet-trip-action-link">
                <i class="fa fa-money fleet-trip-action-icon expense"></i>
                <span>Expense</span>
            </a>
            <button type="button" class="fleet-trip-action-btn" data-action="Sms">
                <i class="fa fa-commenting-o fleet-trip-action-icon sms"></i>
                <span>Sms</span>
            </button>
            <button type="button" class="fleet-trip-action-btn" data-action="Email">
                <i class="fa fa-envelope-o fleet-trip-action-icon email"></i>
                <span>Email</span>
            </button>
            <a href="{{ route('trips.payments', $trip) }}" class="fleet-trip-action-btn fleet-trip-action-link">
                <i class="fa fa-credit-card fleet-trip-action-icon payment"></i>
                <span>Payment</span>
            </a>
        </div>

        <div class="fleet-panel fleet-trip-detail-card">
            <div class="fleet-trip-route-head">
                <h3>Trip Route</h3>
                <button type="button" class="fleet-trip-live-map-btn" id="fleet-live-map-open">
                    <i class="fa fa-map-marker"></i> Live Map
                </button>
            </div>
            <div class="fleet-trip-route-timeline">
                <div class="fleet-trip-route-step start">
                    <div class="fleet-trip-route-marker"><i class="fa fa-circle"></i></div>
                    <div class="fleet-trip-route-content">
                        <div class="fleet-trip-route-top">
                            <strong>{{ $pickupShort }}</strong>
                            <span class="fleet-trip-route-time">{{ $trip->formattedRouteTime($trip->start_time) }}</span>
                        </div>
                        <div class="fleet-trip-route-sub">Starting Point &bull; {{ $trip->formattedRouteDate($trip->start_date) }}</div>
                        <div class="fleet-trip-route-sub">Odometer: 1000 km</div>
                    </div>
                </div>

                <div class="fleet-trip-route-step destination">
                    <div class="fleet-trip-route-marker"><i class="fa fa-map-marker"></i></div>
                    <div class="fleet-trip-route-content">
                        <div class="fleet-trip-route-top">
                            <strong>{{ $dropShort }}</strong>
                            <span class="fleet-trip-route-time">{{ $trip->formattedRouteTime($trip->end_time) }}</span>
                        </div>
                        <div class="fleet-trip-route-sub">Destination &bull; {{ $trip->formattedRouteDate($trip->end_date) }}</div>
                        <div class="fleet-trip-route-sub">Total Distance: <span id="trip-route-distance">Loading...</span></div>
                        <div class="fleet-trip-route-sub">Odometer: N/A km</div>
                    </div>
                </div>

                <div class="fleet-trip-route-step end">
                    <div class="fleet-trip-route-marker"><i class="fa fa-check"></i></div>
                    <div class="fleet-trip-route-content">
                        <strong>End</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="fleet-trip-detail-col fleet-trip-detail-side">
        <div class="fleet-panel fleet-trip-detail-card">
            <div class="fleet-trip-info-section">
                <div class="fleet-trip-info-label"><i class="fa fa-user"></i> Customer</div>
                <div class="fleet-trip-info-name">{{ $trip->customer_name }}</div>
                <div class="fleet-trip-info-line">{{ $trip->customer_phone ?: '-' }}</div>
                <div class="fleet-trip-info-line">{{ $pickupShort }}, India</div>
            </div>
            <div class="fleet-trip-info-divider"></div>
            <div class="fleet-trip-info-section">
                <div class="fleet-trip-info-label"><i class="fa fa-truck"></i> Vehicle</div>
                @if ($vehicle)
                    <div class="fleet-trip-info-name">{{ $vehicle->displayName() }}</div>
                    <div class="fleet-trip-info-plate">{{ $vehicle->registration_number }}</div>
                    <div class="fleet-trip-info-line">Type: {{ $vehicle->type ?: 'Car' }}</div>
                @else
                    <div class="fleet-trip-info-name">No Vehicle</div>
                    <div class="fleet-trip-info-line">Type: -</div>
                @endif
            </div>
            @if ($trip->container_number || $trip->container_empty_drop_point)
            <div class="fleet-trip-info-divider"></div>
            <div class="fleet-trip-info-section">
                <div class="fleet-trip-info-label"><i class="fa fa-cube"></i> Container</div>
                @if ($trip->container_number)
                    <div class="fleet-trip-info-line"><strong>Number:</strong> {{ $trip->container_number }}</div>
                @endif
                @if ($trip->container_empty_drop_point)
                    <div class="fleet-trip-info-line"><strong>Empty Drop:</strong> {{ $trip->container_empty_drop_point }}</div>
                @endif
            </div>
            @endif
        </div>

        <div class="fleet-panel fleet-trip-detail-card">
            <div class="fleet-trip-finance-head">Financial Summary</div>
            <div class="fleet-trip-finance-row">
                <span>Subtotal</span>
                <strong>{{ format_kes($trip->subtotalAmount()) }}</strong>
            </div>
            @if ($trip->billingBreakdownLabel())
            <div class="fleet-trip-finance-row billing-breakdown">
                <span>{{ $trip->billing_type }}</span>
                <strong>{{ $trip->billingBreakdownLabel() }}</strong>
            </div>
            @endif
            <div class="fleet-trip-finance-row">
                <span>Tax</span>
                <strong>{{ format_kes($trip->taxAmount()) }}</strong>
            </div>
            <div class="fleet-trip-finance-row total">
                <span>Total</span>
                <strong>{{ format_kes($trip->totalAmount()) }}</strong>
            </div>
            <div class="fleet-trip-finance-row">
                <span>Paid</span>
                <strong class="fleet-trip-finance-paid">{{ format_kes($trip->paidAmount()) }}</strong>
            </div>
            <div class="fleet-trip-finance-row remaining">
                <span>Remaining</span>
                <strong class="fleet-trip-finance-remaining">{{ format_kes($trip->remainingAmount()) }}</strong>
            </div>
            @if ($trip->remainingAmount() > 0)
            <button
                type="button"
                class="fleet-trip-record-payment-link fleet-trip-record-payment-btn"
                data-trip-id="{{ $trip->id }}"
                data-trip-code="{{ $trip->displayTripCode() }}"
                data-customer="{{ $trip->customer_name }}"
                data-total-label="{{ format_kes($trip->totalAmount()) }}"
                data-paid-label="{{ format_kes($trip->paidAmount()) }}"
                data-remaining-label="{{ format_kes($trip->remainingAmount()) }}"
                data-remaining="{{ number_format($trip->remainingAmount(), 2, '.', '') }}"
                data-action="{{ route('trips.payments.store', $trip) }}"
            >
                <i class="fa fa-plus-circle"></i> Record deposit / payment
            </button>
            @endif
        </div>
    </div>
</div>

<div class="modal fade" id="fleet-live-map-modal" tabindex="-1" role="dialog" aria-labelledby="fleet-live-map-title">
    <div class="modal-dialog modal-lg fleet-live-map-dialog" role="document">
        <div class="modal-content fleet-live-map-content">
            <div class="modal-header fleet-live-map-header">
                <h4 class="modal-title" id="fleet-live-map-title">
                    Live Map - {{ $trip->displayTripCode() }}
                </h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body fleet-live-map-body">
                <div id="fleet-live-map-status" class="fleet-live-map-status">Loading route...</div>
                <div id="fleet-live-map" class="fleet-live-map-canvas"></div>
                <div class="fleet-live-map-legend">
                    <span><i class="fleet-live-dot start"></i> Pickup</span>
                    <span><i class="fleet-live-dot end"></i> Destination</span>
                    <span><i class="fleet-live-dot vehicle"></i> Vehicle (ongoing/completed)</span>
                </div>
            </div>
        </div>
    </div>
</div>

@include('fleet.trips.partials.record_payment_modal')
@endsection

@push('scripts')
@include('fleet.trips.partials.record_payment_modal_scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function () {
    var mapDataUrl = @json(route('trips.live-map-data', $trip));
    var mapInstance = null;
    var vehicleMarker = null;
    var routePoints = [];
    var mapLoaded = false;

    function setDistanceLabel(text) {
        var el = document.getElementById('trip-route-distance');
        if (el) {
            el.textContent = text;
        }
    }

    function setMapStatus(text, isError) {
        var el = document.getElementById('fleet-live-map-status');
        if (!el) return;
        el.textContent = text;
        el.className = 'fleet-live-map-status' + (isError ? ' is-error' : '');
    }

    function createIcon(color, label) {
        return L.divIcon({
            className: 'fleet-map-marker-wrap',
            html: '<span class="fleet-map-marker" style="background:' + color + ';">' + label + '</span>',
            iconSize: [28, 28],
            iconAnchor: [14, 14]
        });
    }

    function initMap(data) {
        var mapEl = document.getElementById('fleet-live-map');
        if (!mapEl) return;

        if (mapInstance) {
            mapInstance.remove();
            mapInstance = null;
        }

        mapInstance = L.map(mapEl, { scrollWheelZoom: true });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(mapInstance);

        L.marker([data.pickup.lat, data.pickup.lng], {
            icon: createIcon('#00a65a', 'A')
        }).addTo(mapInstance).bindPopup('<strong>Pickup</strong><br>' + data.pickup.label);

        L.marker([data.drop.lat, data.drop.lng], {
            icon: createIcon('#dd4b39', 'B')
        }).addTo(mapInstance).bindPopup('<strong>Destination</strong><br>' + data.drop.label);

        routePoints = (data.route && data.route.points && data.route.points.length)
            ? data.route.points
            : [
                { lat: data.pickup.lat, lng: data.pickup.lng },
                { lat: data.drop.lat, lng: data.drop.lng }
            ];

        var latLngs = routePoints.map(function (point) {
            return [point.lat, point.lng];
        });

        L.polyline(latLngs, {
            color: '#3c8dbc',
            weight: 5,
            opacity: 0.85
        }).addTo(mapInstance);

        if (data.vehicle) {
            vehicleMarker = L.marker([data.vehicle.lat, data.vehicle.lng], {
                icon: createIcon('#3c8dbc', 'V')
            }).addTo(mapInstance).bindPopup('<strong>' + data.vehicle_label + '</strong><br>Status: ' + data.status);
        }

        mapInstance.fitBounds(L.latLngBounds(latLngs), { padding: [30, 30] });

        if (data.route && data.route.distance_km) {
            setDistanceLabel(data.route.distance_km + ' km');
            setMapStatus('Route loaded • ' + data.route.distance_km + ' km • ~' + data.route.duration_minutes + ' min');
        } else {
            setDistanceLabel('N/A');
            setMapStatus('Route loaded (straight line estimate)');
        }

        mapLoaded = true;
        setTimeout(function () {
            if (mapInstance) {
                mapInstance.invalidateSize();
            }
        }, 250);
    }

    function loadMapData() {
        setMapStatus('Loading route...');
        setDistanceLabel('Loading...');

        fetch(mapDataUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.message || 'Unable to load map data.');
                    }
                    return data;
                });
            })
            .then(function (data) {
                initMap(data);
            })
            .catch(function (error) {
                setMapStatus(error.message || 'Unable to load map.', true);
                setDistanceLabel('N/A');
            });
    }

    document.getElementById('fleet-live-map-open').addEventListener('click', function () {
        $('#fleet-live-map-modal').modal('show');
        if (!mapLoaded) {
            loadMapData();
        } else if (mapInstance) {
            setTimeout(function () {
                mapInstance.invalidateSize();
            }, 250);
        }
    });

    $('#fleet-live-map-modal').on('shown.bs.modal', function () {
        if (mapInstance) {
            mapInstance.invalidateSize();
        }
    });

    loadMapData();

    document.querySelectorAll('.fleet-trip-action-btn:not(.fleet-trip-action-link)').forEach(function (btn) {
        btn.addEventListener('click', function () {
            alert((this.dataset.action || 'Feature') + ' coming soon.');
        });
    });
})();
</script>
@endpush
