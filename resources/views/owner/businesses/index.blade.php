@extends('layouts.fleet')

@section('title', 'Businesses')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Business subscriptions',
    'subtitle' => 'Search, filter and manage every tenant',
    'backUrl' => route('owner.dashboard'),
    'breadcrumbs' => [
        ['label' => 'Owner', 'url' => route('owner.dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Businesses'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <form method="get" class="form-inline" style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;">
            <div class="form-group">
                <label>Search</label>
                <input type="text" name="q" class="form-control" value="{{ $filters['q'] }}" placeholder="Name, email, phone">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="">All</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @if($filters['status'] === $value) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Plan</label>
                <select name="plan_id" class="form-control">
                    <option value="">All</option>
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}" @if((string) $filters['plan_id'] === (string) $plan->id) selected @endif>{{ $plan->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn sx-btn-aqua"><i class="fa fa-search"></i> Filter</button>
        </form>
        <a href="{{ route('owner.businesses.create') }}" class="btn sx-btn-aqua"><i class="fa fa-plus"></i> New business</a>
    </div>
    <div class="sx-box-body">
        <div class="table-responsive">
            <table class="table table-bordered sx-gold-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Business</th>
                        <th>Owner</th>
                        <th>Email</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Start</th>
                        <th>Expiry</th>
                        <th>Days</th>
                        <th>Users</th>
                        <th>Shops</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($companies as $company)
                        @php
                            $sub = $company->subscription;
                            $st = $sub ? $sub->effectiveStatus() : 'expired';
                            if (! $company->is_active) { $st = 'deactivated'; }
                        @endphp
                        <tr>
                            <td>{{ $company->id }}</td>
                            <td>{{ $company->name }}</td>
                            <td>{{ $company->owner_name ?: '-' }}</td>
                            <td>{{ $company->email }}</td>
                            <td>{{ optional(optional($sub)->plan)->name ?: '-' }}</td>
                            <td><span class="sx-sub-badge {{ subscription_status_class($st) }}">{{ $sub ? $sub->statusLabel() : 'None' }}</span></td>
                            <td>{{ optional(optional($sub)->starts_at)->format('d M Y') ?: '-' }}</td>
                            <td>{{ optional(optional($sub)->expires_at)->format('d M Y') ?: '-' }}</td>
                            <td>{{ $sub ? $sub->daysRemaining() : '-' }}</td>
                            <td>{{ $company->users_count }}</td>
                            <td>{{ $company->branches_count }}</td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">Action <span class="caret"></span></button>
                                    <ul class="dropdown-menu dropdown-menu-right">
                                        <li><a href="{{ route('owner.businesses.show', $company) }}"><i class="fa fa-eye"></i> View</a></li>
                                        <li><a href="{{ route('owner.businesses.edit', $company) }}"><i class="fa fa-pencil"></i> Edit</a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12">No businesses match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $companies->links() }}
    </div>
</div>
@endsection
