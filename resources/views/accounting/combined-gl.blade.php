@extends('layouts.fleet')

@section('title', 'Combined GL')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Combined GL',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'List of Charts of Account'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-file-text-o"></i> Generate Combined GL</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('accounting.combined-gl') }}">
            <input type="hidden" name="show" value="1">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Start Date: <span class="sx-req">*</span></label>
                        <input type="date" name="from" class="form-control" value="{{ $from }}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>End Date: <span class="sx-req">*</span></label>
                        <input type="date" name="to" class="form-control" value="{{ $to }}" required>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa fa-file-text"></i> Generate</button>
        </form>
    </div>
</div>

<div class="sx-acc-card">
    <div class="sx-acc-result-head">
        <span><i class="fa fa-file-text-o"></i> Combined Gl</span>
        <button type="button" class="btn btn-success btn-sm" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
    </div>
    <div class="sx-acc-card-body">
        <p class="sx-printed-on">PRINTED ON: {{ now()->format('l, F jS, Y | h:i:s A') }}</p>
        @if($generated)
            <table class="table table-bordered sx-report-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Account</th>
                        <th>Ref</th>
                        <th>Description</th>
                        <th class="text-right">Debit</th>
                        <th class="text-right">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ optional($row['date'])->format('d-m-Y') }}</td>
                            <td>{{ $row['account'] }}</td>
                            <td>{{ $row['reference'] }}</td>
                            <td>{{ $row['description'] }}</td>
                            <td class="text-right">{{ number_format($row['debit'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['credit'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No ledger activity in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
