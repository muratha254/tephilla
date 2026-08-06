<script>
window.fleetGeofenceLayerFromData = function (geofence, options) {
    var geometry = geofence.geometry || {};
    var style = Object.assign({
        color: '#dd4b39',
        weight: 2,
        fillColor: '#dd4b39',
        fillOpacity: 0.25
    }, options || {});

    if (geofence.shape_type === 'marker') {
        return L.marker([geometry.lat, geometry.lng]);
    }

    if (geofence.shape_type === 'circle') {
        return L.circle([geometry.lat, geometry.lng], Object.assign({ radius: geometry.radius || 100 }, style));
    }

    if (geofence.shape_type === 'rectangle') {
        return L.rectangle([
            [geometry.south, geometry.west],
            [geometry.north, geometry.east]
        ], style);
    }

    if (geofence.shape_type === 'polygon' && geometry.points) {
        return L.polygon(geometry.points.map(function (point) {
            return [point.lat, point.lng];
        }), style);
    }

    return null;
};
</script>
