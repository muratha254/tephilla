@extends('layouts.fleet')

@section('title', 'Geofence')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-geofence.css') }}?v=2">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css">
@endpush

@section('content')
<div class="fleet-geofence-shell">
    <div class="fleet-geofence-toolbar">
        <form method="GET" action="{{ route('geofences.create') }}" class="fleet-geofence-search-form" id="geofence-search-form">
            <input
                type="text"
                id="geofence-location-search"
                name="location"
                class="fleet-geofence-search"
                value="{{ request('location', $defaultLocation) }}"
                placeholder="Search location..."
                autocomplete="off"
            >
        </form>
        <button type="button" class="fleet-btn fleet-btn-primary fleet-geofence-save-btn" id="geofence-save-btn">
            Save Geofence
        </button>
    </div>

    <div class="fleet-panel fleet-geofence-map-panel">
        <div id="fleet-geofence-map" class="fleet-geofence-map"></div>
    </div>
</div>

<form method="POST" action="{{ route('geofences.store') }}" id="geofence-save-form" hidden>
    @csrf
    <input type="hidden" name="location_label" id="geofence-location-label">
    <input type="hidden" name="shape_type" id="geofence-shape-type">
    <input type="hidden" name="center_lat" id="geofence-center-lat">
    <input type="hidden" name="center_lng" id="geofence-center-lng">
    <input type="hidden" name="geometry" id="geofence-geometry">
</form>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
@include('fleet.geofences.partials.map_helpers')
@include('fleet.geofences.partials.draw_scripts')
@endpush
