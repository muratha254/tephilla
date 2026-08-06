<script>
(function () {
    var geocodeUrl = @json(route('geofences.geocode'));
    var savedGeofences = @json($geofences);
    var defaultCenter = @json($defaultCenter);
    var map = null;
    var drawnItems = new L.FeatureGroup();
    var savedLayerGroup = new L.FeatureGroup();
    var activeDraw = null;

    function initMap() {
        map = L.map('fleet-geofence-map', {
            center: [defaultCenter.lat, defaultCenter.lng],
            zoom: 12,
            scrollWheelZoom: true
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        map.addLayer(drawnItems);
        map.addLayer(savedLayerGroup);

        var drawControl = new L.Control.Draw({
            position: 'topcenter',
            draw: {
                polyline: false,
                polygon: {
                    allowIntersection: false,
                    showArea: true
                },
                rectangle: true,
                circle: true,
                marker: true,
                circlemarker: false
            },
            edit: {
                featureGroup: drawnItems,
                remove: true
            }
        });

        map.addControl(drawControl);

        map.on(L.Draw.Event.CREATED, function (event) {
            drawnItems.clearLayers();
            drawnItems.addLayer(event.layer);
            activeDraw = event.layer;
        });

        map.on(L.Draw.Event.DELETED, function () {
            activeDraw = drawnItems.getLayers()[0] || null;
        });

        map.on(L.Draw.Event.EDITED, function () {
            activeDraw = drawnItems.getLayers()[0] || null;
        });

        renderSavedGeofences();
    }

    function renderSavedGeofences() {
        savedLayerGroup.clearLayers();

        savedGeofences.forEach(function (geofence) {
            var layer = window.fleetGeofenceLayerFromData(geofence);
            if (!layer) {
                return;
            }

            layer.bindPopup('<strong>' + escapeHtml(geofence.name || geofence.location_label) + '</strong><br>' + escapeHtml(geofence.shape_type));
            savedLayerGroup.addLayer(layer);
        });
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function serializeLayer(layer) {
        if (layer instanceof L.Marker) {
            var latLng = layer.getLatLng();
            return {
                type: 'marker',
                geometry: { lat: latLng.lat, lng: latLng.lng },
                center: latLng
            };
        }

        if (layer instanceof L.Circle) {
            var center = layer.getLatLng();
            return {
                type: 'circle',
                geometry: { lat: center.lat, lng: center.lng, radius: layer.getRadius() },
                center: center
            };
        }

        if (layer instanceof L.Rectangle) {
            var bounds = layer.getBounds();
            return {
                type: 'rectangle',
                geometry: {
                    south: bounds.getSouth(),
                    west: bounds.getWest(),
                    north: bounds.getNorth(),
                    east: bounds.getEast()
                },
                center: bounds.getCenter()
            };
        }

        if (layer instanceof L.Polygon) {
            var points = layer.getLatLngs()[0].map(function (latLng) {
                return { lat: latLng.lat, lng: latLng.lng };
            });

            return {
                type: 'polygon',
                geometry: { points: points },
                center: layer.getBounds().getCenter()
            };
        }

        return null;
    }

    function searchLocation() {
        var query = document.getElementById('geofence-location-search').value.trim();
        if (!query) {
            return;
        }

        fetch(geocodeUrl + '?query=' + encodeURIComponent(query), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                if (!payload.success) {
                    alert(payload.message || 'Location not found.');
                    return;
                }

                map.setView([payload.location.lat, payload.location.lng], 13);
            })
            .catch(function () {
                alert('Unable to search location.');
            });
    }

    document.getElementById('geofence-search-form').addEventListener('submit', function (event) {
        event.preventDefault();
        searchLocation();
    });

    document.getElementById('geofence-save-btn').addEventListener('click', function () {
        var layer = activeDraw || drawnItems.getLayers()[0];

        if (!layer) {
            alert('Draw a geofence on the map first.');
            return;
        }

        var serialized = serializeLayer(layer);
        if (!serialized) {
            alert('Unsupported geofence shape.');
            return;
        }

        document.getElementById('geofence-location-label').value = document.getElementById('geofence-location-search').value.trim() || 'Geofence';
        document.getElementById('geofence-shape-type').value = serialized.type;
        document.getElementById('geofence-center-lat').value = serialized.center.lat;
        document.getElementById('geofence-center-lng').value = serialized.center.lng;
        document.getElementById('geofence-geometry').value = JSON.stringify(serialized.geometry);
        document.getElementById('geofence-save-form').submit();
    });

    initMap();
})();
</script>
