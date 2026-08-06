<article class="fleet-playback-trip-card {{ ! empty($active) ? 'is-active' : '' }}" data-trip-id="{{ $trip['id'] }}" data-route-url="{{ $trip['route_url'] }}">
    <div class="fleet-playback-trip-top">
        <span class="fleet-playback-trip-badge">Trip #{{ $trip['number'] }}</span>
        <button type="button" class="fleet-playback-trip-play" data-play-trip="{{ $trip['id'] }}">
            <i class="fa fa-play"></i> CLICK TO PLAY
        </button>
    </div>
    <div class="fleet-playback-trip-route">
        <div class="fleet-playback-route-point start">
            <span></span>
            <strong>{{ $trip['pickup'] }}</strong>
        </div>
        <div class="fleet-playback-route-point end">
            <span></span>
            <strong>{{ $trip['drop'] }}</strong>
        </div>
    </div>
    <div class="fleet-playback-trip-meta">
        <span><strong>DIST:</strong> {{ $trip['distance_label'] }}</span>
        <span><strong>TIME:</strong> {{ $trip['duration_label'] }}</span>
    </div>
</article>
