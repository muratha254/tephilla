@extends('layouts.fleet')

@section('title', 'Flat sheet stock')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Flat sheet stock',
    'subtitle' => ($branchName ?: 'Branch') . '. Each colour is a separate balance.',
    'backUrl' => route('folding.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Folding', 'url' => route('folding.index')],
        ['label' => 'Flat sheet'],
    ],
])

<div class="sx-box">
    <h3 class="sx-box-title">{{ optional($product)->name ?: 'Flat Sheet' }}</h3>
    <div class="sx-box-body">
        <table class="table table-bordered sx-gold-table">
            <thead>
                <tr>
                    <th>Colour</th>
                    <th class="text-right">Stock</th>
                    <th>Unit</th>
                </tr>
            </thead>
            <tbody>
                @forelse($balances as $balance)
                    <tr>
                        <td>{{ optional($balance->variant)->color ?: '—' }}</td>
                        <td class="text-right">{{ rtrim(rtrim(number_format((float) $balance->quantity, 4, '.', ''), '0'), '.') }}</td>
                        <td>{{ optional(optional($product)->unit)->short_name ?: 'PCS' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">No flat sheet colour balances at this branch.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p>Total: {{ rtrim(rtrim(number_format((float) $balances->sum('quantity'), 4, '.', ''), '0'), '.') }}</p>
    </div>
</div>

<div class="sx-box">
    <h3 class="sx-box-title">Recent movements</h3>
    <div class="sx-box-body">
        <table class="table table-bordered sx-gold-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Colour</th>
                    <th>Transaction</th>
                    <th class="text-right">O.S</th>
                    <th class="text-right">Received</th>
                    <th class="text-right">Folded</th>
                    <th class="text-right">C.S</th>
                    <th>Narration</th>
                    <th>User</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movements as $movement)
                    <tr>
                        <td>{{ optional($movement->occurred_at)->format('d/m/Y') }}</td>
                        <td>{{ optional($movement->variant)->color ?: '—' }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $movement->type)) }}</td>
                        <td class="text-right">{{ number_format((float) $movement->quantity_before, 2) }}</td>
                        <td class="text-right">{{ number_format((float) $movement->quantity_in, 2) }}</td>
                        <td class="text-right">{{ number_format((float) $movement->quantity_out, 2) }}</td>
                        <td class="text-right">{{ number_format((float) $movement->quantity_after, 2) }}</td>
                        <td>{{ $movement->notes }}</td>
                        <td>{{ optional($movement->user)->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9">No movements yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p><a href="{{ route('reports.stock.ledger') }}">Open the full stock ledger</a></p>
    </div>
</div>
@endsection
