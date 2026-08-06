@extends('layouts.fleet')

@section('title', 'Trip Playback')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-playback.css') }}?v=1">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

@section('content')
<div class="fleet-playback-layout">
    <div class="fleet-panel fleet-playback-panel">
        <div class="fleet-playback-panel-head">
            <h1 class="fleet-playback-title"><i class="fa fa-play-circle"></i> Trip Playback</h1>
        </div>

        <form method="GET" action="{{ route('tracking.playback') }}" class="fleet-playback-filters" id="playback-filter-form">
            <div class="fleet-playback-field">
                <label for="playback-vehicle">Select Vehicle</label>
                <select id="playback-vehicle" name="vehicle_id" class="fleet-playback-input">
                    @foreach ($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" {{ (int) $selectedVehicleId === (int) $vehicle->id ? 'selected' : '' }}>
                            {{ $vehicle->displayName() }} - {{ $vehicle->registration_number }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="fleet-playback-date-row">
                <div class="fleet-playback-field">
                    <label for="playback-date-from">From</label>
                    <input type="date" id="playback-date-from" name="date_from" class="fleet-playback-input" value="{{ $dateFrom }}">
                </div>
                <div class="fleet-playback-field">
                    <label for="playback-date-to">To</label>
                    <input type="date" id="playback-date-to" name="date_to" class="fleet-playback-input" value="{{ $dateTo }}">
                </div>
            </div>

            <button type="button" class="fleet-btn fleet-btn-primary fleet-playback-start-btn" id="playback-start-btn">
                <i class="fa fa-play"></i> Start Playback
            </button>
        </form>

        <div class="fleet-playback-list-head">Trip List</div>
        <div class="fleet-playback-trip-list" id="playback-trip-list">
            @forelse ($trips as $trip)
                @include('fleet.tracking.partials.trip_card', ['trip' => $trip, 'active' => false])
            @empty
                <div class="fleet-playback-empty">No trips found for the selected vehicle and date range.</div>
            @endforelse
        </div>
    </div>

    <div class="fleet-panel fleet-playback-map-panel">
        <div id="fleet-playback-map" class="fleet-playback-map"></div>
        <div class="fleet-playback-map-status" id="playback-map-status">Select a trip or click Start Playback.</div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function () {
    var tripsUrl = @json(route('tracking.playback.trips'));
    var initialTrips = @json($trips);
    var map = null;
    var routeLayer = null;
    var markerLayer = null;
    var vehicleMarker = null;
    var playbackTimer = null;
    var currentTripId = null;
    var currentPoints = [];

    function setStatus(text) {
        var el = document.getElementById('playback-map-status');
        if (el) {
            el.textContent = text;
        }
    }

    function markerIcon(color, label) {
        return L.divIcon({
            className: 'fleet-playback-marker-wrap',
            html: '<span class="fleet-playback-marker" style="background:' + color + ';">' + (label || '') + '</span>',
            iconSize: [label ? 28 : 16, label ? 28 : 16],
            iconAnchor: [label ? 14 : 8, label ? 14 : 8]
        });
    }

    function vehicleIcon() {
        return L.divIcon({
            className: 'fleet-playback-vehicle-wrap',
            html: '<span class="fleet-playback-vehicle"><i class="fa fa-truck"></i></span>',
            iconSize: [28, 28],
            iconAnchor: [14, 14]
        });
    }

    function initMap() {
        map = L.map('fleet-playback-map', { scrollWheelZoom: true });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        routeLayer = L.layerGroup().addTo(map);
        markerLayer = L.layerGroup().addTo(map);
        map.setView([13.0037, 80.2514], 12);
    }

    function clearPlayback() {
        if (playbackTimer) {
            clearInterval(playbackTimer);
            playbackTimer = null;
        }
        routeLayer.clearLayers();
        markerLayer.clearLayers();
        vehicleMarker = null;
    }

    function setActiveTripCard(tripId) {
        document.querySelectorAll('.fleet-playback-trip-card').forEach(function (card) {
            card.classList.toggle('is-active', parseInt(card.dataset.tripId, 10) === tripId);
        });
    }

    function renderTripList(trips) {
        var list = document.getElementById('playback-trip-list');
        if (!list) return;

        if (!trips.length) {
            list.innerHTML = '<div class="fleet-playback-empty">No trips found for the selected vehicle and date range.</div>';
            return;
        }

        list.innerHTML = trips.map(function (trip) {
            var activeClass = currentTripId === trip.id ? ' is-active' : '';
            return '<article class="fleet-playback-trip-card' + activeClass + '" data-trip-id="' + trip.id + '" data-route-url="' + trip.route_url + '">' +
                '<div class="fleet-playback-trip-top">' +
                    '<span class="fleet-playback-trip-badge">Trip #' + trip.number + '</span>' +
                    '<button type="button" class="fleet-playback-trip-play" data-play-trip="' + trip.id + '"><i class="fa fa-play"></i> CLICK TO PLAY</button>' +
                '</div>' +
                '<div class="fleet-playback-trip-route">' +
                    '<div class="fleet-playback-route-point start"><span></span><strong>' + escapeHtml(trip.pickup) + '</strong></div>' +
                    '<div class="fleet-playback-route-point end"><span></span><strong>' + escapeHtml(trip.drop) + '</strong></div>' +
                '</div>' +
                '<div class="fleet-playback-trip-meta">' +
                    '<span><strong>DIST:</strong> ' + escapeHtml(trip.distance_label) + '</span>' +
                    '<span><strong>TIME:</strong> ' + escapeHtml(trip.duration_label) + '</span>' +
                '</div>' +
            '</article>';
        }).join('');

        bindTripCards();
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function drawRoute(route, trip) {
        clearPlayback();
        currentPoints = route.points || [];

        if (!currentPoints.length) {
            setStatus('No route points available for this trip.');
            return;
        }

        var latLngs = currentPoints.map(function (point) {
            return [point.lat, point.lng];
        });

        L.polyline(latLngs, {
            color: '#3c8dbc',
            weight: 5,
            opacity: 0.9
        }).addTo(routeLayer);

        L.marker([route.start.lat, route.start.lng], { icon: markerIcon('#00a65a') }).addTo(markerLayer);
        L.marker([route.end.lat, route.end.lng], { icon: markerIcon('#dd4b39') }).addTo(markerLayer);

        vehicleMarker = L.marker([route.start.lat, route.start.lng], { icon: vehicleIcon() }).addTo(markerLayer);
        map.fitBounds(L.latLngBounds(latLngs), { padding: [40, 40] });

        setStatus('Playing Trip #' + trip.number + ': ' + trip.pickup + ' to ' + trip.drop);
    }

    function animateRoute() {
        if (!vehicleMarker || currentPoints.length < 2) {
            return;
        }

        var index = 0;
        if (playbackTimer) {
            clearInterval(playbackTimer);
        }

        playbackTimer = setInterval(function () {
            index += 1;
            if (index >= currentPoints.length) {
                clearInterval(playbackTimer);
                playbackTimer = null;
                return;
            }

            var point = currentPoints[index];
            vehicleMarker.setLatLng([point.lat, point.lng]);
        }, 180);
    }

    function loadTripRoute(tripCard, autoplay) {
        var url = tripCard.getAttribute('data-route-url');
        var tripId = parseInt(tripCard.dataset.tripId, 10);
        currentTripId = tripId;
        setActiveTripCard(tripId);
        setStatus('Loading route...');

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                if (!payload.success) {
                    setStatus(payload.message || 'Unable to load route.');
                    return;
                }

                drawRoute(payload.route, payload.trip);

                if (autoplay) {
                    animateRoute();
                }
            })
            .catch(function () {
                setStatus('Unable to load route.');
            });
    }

    function bindTripCards() {
        document.querySelectorAll('.fleet-playback-trip-card').forEach(function (card) {
            card.addEventListener('click', function (event) {
                if (event.target.closest('[data-play-trip]')) {
                    loadTripRoute(card, true);
                    return;
                }
                loadTripRoute(card, false);
            });
        });

        document.querySelectorAll('[data-play-trip]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.stopPropagation();
                var card = button.closest('.fleet-playback-trip-card');
                if (card) {
                    loadTripRoute(card, true);
                }
            });
        });
    }

    function refreshTrips() {
        var vehicleId = document.getElementById('playback-vehicle').value;
        var dateFrom = document.getElementById('playback-date-from').value;
        var dateTo = document.getElementById('playback-date-to').value;
        var url = tripsUrl + '?vehicle_id=' + encodeURIComponent(vehicleId) +
            '&date_from=' + encodeURIComponent(dateFrom) +
            '&date_to=' + encodeURIComponent(dateTo);

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                if (payload.success) {
                    renderTripList(payload.trips || []);
                }
            });
    }

    document.getElementById('playback-start-btn').addEventListener('click', function () {
        var firstCard = document.querySelector('.fleet-playback-trip-card');
        if (!firstCard) {
            setStatus('No trips available to play.');
            return;
        }
        loadTripRoute(firstCard, true);
    });

    ['playback-vehicle', 'playback-date-from', 'playback-date-to'].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('change', function () {
            refreshTrips();
            clearPlayback();
            currentTripId = null;
            setStatus('Select a trip or click Start Playback.');
        });
    });

    initMap();
    bindTripCards();

    if (initialTrips.length) {
        var firstCard = document.querySelector('.fleet-playback-trip-card');
        if (firstCard) {
            loadTripRoute(firstCard, false);
        }
    }
})();
</script>
@endpush
