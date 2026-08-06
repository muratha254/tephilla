@extends('layouts.fleet')

@section('title', 'Vehicle Availability')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-availability.css') }}?v=4">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title"><i class="fa fa-calendar"></i> Vehicle Availability</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li>Vehicle Calendar</li>
    </ul>
</div>

<div class="fleet-panel fleet-calendar-card">
    <div class="fleet-calendar-toolbar">
        <div class="fleet-calendar-legend">
            <span class="fleet-legend-btn fleet-legend-available"><i class="fa fa-check"></i> All Available</span>
            <span class="fleet-legend-btn fleet-legend-booked"><i class="fa fa-ban"></i> Unavailable</span>
            <button type="button" class="fleet-legend-minus" aria-label="Collapse legend">−</button>
        </div>

        <div class="fleet-calendar-nav">
            <div class="fleet-calendar-nav-left">
                <a href="{{ route('availability', ['month' => $prevMonth]) }}" class="fleet-cal-btn" aria-label="Previous month"><i class="fa fa-chevron-left"></i></a>
                <a href="{{ route('availability', ['month' => $nextMonth]) }}" class="fleet-cal-btn" aria-label="Next month"><i class="fa fa-chevron-right"></i></a>
                <a href="{{ route('availability') }}" class="fleet-cal-today">Today</a>
            </div>
            <div class="fleet-calendar-month">{{ $monthLabel }}</div>
            <div class="fleet-calendar-views">
                <button type="button" class="fleet-view-btn active">Month</button>
                <button type="button" class="fleet-view-btn" disabled>Week</button>
                <button type="button" class="fleet-view-btn" disabled>List</button>
            </div>
        </div>
    </div>

    <div class="fleet-calendar-grid">
        <div class="fleet-calendar-weekdays">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                <div class="fleet-calendar-weekday">{{ $weekday }}</div>
            @endforeach
        </div>
        <div class="fleet-calendar-days">
            @foreach ($days as $day)
                @php
                    $total = $day['availability']['total'] ?? 0;
                    $available = $day['availability']['available_count'] ?? 0;
                    $unavailable = $day['unavailable_vehicles'] ?? [];
                    $visibleUnavailable = array_slice($unavailable, 0, 2);
                    $hiddenUnavailable = max(0, count($unavailable) - count($visibleUnavailable));
                @endphp
                <div class="fleet-calendar-day {{ !$day['is_current_month'] ? 'is-outside' : '' }} {{ $day['is_today'] ? 'is-today' : '' }} {{ $day['status'] === 'fully_booked' ? 'is-fully-booked' : '' }} {{ $day['status'] === 'all_available' ? 'is-all-available' : '' }}">
                    <div class="fleet-calendar-day-num">{{ $day['date']->day }}</div>

                    @if ($day['is_current_month'] && $total > 0 && $day['status'] !== 'all_available')
                        <div class="fleet-calendar-day-status is-busy">
                            <i class="fa fa-ban"></i>
                            @if ($day['status'] === 'fully_booked')
                                All vehicles booked
                            @else
                                {{ count($unavailable) }} unavailable
                            @endif
                        </div>

                        <div class="fleet-calendar-unavailable-list">
                            @foreach ($visibleUnavailable as $vehicle)
                                <span class="fleet-calendar-unavailable-tag" title="{{ $vehicle['name'] }}">
                                    {{ $vehicle['registration'] ?: $vehicle['name'] }}
                                </span>
                            @endforeach
                            @if ($hiddenUnavailable > 0)
                                <span class="fleet-calendar-unavailable-more">+{{ $hiddenUnavailable }} more</span>
                            @endif
                        </div>

                        <div class="fleet-calendar-day-foot">
                            {{ $available }} / {{ $total }} free
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
