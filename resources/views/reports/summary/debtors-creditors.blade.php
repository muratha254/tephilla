@extends('layouts.fleet')

@section('title', 'Debtors | Creditors')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Debtors | Creditors',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Debtors | Creditors'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-calendar"></i> Please Select Date Range</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.summary.debtors-creditors') }}" class="sx-acc-filter">
            <input type="hidden" name="show" value="1">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>From Date</label>
                        <input type="date" name="from" class="form-control" value="{{ $from }}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>To Date</label>
                        <input type="date" name="to" class="form-control" value="{{ $to }}" required>
                    </div>
                </div>
            </div>
            <div class="sx-form-actions">
                <button type="submit" class="btn btn-success">Show</button>
                <a href="{{ route('dashboard') }}" class="btn btn-warning">Close</a>
            </div>
        </form>
    </div>
</div>

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-file-text-o"></i> Report</div>
    <div class="sx-acc-card-body">
        @if($generated && $report)
            <h4>Debtors (Customers)</h4>
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Branch</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th class="text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report['debtors'] as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $row['branch'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['phone'] ?: '-' }}</td>
                                <td class="text-right">{{ number_format($row['balance'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No debtor balances found.</td></tr>
                        @endforelse
                        @if($report['debtors']->isNotEmpty())
                            <tr>
                                <th colspan="4" class="text-right">Total Debtors</th>
                                <th class="text-right">{{ number_format($report['debtors_total'], 2) }}</th>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            <h4 style="margin-top:24px;">Creditors (Suppliers)</h4>
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Branch</th>
                            <th>Supplier</th>
                            <th>Phone</th>
                            <th class="text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report['creditors'] as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $row['branch'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['phone'] ?: '-' }}</td>
                                <td class="text-right">{{ number_format($row['balance'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No creditor balances found.</td></tr>
                        @endforelse
                        @if($report['creditors']->isNotEmpty())
                            <tr>
                                <th colspan="4" class="text-right">Total Creditors</th>
                                <th class="text-right">{{ number_format($report['creditors_total'], 2) }}</th>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
