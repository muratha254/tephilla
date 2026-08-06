@extends('layouts.fleet')

@section('title', 'Stock Inventory')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-stock.css') }}?v=3">
@endpush

@section('content')
@php
    $queryParams = fn (array $extra = []) => array_filter(array_merge([
        'search' => $search,
        'view' => $viewMode,
    ], $extra), fn ($value) => $value !== null && $value !== '');
@endphp

<div class="fleet-page-head">
    <h1 class="fleet-page-title">Stock Inventory</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li>Stock Inventory</li>
    </ul>
</div>

<div class="fleet-panel fleet-stock-panel">
    <div class="fleet-stock-toolbar">
        <div class="fleet-stock-summary">
            <div class="fleet-stock-summary-item">
                <strong>{{ $itemCount }}</strong> Items
            </div>
            <div class="fleet-stock-summary-item">
                <strong>{{ number_format($totalAssetValue, 2) }}</strong> Total Asset Value
            </div>
        </div>

        <form method="GET" action="{{ route('stock.index') }}" class="fleet-stock-filters">
            @if ($viewMode === 'list')
                <input type="hidden" name="view" value="list">
            @endif
            <div class="fleet-search-wrap">
                <i class="fa fa-search"></i>
                <input type="text" name="search" class="fleet-search-input" value="{{ $search }}" placeholder="Search Inventory...">
            </div>
            <div class="fleet-view-toggle">
                <a href="{{ route('stock.index', $queryParams(['view' => 'grid'])) }}" class="fleet-view-icon {{ $viewMode === 'grid' ? 'active' : '' }}" aria-label="Grid view"><i class="fa fa-th"></i></a>
                <a href="{{ route('stock.index', $queryParams(['view' => 'list'])) }}" class="fleet-view-icon {{ $viewMode === 'list' ? 'active' : '' }}" aria-label="List view"><i class="fa fa-list"></i></a>
            </div>
        </form>
    </div>

    @if ($viewMode === 'list')
    <div class="fleet-table-wrap">
        <table class="fleet-stock-list-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Status</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Asset Value</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                <tr>
                    <td>
                        <div class="fleet-stock-item-name">{{ $item->name }}</div>
                        <div class="fleet-stock-item-desc">{{ $item->description ?: '-' }}</div>
                    </td>
                    <td>
                        <span class="fleet-stock-status {{ $item->isActive() ? 'is-active' : 'is-inactive' }}">{{ $item->status }}</span>
                    </td>
                    <td>{{ number_format($item->quantity) }}</td>
                    <td>{{ $item->formattedUnitPrice() }}</td>
                    <td>{{ $item->formattedAssetValue() }}</td>
                    <td>
                        @include('fleet.stock.partials.card_actions', ['item' => $item])
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="fleet-empty-row">No stock items found. <a href="{{ route('stock.create') }}">Add stock</a>.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @else
    <div class="fleet-stock-grid">
        @forelse ($items as $item)
        <div class="fleet-stock-card">
            <div class="fleet-stock-card-head">
                <h3>{{ $item->name }}</h3>
                <span class="fleet-stock-status {{ $item->isActive() ? 'is-active' : 'is-inactive' }}">{{ $item->status }}</span>
            </div>

            <p class="fleet-stock-card-desc">{{ $item->description ?: 'No description provided.' }}</p>

            <div class="fleet-stock-card-stats">
                <div>
                    <span>Quantity</span>
                    <strong>{{ number_format($item->quantity) }}</strong>
                </div>
                <div>
                    <span>Price</span>
                    <strong>{{ $item->formattedUnitPrice() }}</strong>
                </div>
            </div>

            @include('fleet.stock.partials.card_actions', ['item' => $item])
        </div>
        @empty
        <div class="fleet-stock-empty">
            No stock items found. <a href="{{ route('stock.create') }}">Add your first stock item</a>.
        </div>
        @endforelse
    </div>
    @endif
</div>

@include('fleet.stock.partials.adjust_modal')
@endsection

@push('scripts')
@include('fleet.stock.partials.adjust_modal_scripts')
@endpush
