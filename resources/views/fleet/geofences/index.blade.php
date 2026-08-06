@extends('layouts.fleet')

@section('title', 'Geofence Management')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-geofence.css') }}?v=2">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

@section('content')
<div class="fleet-geofence-manage-shell">
    <div class="fleet-geofence-manage-head">
        <h1 class="fleet-geofence-manage-title">Geofence Management</h1>
        <a href="{{ route('geofences.create') }}" class="fleet-geofence-new-btn"><i class="fa fa-plus"></i> Action New</a>
    </div>

    <div class="fleet-geofence-manage-layout">
        <div class="fleet-panel fleet-geofence-list-panel">
            <form method="GET" action="{{ route('geofences.index') }}" class="fleet-geofence-list-search-wrap">
                <i class="fa fa-search"></i>
                <input
                    type="text"
                    name="search"
                    class="fleet-geofence-list-search"
                    value="{{ $search }}"
                    placeholder="Placeholder Search Geofence"
                >
            </form>

            <div class="fleet-geofence-list" id="geofence-list">
                @forelse ($geofences as $geofence)
                    @include('fleet.geofences.partials.manage_card', ['geofence' => $geofence])
                @empty
                    <div class="fleet-geofence-list-empty">No geofences found.</div>
                @endforelse
            </div>
        </div>

        <div class="fleet-panel fleet-geofence-manage-map-panel">
            <div id="fleet-geofence-manage-map" class="fleet-geofence-manage-map"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
@include('fleet.geofences.partials.map_helpers')
<script>
(function () {
    var geofences = @json($geofences);
    var defaultCenter = @json($defaultCenter);
    var map = null;
    var layerGroup = null;
    var layersById = {};
    var selectedId = geofences.length ? geofences[0].id : null;

    function initMap() {
        map = L.map('fleet-geofence-manage-map', { scrollWheelZoom: true });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        layerGroup = L.layerGroup().addTo(map);
        renderGeofences();

        if (selectedId) {
            focusGeofence(selectedId, true);
        } else {
            map.setView([defaultCenter.lat, defaultCenter.lng], 12);
        }
    }

    function renderGeofences() {
        layerGroup.clearLayers();
        layersById = {};
        var bounds = [];

        geofences.forEach(function (geofence) {
            var layer = window.fleetGeofenceLayerFromData(geofence);
            if (!layer) {
                return;
            }

            layer.bindPopup('<strong>' + escapeHtml(geofence.name) + '</strong><br>' + escapeHtml(geofence.description || ''));
            layer.on('click', function () {
                focusGeofence(geofence.id, false);
            });
            layerGroup.addLayer(layer);
            layersById[geofence.id] = layer;

            if (layer.getBounds) {
                bounds.push(layer.getBounds());
            } else if (layer.getLatLng) {
                bounds.push(layer.getLatLng());
            }
        });

        if (!selectedId && bounds.length) {
            if (bounds[0] instanceof L.LatLngBounds) {
                map.fitBounds(bounds[0], { padding: [40, 40] });
            } else {
                map.fitBounds(L.latLngBounds(bounds), { padding: [40, 40] });
            }
        }
    }

    function focusGeofence(id, fit) {
        selectedId = id;
        document.querySelectorAll('.fleet-geofence-manage-card').forEach(function (card) {
            card.classList.toggle('is-active', parseInt(card.dataset.geofenceId, 10) === id);
        });

        var layer = layersById[id];
        if (!layer) {
            return;
        }

        if (fit) {
            if (layer.getBounds) {
                map.fitBounds(layer.getBounds(), { padding: [50, 50] });
            } else if (layer.getLatLng) {
                map.setView(layer.getLatLng(), 14);
            }
        }
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    document.querySelectorAll('.fleet-geofence-manage-card').forEach(function (card) {
        card.addEventListener('click', function (event) {
            if (event.target.closest('.fleet-geofence-card-action')) {
                return;
            }
            focusGeofence(parseInt(card.dataset.geofenceId, 10), true);
        });
    });

    document.querySelectorAll('[data-focus-geofence]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            focusGeofence(parseInt(button.dataset.focusGeofence, 10), true);
        });
    });

    initMap();
})();
</script>
@endpush
