@extends('layouts.fleet')

@section('title', 'Purchase History')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-stock.css') }}?v=1">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">Purchase History</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('stock.index') }}">Stock Inventory</a></li>
        <li>Purchase History</li>
    </ul>
</div>

<div class="fleet-panel fleet-stock-panel">
    <form method="GET" action="{{ route('stock.purchases') }}" class="fleet-stock-toolbar">
        <div class="fleet-stock-summary">
            <div class="fleet-stock-summary-item">
                <strong>{{ $purchases->total() }}</strong> Purchase Records
            </div>
        </div>
        <div class="fleet-stock-filters">
            <div class="fleet-search-wrap">
                <i class="fa fa-search"></i>
                <input type="text" name="search" class="fleet-search-input" value="{{ $search }}" placeholder="Search purchases...">
            </div>
        </div>
    </form>

    <div class="fleet-table-wrap">
        <table class="fleet-stock-list-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Cost</th>
                    <th>Total</th>
                    <th>Purchased From</th>
                    <th>Payment</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($purchases as $purchase)
                <tr>
                    <td>{{ $purchase->formattedDate() }}</td>
                    <td>{{ optional($purchase->item)->name ?: '-' }}</td>
                    <td>+{{ number_format($purchase->quantity_change) }}</td>
                    <td>{{ $purchase->unit_price !== null ? number_format((float) $purchase->unit_price, 0) : '-' }}</td>
                    <td>{{ $purchase->unit_price !== null ? number_format((float) $purchase->unit_price * $purchase->quantity_change, 2) : '-' }}</td>
                    <td>{{ $purchase->purchased_from ?: '-' }}</td>
                    <td>{{ $purchase->payment_status ?: '-' }}</td>
                    <td>{{ $purchase->description ?: '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="fleet-empty-row">No purchase records yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($purchases->hasPages())
    <div class="fleet-stock-pagination">
        {{ $purchases->links('fleet.incidents.partials.pagination') }}
    </div>
    @endif
</div>
@endsection
