@extends('layouts.fleet')

@section('title', 'Payments Accounts Statements')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Payments Accounts Statements',
    'subtitle' => $account->name,
    'backUrl' => route('accounting.balances'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Account Balances', 'url' => route('accounting.balances')],
        ['label' => 'Statement'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-result-head">
        <span>{{ $account->name }} ({{ $account->gl_code }})</span>
        <button type="button" class="btn btn-success btn-sm" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
    </div>
    <div class="sx-acc-card-body">
        <p class="sx-printed-on">From {{ \Carbon\Carbon::parse($from)->format('d-m-Y') }} to {{ \Carbon\Carbon::parse($to)->format('d-m-Y') }}</p>
        <table class="table table-bordered sx-report-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Ref</th>
                    <th>Description</th>
                    <th class="text-right">Debit</th>
                    <th class="text-right">Credit</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="3"><strong>Opening Balance</strong></td>
                    <td class="text-right" colspan="2"><strong>Ksh {{ number_format($opening, 2) }}</strong></td>
                </tr>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ optional($row['date'])->format('d-m-Y') }}</td>
                        <td>{{ $row['reference'] }}</td>
                        <td>{{ $row['description'] }}</td>
                        <td class="text-right">{{ number_format($row['debit'], 2) }}</td>
                        <td class="text-right">{{ number_format($row['credit'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">No transactions in this period.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3">Closing Balance</th>
                    <th class="text-right" colspan="2">Ksh {{ number_format($closing, 2) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
