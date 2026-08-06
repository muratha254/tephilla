<article class="fleet-geofence-manage-card {{ ! empty($active) ? 'is-active' : '' }}" data-geofence-id="{{ $geofence['id'] }}">
    <div class="fleet-geofence-card-top">
        <div>
            <h3 class="fleet-geofence-card-name">{{ $geofence['name'] }}</h3>
            <p class="fleet-geofence-card-desc">{{ $geofence['description'] ?: '-' }}</p>
        </div>
        <div class="fleet-geofence-card-actions">
            @if ($geofence['notify_sms'])
                <span class="fleet-geofence-notify-badge is-sms">Sms</span>
            @endif
            @if ($geofence['notify_email'])
                <span class="fleet-geofence-notify-badge is-email">Email</span>
            @endif
            <button type="button" class="fleet-geofence-card-action" data-focus-geofence="{{ $geofence['id'] }}" title="Show on map">
                <i class="fa fa-refresh"></i>
            </button>
            <form action="{{ route('geofences.destroy', $geofence['id']) }}" method="POST" class="fleet-delete-form fleet-geofence-card-action" onsubmit="return confirm('Delete this geofence?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="fleet-geofence-delete-btn" title="Delete"><i class="fa fa-trash"></i></button>
            </form>
        </div>
    </div>
    <div class="fleet-geofence-card-meta">
        <span>Created by: <strong>{{ $geofence['created_by'] }}</strong></span>
        <span>{{ $geofence['created_date'] }}</span>
    </div>
    <div class="fleet-geofence-card-vehicles">
        <i class="fa fa-truck"></i> {{ $geofence['vehicles_label'] }}
    </div>
</article>
