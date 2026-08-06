@extends('layouts.fleet')

@section('title', 'Smart Dispatch')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-vehicle-form.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-smart-dispatch.css') }}?v=1">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">Smart Dispatch</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('trips.index') }}">Trips</a></li>
        <li>Smart Dispatch</li>
    </ul>
</div>

<div class="fleet-dispatch-layout">
    <div class="fleet-panel fleet-dispatch-panel">
        <div class="fleet-dispatch-panel-head">
            <h3>Booking Details</h3>
            <span class="fleet-dispatch-count">{{ count($bookings) }} pending</span>
        </div>

        <div class="fleet-dispatch-bookings" id="dispatch-booking-list">
            @forelse ($bookings as $booking)
                <label class="fleet-dispatch-booking-item">
                    <input type="checkbox" class="dispatch-trip-checkbox" value="{{ $booking['id'] }}" data-code="{{ $booking['code'] }}">
                    <span class="fleet-dispatch-booking-body">
                        <span class="fleet-dispatch-booking-code">{{ $booking['code'] }}</span>
                        <span class="fleet-dispatch-booking-meta">
                            <i class="fa fa-clock-o"></i> {{ $booking['time'] }}
                            <i class="fa fa-map-marker"></i> {{ $booking['location'] }}
                        </span>
                    </span>
                </label>
            @empty
                <div class="fleet-dispatch-empty">No pending bookings available for dispatch.</div>
            @endforelse
        </div>

        <div class="fleet-dispatch-assign">
            <div class="fleet-field">
                <label>Assign To Driver</label>
                <select id="dispatch-driver" class="fleet-select fleet-input">
                    <option value="">Select Driver</option>
                    @foreach ($drivers as $driver)
                        <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="fleet-field">
                <label>Assign Vehicle</label>
                <select id="dispatch-vehicle" class="fleet-select fleet-input">
                    <option value="">Select Vehicle</option>
                    @foreach ($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}">{{ $vehicle->displayName() }} ({{ $vehicle->registration_number }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="fleet-dispatch-actions">
            <button type="button" class="fleet-btn fleet-btn-warning" id="dispatch-optimize-btn">
                <i class="fa fa-magic"></i> Optimize Route
            </button>
            <button type="button" class="fleet-btn fleet-btn-success" id="dispatch-confirm-btn">
                <i class="fa fa-check"></i> Confirm Dispatch
            </button>
        </div>

        <div class="fleet-dispatch-status" id="dispatch-status">Select bookings to plan route on the map.</div>
    </div>

    <div class="fleet-panel fleet-dispatch-map-panel">
        <div id="fleet-dispatch-map" class="fleet-dispatch-map"></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var mapMarkersUrl = @json(route('trips.smart-dispatch.map-markers'));
    var optimizeUrl = @json(route('trips.smart-dispatch.optimize'));
    var confirmUrl = @json(route('trips.smart-dispatch.confirm'));
    var map = null;
    var markerLayer = null;
    var routeLayer = null;
    var optimizedOrder = [];

    function selectedTripIds() {
        return Array.prototype.slice.call(document.querySelectorAll('.dispatch-trip-checkbox:checked'))
            .map(function (el) { return parseInt(el.value, 10); });
    }

    function setStatus(text, isError) {
        var el = document.getElementById('dispatch-status');
        el.textContent = text;
        el.className = 'fleet-dispatch-status' + (isError ? ' is-error' : '');
    }

    function markerIcon(label, color) {
        return L.divIcon({
            className: 'fleet-map-marker-wrap',
            html: '<span class="fleet-map-marker" style="background:' + color + ';">' + label + '</span>',
            iconSize: [28, 28],
            iconAnchor: [14, 14]
        });
    }

    function initMap() {
        map = L.map('fleet-dispatch-map', { scrollWheelZoom: true });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        map.setView([-1.286389, 36.817223], 11);
        markerLayer = L.layerGroup().addTo(map);
        routeLayer = L.layerGroup().addTo(map);
    }

    function renderMarkers(markers, routePoints) {
        markerLayer.clearLayers();
        routeLayer.clearLayers();

        if (!markers.length) {
            setStatus('No mappable bookings found for current selection.', true);
            return;
        }

        var bounds = [];

        markers.forEach(function (marker, index) {
            var latLng = [marker.lat, marker.lng];
            bounds.push(latLng);
            L.marker(latLng, {
                icon: markerIcon(index + 1, '#3c8dbc')
            }).addTo(markerLayer).bindPopup(
                '<strong>' + marker.code + '</strong><br>' + marker.location + '<br>' + marker.time
            );
        });

        if (routePoints && routePoints.length > 1) {
            var latLngs = routePoints.map(function (point) {
                return [point.lat, point.lng];
            });
            L.polyline(latLngs, { color: '#00a65a', weight: 5, opacity: 0.85 }).addTo(routeLayer);
            latLngs.forEach(function (latLng) { bounds.push(latLng); });
        }

        if (bounds.length) {
            map.fitBounds(bounds, { padding: [40, 40] });
        }
    }

    function loadMarkers(tripIds, routePoints) {
        setStatus('Loading map...');

        fetch(mapMarkersUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ trip_ids: tripIds })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.message || 'Unable to load map markers.');
                    }
                    return data;
                });
            })
            .then(function (data) {
                renderMarkers(data.markers || [], routePoints || null);
                if (!routePoints) {
                    setStatus(tripIds.length ? tripIds.length + ' booking(s) shown on map.' : 'Showing all pending bookings.');
                }
            })
            .catch(function (error) {
                setStatus(error.message || 'Unable to load map.', true);
            });
    }

    function syncSelection() {
        var ids = selectedTripIds();
        optimizedOrder = ids.slice();
        loadMarkers(ids.length ? ids : null);
    }

    document.querySelectorAll('.dispatch-trip-checkbox').forEach(function (checkbox) {
        checkbox.addEventListener('change', syncSelection);
    });

    document.getElementById('dispatch-optimize-btn').addEventListener('click', function () {
        var ids = selectedTripIds();

        if (ids.length < 1) {
            setStatus('Select at least one booking to optimize.', true);
            return;
        }

        setStatus('Optimizing route...');

        fetch(optimizeUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ trip_ids: ids })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.message || 'Optimization failed.');
                    }
                    return data;
                });
            })
            .then(function (data) {
                optimizedOrder = data.order || ids;
                renderMarkers(data.stops || [], (data.route && data.route.points) || []);
                setStatus('Route optimized • ' + (data.route.distance_km || 0) + ' km • ~' + (data.route.duration_minutes || 0) + ' min');
            })
            .catch(function (error) {
                setStatus(error.message || 'Optimization failed.', true);
            });
    });

    document.getElementById('dispatch-confirm-btn').addEventListener('click', function () {
        var ids = optimizedOrder.length ? optimizedOrder : selectedTripIds();
        var driverId = document.getElementById('dispatch-driver').value;
        var vehicleId = document.getElementById('dispatch-vehicle').value;

        if (!ids.length) {
            setStatus('Select at least one booking to dispatch.', true);
            return;
        }

        if (!driverId || !vehicleId) {
            setStatus('Select both driver and vehicle before dispatch.', true);
            return;
        }

        setStatus('Confirming dispatch...');

        fetch(confirmUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                trip_ids: ids,
                fleet_driver_id: driverId,
                fleet_vehicle_id: vehicleId
            })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.message || 'Dispatch failed.');
                    }
                    return data;
                });
            })
            .then(function (data) {
                setStatus(data.message || 'Dispatched successfully.');
                window.location.href = data.redirect || @json(route('trips.index'));
            })
            .catch(function (error) {
                setStatus(error.message || 'Dispatch failed.', true);
            });
    });

    initMap();
    loadMarkers(null);
})();
</script>
@endpush
