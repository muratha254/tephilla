<article class="fleet-live-vehicle-card" data-vehicle-id="{{ $vehicle['id'] }}" data-search="{{ strtolower($vehicle['name'] . ' ' . $vehicle['registration'] . ' ' . $vehicle['driver']) }}">
    <div class="fleet-live-card-thumb">
        @if ($vehicle['image_url'])
            <img src="{{ $vehicle['image_url'] }}" alt="">
        @else
            @php
                $icon = 'fa-truck';
                $type = strtolower($vehicle['type'] ?? '');
                if (str_contains($type, 'motor')) {
                    $icon = 'fa-motorcycle';
                } elseif (str_contains($type, 'car')) {
                    $icon = 'fa-car';
                } elseif (str_contains($type, 'bus')) {
                    $icon = 'fa-bus';
                }
            @endphp
            <span class="fleet-live-card-icon"><i class="fa {{ $icon }}"></i></span>
        @endif
    </div>
    <div class="fleet-live-card-body">
        <div class="fleet-live-card-head">
            <div>
                <strong>{{ $vehicle['name'] }}</strong>
                <span>{{ $vehicle['registration'] }}</span>
            </div>
            <span class="fleet-live-status-badge {{ $vehicle['status_class'] }}">{{ $vehicle['status_label'] }}</span>
        </div>
        <div class="fleet-live-card-meta">
            <span><i class="fa fa-tachometer"></i> {{ $vehicle['speed_label'] }}</span>
            <span><i class="fa fa-clock-o"></i> {{ $vehicle['last_seen'] }}</span>
        </div>
        <div class="fleet-live-card-trip">Trip: {{ $vehicle['trip_code'] }}, {{ $vehicle['trip_route'] }}</div>
        <div class="fleet-live-card-driver"><i class="fa fa-user"></i> {{ $vehicle['driver'] }}</div>
    </div>
</article>
