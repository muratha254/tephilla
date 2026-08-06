@extends('layouts.fleet')

@section('title', 'Stock History')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-stock.css') }}?v=1">
@endpush

@section('content')
<div class="fleet-page-head">
    <div class="fleet-page-title-row">
        <h1 class="fleet-page-title">Stock History — {{ $item->name }}</h1>
        <a href="{{ route('stock.index') }}" class="fleet-btn fleet-btn-default"><i class="fa fa-arrow-left"></i> Back to Stock List</a>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('stock.index') }}">Stock Inventory</a></li>
        <li>History</li>
    </ul>
</div>

<div class="fleet-panel fleet-stock-panel">
    <div class="fleet-table-wrap">
        <table class="fleet-stock-list-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Change</th>
                    <th>Qty After</th>
                    <th>Cost</th>
                    <th>Purchased From</th>
                    <th>Payment</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $movement)
                <tr>
                    <td>{{ $movement->formattedDate() }}</td>
                    <td>{{ $movement->typeLabel() }}</td>
                    <td>{{ $movement->quantity_change > 0 ? '+' : '' }}{{ number_format($movement->quantity_change) }}</td>
                    <td>{{ number_format($movement->quantity_after) }}</td>
                    <td>{{ $movement->unit_price !== null ? number_format((float) $movement->unit_price, 0) : '-' }}</td>
                    <td>{{ $movement->purchased_from ?: '-' }}</td>
                    <td>{{ $movement->payment_status ?: '-' }}</td>
                    <td>{{ $movement->description ?: ($movement->notes ?: '-') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="fleet-empty-row">No movement history yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($movements->hasPages())
    <div class="fleet-stock-pagination">
        {{ $movements->links('fleet.incidents.partials.pagination') }}
    </div>
    @endif
</div>
@endsection
