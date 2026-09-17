@extends('layouts.fleet')

@section('title', 'Balance Sheet')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Balance Sheet',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'List of Charts of Account'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-file-text-o"></i> Generate Balance Sheet</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('accounting.balance-sheet') }}">
            <input type="hidden" name="show" value="1">
            <div class="form-group" style="max-width:360px;">
                <label>Select Date: <span class="sx-req">*</span></label>
                <input type="date" name="as_of" class="form-control" value="{{ $asOf }}" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa fa-file-text"></i> Generate</button>
        </form>
    </div>
</div>

<div class="sx-acc-card">
    <div class="sx-acc-result-head">
        <span><i class="fa fa-file-text-o"></i> Balance Sheet</span>
        <button type="button" class="btn btn-success btn-sm" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
    </div>
    <div class="sx-acc-card-body">
        <p class="sx-printed-on">PRINTED ON: {{ now()->format('l, F jS, Y | h:i:s A') }}</p>
        @if($generated && $report)
            <table class="table table-bordered sx-report-table">
                <thead>
                    <tr><th>Account</th><th class="text-right">Amount</th></tr>
                </thead>
                <tbody>
                    <tr><th colspan="2">Assets</th></tr>
                    @forelse($report['assets'] as $row)
                        <tr><td>{{ $row['name'] }}</td><td class="text-right">{{ number_format($row['amount'], 2) }}</td></tr>
                    @empty
                        <tr><td colspan="2">No asset balances.</td></tr>
                    @endforelse
                    <tr><th>Total Assets</th><th class="text-right">{{ number_format($report['asset_total'], 2) }}</th></tr>
                    <tr><th colspan="2">Liabilities</th></tr>
                    @forelse($report['liabilities'] as $row)
                        <tr><td>{{ $row['name'] }}</td><td class="text-right">{{ number_format($row['amount'], 2) }}</td></tr>
                    @empty
                        <tr><td colspan="2">No liability balances.</td></tr>
                    @endforelse
                    <tr><th>Total Liabilities</th><th class="text-right">{{ number_format($report['liability_total'], 2) }}</th></tr>
                    <tr><th colspan="2">Equity</th></tr>
                    @forelse($report['equity'] as $row)
                        <tr><td>{{ $row['name'] }}</td><td class="text-right">{{ number_format($row['amount'], 2) }}</td></tr>
                    @empty
                        <tr><td colspan="2">No equity balances.</td></tr>
                    @endforelse
                    <tr><th>Total Equity</th><th class="text-right">{{ number_format($report['equity_total'], 2) }}</th></tr>
                    <tr><th>Liabilities + Equity</th><th class="text-right">{{ number_format($report['liability_total'] + $report['equity_total'], 2) }}</th></tr>
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
