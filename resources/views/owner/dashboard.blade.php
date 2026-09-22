@extends('layouts.fleet')

@section('title', 'System Owner')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'System Owner Dashboard',
    'subtitle' => 'Subscription control for every business on this server',
    'backUrl' => route('owner.dashboard'),
    'breadcrumbs' => [
        ['label' => 'Owner', 'url' => route('owner.dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Dashboard'],
    ],
])

<div class="row">
    @foreach([
        ['key' => 'total', 'label' => 'Total Businesses', 'color' => 'sx-aqua', 'icon' => 'fa-building', 'status' => ''],
        ['key' => 'pending', 'label' => 'Pending Approval', 'color' => '', 'icon' => 'fa-hourglass-start', 'status' => 'pending_approval'],
        ['key' => 'active', 'label' => 'Active', 'color' => 'sx-green', 'icon' => 'fa-check', 'status' => 'active'],
        ['key' => 'expiring', 'label' => 'Expiring Soon', 'color' => '', 'icon' => 'fa-clock-o', 'status' => 'expiring_soon'],
        ['key' => 'expired', 'label' => 'Expired', 'color' => '', 'icon' => 'fa-times-circle', 'status' => 'expired'],
        ['key' => 'suspended', 'label' => 'Suspended', 'color' => '', 'icon' => 'fa-ban', 'status' => 'suspended'],
        ['key' => 'trial', 'label' => 'Trial', 'color' => '', 'icon' => 'fa-hourglass-half', 'status' => 'trial'],
        ['key' => 'users', 'label' => 'Total Users', 'color' => 'sx-aqua', 'icon' => 'fa-users', 'status' => ''],
    ] as $card)
        <div class="col-md-3 col-sm-6">
            <a href="{{ $card['status'] !== '' ? route('owner.businesses.index', ['status' => $card['status']]) : route('owner.businesses.index') }}" class="sx-info-box-link">
                <div class="sx-info-box {{ $card['color'] }} is-clickable">
                    <div class="sx-info-icon"><i class="fa {{ $card['icon'] }}"></i></div>
                    <div class="sx-info-body">
                        <span class="sx-info-label">{{ $card['label'] }}</span>
                        <span class="sx-info-value">{{ number_format($cards[$card['key']] ?? 0) }}</span>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<div class="row">
    <div class="col-md-12">
        <div class="sx-box">
            <div class="sx-box-body">
                <h4>Pending subscription requests</h4>
                <div class="table-responsive">
                    <table class="table table-bordered sx-gold-table">
                        <thead><tr><th>Business</th><th>Owner</th><th>Plan</th><th>Requested</th><th></th></tr></thead>
                        <tbody>
                            @forelse($pending as $row)
                                <tr>
                                    <td><a href="{{ route('owner.businesses.show', $row->company) }}">{{ optional($row->company)->name }}</a></td>
                                    <td>{{ optional($row->company)->owner_name ?: '-' }}</td>
                                    <td>{{ optional($row->plan)->name ?: '-' }}</td>
                                    <td>{{ optional($row->created_at)->format('d M Y H:i') }}</td>
                                    <td><a href="{{ route('owner.businesses.show', $row->company) }}" class="btn btn-xs btn-primary">Review</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5">No pending requests.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="sx-box">
            <div class="sx-box-body">
                <h4>Recent registrations</h4>
                <div class="table-responsive">
                    <table class="table table-bordered sx-gold-table">
                        <thead><tr><th>Business</th><th>Plan</th><th>Status</th><th>Expiry</th></tr></thead>
                        <tbody>
                            @forelse($recent as $row)
                                @php $st = optional($row->subscription)->effectiveStatus() ?: 'expired'; @endphp
                                <tr>
                                    <td><a href="{{ route('owner.businesses.show', $row) }}">{{ $row->name }}</a></td>
                                    <td>{{ optional(optional($row->subscription)->plan)->name ?: '-' }}</td>
                                    <td><span class="sx-sub-badge {{ subscription_status_class($st) }}">{{ optional($row->subscription)->statusLabel() ?: 'No plan' }}</span></td>
                                    <td>{{ optional(optional($row->subscription)->expires_at)->format('d M Y') ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4">No businesses yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="sx-box">
            <div class="sx-box-body">
                <h4>Expiring soon</h4>
                <div class="table-responsive">
                    <table class="table table-bordered sx-gold-table">
                        <thead><tr><th>Business</th><th>Days</th><th>Expiry</th></tr></thead>
                        <tbody>
                            @forelse($expiring as $row)
                                <tr>
                                    <td><a href="{{ route('owner.businesses.show', $row->company) }}">{{ optional($row->company)->name }}</a></td>
                                    <td>{{ $row->daysRemaining() }}</td>
                                    <td>{{ optional($row->expires_at)->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3">None in the warning window.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="sx-box">
            <div class="sx-box-body">
                <h4>Recently expired</h4>
                <div class="table-responsive">
                    <table class="table table-bordered sx-gold-table">
                        <thead><tr><th>Business</th><th>Expiry</th></tr></thead>
                        <tbody>
                            @forelse($expired as $row)
                                <tr>
                                    <td><a href="{{ route('owner.businesses.show', $row->company) }}">{{ optional($row->company)->name }}</a></td>
                                    <td>{{ optional($row->expires_at)->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2">No expired subscriptions.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="sx-box">
            <div class="sx-box-body">
                <h4>Recent activity</h4>
                <div class="table-responsive">
                    <table class="table table-bordered sx-gold-table">
                        <thead><tr><th>When</th><th>Business</th><th>Action</th><th>By</th></tr></thead>
                        <tbody>
                            @forelse($activity as $row)
                                <tr>
                                    <td>{{ $row->created_at->format('d M Y H:i') }}</td>
                                    <td>{{ optional($row->company)->name }}</td>
                                    <td>{{ str_replace('_', ' ', $row->action) }}</td>
                                    <td>{{ optional($row->actor)->name ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4">No activity yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
