@extends('layouts.fleet')

@section('title', 'Live Fleet')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-live.css') }}?v=1">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

@section('content')
<div class="fleet-live-shell">
    <div class="fleet-live-topbar">
        <div class="fleet-live-topbar-left">
            <h1 class="fleet-live-title"><i class="fa fa-rss"></i> Live Fleet</h1>
            <div class="fleet-live-refresh">
                <select id="live-refresh-interval" class="fleet-live-refresh-select">
                    <option value="10" selected>10s</option>
                    <option value="30">30s</option>
                    <option value="60">60s</option>
                </select>
                <span class="fleet-live-refresh-label" id="live-refresh-label">just now</span>
            </div>
        </div>
        <div class="fleet-live-stats">
            <div class="fleet-live-stat is-total">
                <strong id="live-stat-total">{{ $stats['total'] }}</strong>
                <span>TOTAL</span>
            </div>
            <div class="fleet-live-stat is-moving">
                <strong id="live-stat-moving">{{ $stats['moving'] }}</strong>
                <span>MOVING</span>
            </div>
            <div class="fleet-live-stat is-idle">
                <strong id="live-stat-idle">{{ $stats['idle'] }}</strong>
                <span>IDLE</span>
            </div>
        </div>
    </div>

    <div class="fleet-live-layout">
        <div class="fleet-panel fleet-live-list-panel">
            <div class="fleet-live-search-wrap">
                <i class="fa fa-search"></i>
                <input type="text" id="live-vehicle-search" class="fleet-live-search" placeholder="Search vehicles...">
            </div>

            <div class="fleet-live-vehicle-list" id="live-vehicle-list">
                @foreach ($vehicles as $vehicle)
                    @include('fleet.tracking.partials.live_vehicle_card', ['vehicle' => $vehicle])
                @endforeach
            </div>
        </div>

        <div class="fleet-panel fleet-live-map-panel">
            <div class="fleet-live-map-filter">
                <select id="live-map-filter" class="fleet-live-map-select">
                    <option value="">All Vehicles</option>
                    @foreach ($vehicles as $vehicle)
                        <option value="{{ $vehicle['id'] }}">{{ $vehicle['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div id="fleet-live-map" class="fleet-live-map"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function () {
    var feedUrl = @json(route('tracking.live.feed'));
    var vehicles = @json($vehicles);
    var map = null;
    var markerLayer = null;
    var markers = {};
    var selectedVehicleId = null;
    var refreshTimer = null;
    var secondsSinceRefresh = 0;
    var refreshInterval = 10;

    function vehicleIcon(type) {
        var iconClass = 'fa-truck';
        if ((type || '').toLowerCase().indexOf('motor') !== -1) {
            iconClass = 'fa-motorcycle';
        } else if ((type || '').toLowerCase().indexOf('car') !== -1) {
            iconClass = 'fa-car';
        } else if ((type || '').toLowerCase().indexOf('bus') !== -1) {
            iconClass = 'fa-bus';
        }

        return L.divIcon({
            className: 'fleet-live-marker-wrap',
            html: '<span class="fleet-live-marker"><i class="fa ' + iconClass + '"></i></span>',
            iconSize: [34, 34],
            iconAnchor: [17, 17]
        });
    }

    function popupHtml(vehicle) {
        return '<div class="fleet-live-popup">' +
            '<div class="fleet-live-popup-head ' + vehicle.status_class + '">' +
                '<strong>' + escapeHtml(vehicle.name) + '</strong>' +
                '<span>' + escapeHtml(vehicle.status_label) + '</span>' +
            '</div>' +
            '<div class="fleet-live-popup-body">' +
                '<div><span>SPEED</span><strong>' + escapeHtml(vehicle.speed_label) + '</strong></div>' +
                '<div><span>DRIVER</span><strong>' + escapeHtml(vehicle.driver) + '</strong></div>' +
                '<div class="fleet-live-popup-trip"><span>To:</span><strong>' + escapeHtml(vehicle.trip_drop) + '</strong></div>' +
                '<div class="fleet-live-popup-time">' + escapeHtml(vehicle.last_seen) + '</div>' +
            '</div>' +
        '</div>';
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function cardHtml(vehicle) {
        var iconClass = 'fa-truck';
        if ((vehicle.type || '').toLowerCase().indexOf('motor') !== -1) {
            iconClass = 'fa-motorcycle';
        } else if ((vehicle.type || '').toLowerCase().indexOf('car') !== -1) {
            iconClass = 'fa-car';
        }

        var imageBlock = vehicle.image_url
            ? '<img src="' + escapeHtml(vehicle.image_url) + '" alt="">'
            : '<span class="fleet-live-card-icon"><i class="fa ' + iconClass + '"></i></span>';

        return '<article class="fleet-live-vehicle-card" data-vehicle-id="' + vehicle.id + '" data-search="' + escapeHtml((vehicle.name + ' ' + vehicle.registration + ' ' + vehicle.driver).toLowerCase()) + '">' +
            '<div class="fleet-live-card-thumb">' + imageBlock + '</div>' +
            '<div class="fleet-live-card-body">' +
                '<div class="fleet-live-card-head">' +
                    '<div><strong>' + escapeHtml(vehicle.name) + '</strong><span>' + escapeHtml(vehicle.registration) + '</span></div>' +
                    '<span class="fleet-live-status-badge ' + vehicle.status_class + '">' + escapeHtml(vehicle.status_label) + '</span>' +
                '</div>' +
                '<div class="fleet-live-card-meta">' +
                    '<span><i class="fa fa-tachometer"></i> ' + escapeHtml(vehicle.speed_label) + '</span>' +
                    '<span><i class="fa fa-clock-o"></i> ' + escapeHtml(vehicle.last_seen) + '</span>' +
                '</div>' +
                '<div class="fleet-live-card-trip">Trip: ' + escapeHtml(vehicle.trip_code) + ', ' + escapeHtml(vehicle.trip_route) + '</div>' +
                '<div class="fleet-live-card-driver"><i class="fa fa-user"></i> ' + escapeHtml(vehicle.driver) + '</div>' +
            '</div>' +
        '</article>';
    }

    function initMap() {
        map = L.map('fleet-live-map', { scrollWheelZoom: true });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        markerLayer = L.layerGroup().addTo(map);
        map.setView([13.0100, 80.2300], 11);
        renderMarkers(vehicles);
    }

    function renderMarkers(list) {
        markerLayer.clearLayers();
        markers = {};
        var bounds = [];

        list.forEach(function (vehicle) {
            if (vehicle.lat === null || vehicle.lng === null) {
                return;
            }

            var marker = L.marker([vehicle.lat, vehicle.lng], { icon: vehicleIcon(vehicle.type) })
                .bindPopup(popupHtml(vehicle))
                .addTo(markerLayer);

            marker.on('click', function () {
                selectVehicle(vehicle.id, false);
            });

            markers[vehicle.id] = marker;
            bounds.push([vehicle.lat, vehicle.lng]);
        });

        if (bounds.length === 1) {
            map.setView(bounds[0], 13);
        } else if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [50, 50] });
        }
    }

    function renderList(list) {
        var container = document.getElementById('live-vehicle-list');
        container.innerHTML = list.map(cardHtml).join('');
        bindCards();
        applySearchFilter();
    }

    function updateStats(stats) {
        document.getElementById('live-stat-total').textContent = stats.total;
        document.getElementById('live-stat-moving').textContent = stats.moving;
        document.getElementById('live-stat-idle').textContent = stats.idle;
    }

    function selectVehicle(vehicleId, openPopup) {
        selectedVehicleId = vehicleId;
        document.querySelectorAll('.fleet-live-vehicle-card').forEach(function (card) {
            card.classList.toggle('is-active', parseInt(card.dataset.vehicleId, 10) === vehicleId);
        });

        var marker = markers[vehicleId];
        if (marker) {
            map.setView(marker.getLatLng(), Math.max(map.getZoom(), 13));
            if (openPopup) {
                marker.openPopup();
            }
        }
    }

    function bindCards() {
        document.querySelectorAll('.fleet-live-vehicle-card').forEach(function (card) {
            card.addEventListener('click', function () {
                selectVehicle(parseInt(card.dataset.vehicleId, 10), true);
            });
        });
    }

    function applySearchFilter() {
        var query = (document.getElementById('live-vehicle-search').value || '').trim().toLowerCase();
        document.querySelectorAll('.fleet-live-vehicle-card').forEach(function (card) {
            var haystack = card.getAttribute('data-search') || '';
            card.style.display = !query || haystack.indexOf(query) !== -1 ? '' : 'none';
        });
    }

    function updateRefreshLabel() {
        var label = document.getElementById('live-refresh-label');
        if (secondsSinceRefresh <= 1) {
            label.textContent = 'just now';
            return;
        }
        label.textContent = secondsSinceRefresh + 's ago';
    }

    function fetchFeed() {
        var filter = document.getElementById('live-map-filter').value;
        var url = feedUrl + (filter ? ('?vehicle_id=' + encodeURIComponent(filter)) : '');

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                if (!payload.success) {
                    return;
                }

                vehicles = payload.all_vehicles || payload.vehicles || [];
                updateStats(payload.stats || {});
                renderList(vehicles);
                renderMarkers(filter ? (payload.vehicles || []) : vehicles);
                secondsSinceRefresh = 0;
                updateRefreshLabel();

                if (selectedVehicleId && markers[selectedVehicleId]) {
                    selectVehicle(selectedVehicleId, false);
                }
            });
    }

    function resetRefreshTimer() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
        }

        refreshTimer = setInterval(function () {
            secondsSinceRefresh += 1;
            updateRefreshLabel();

            if (secondsSinceRefresh >= refreshInterval) {
                fetchFeed();
            }
        }, 1000);
    }

    document.getElementById('live-vehicle-search').addEventListener('input', applySearchFilter);

    document.getElementById('live-map-filter').addEventListener('change', function () {
        var filter = this.value;
        renderMarkers(filter ? vehicles.filter(function (vehicle) {
            return String(vehicle.id) === String(filter);
        }) : vehicles);
        fetchFeed();
    });

    document.getElementById('live-refresh-interval').addEventListener('change', function () {
        refreshInterval = parseInt(this.value, 10) || 10;
        secondsSinceRefresh = 0;
        updateRefreshLabel();
    });

    initMap();
    bindCards();
    resetRefreshTimer();
})();
</script>
@endpush
