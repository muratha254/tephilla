@extends('layouts.fleet')

@section('title', 'Trial Balance')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Trial Balance',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'List of Charts of Account'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-file-text-o"></i> Generate Balance Sheet</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('accounting.trial-balance') }}">
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
        <span><i class="fa fa-file-text-o"></i> Trial Sheet</span>
        <button type="button" class="btn btn-success btn-sm" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
    </div>
    <div class="sx-acc-card-body">
        <p class="sx-printed-on">PRINTED ON: {{ now()->format('l, F jS, Y | h:i:s A') }}</p>
        @if($generated)
            <table class="table table-bordered sx-report-table">
                <thead>
                    <tr>
                        <th>GL Code</th>
                        <th>Account Name</th>
                        <th>Type</th>
                        <th class="text-right">Debit</th>
                        <th class="text-right">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row['gl_code'] }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td>{{ $row['type'] }}</td>
                            <td class="text-right">{{ number_format($row['debit'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['credit'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No trial balance rows for this date.</td></tr>
                    @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                    <tfoot>
                        <tr>
                            <th colspan="3">Total</th>
                            <th class="text-right">{{ number_format($rows->sum('debit'), 2) }}</th>
                            <th class="text-right">{{ number_format($rows->sum('credit'), 2) }}</th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        @endif
    </div>
</div>
@endsection
