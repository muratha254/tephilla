@extends('layouts.fleet')

@section('title', 'Summary Daily Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Summary Daily Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Summary Daily Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-calendar"></i> Please Select Date Range</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.summary.daily') }}" class="sx-acc-filter">
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
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Employee</label>
                        <select name="user_id" class="form-control">
                            <option value="">-Select-</option>
                            @foreach($staff as $member)
                                <option value="{{ $member->id }}" @if((string) $userId === (string) $member->id) selected @endif>{{ $member->name }}</option>
                            @endforeach
                        </select>
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
    <div class="sx-acc-card-head"><i class="fa fa-bar-chart"></i> Report</div>
    <div class="sx-acc-card-body">
        @if($generated && $report)
            <table class="table table-bordered sx-report-table">
                <tbody>
                    <tr><th>Sales Invoices</th><td class="text-right">{{ number_format($report['sales_count']) }}</td></tr>
                    <tr><th>Subtotal</th><td class="text-right">{{ number_format($report['subtotal'], 2) }}</td></tr>
                    <tr><th>Discount</th><td class="text-right">{{ number_format($report['discount'], 2) }}</td></tr>
                    <tr><th>Tax</th><td class="text-right">{{ number_format($report['tax'], 2) }}</td></tr>
                    <tr><th>Total Sales</th><td class="text-right">{{ number_format($report['total'], 2) }}</td></tr>
                    <tr><th>Paid</th><td class="text-right">{{ number_format($report['paid'], 2) }}</td></tr>
                    <tr><th>Balance Due</th><td class="text-right">{{ number_format($report['balance'], 2) }}</td></tr>
                    <tr><th>Returns</th><td class="text-right">{{ number_format($report['returns'], 2) }}</td></tr>
                    <tr><th>Net Sales</th><td class="text-right">{{ number_format($report['net_sales'], 2) }}</td></tr>
                    <tr><th>Expenses</th><td class="text-right">{{ number_format($report['expenses'], 2) }}</td></tr>
                    <tr><th>Purchases</th><td class="text-right">{{ number_format($report['purchases'], 2) }}</td></tr>
                </tbody>
            </table>

            @if(!empty($report['payment_methods']))
                <h4 style="margin-top:20px;">Payment Methods</h4>
                <table class="table table-bordered sx-gold-table">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['payment_methods'] as $method => $amount)
                            <tr>
                                <td>{{ ucfirst(str_replace('_', ' ', $method)) }}</td>
                                <td class="text-right">{{ number_format($amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @elseif($generated)
            <p class="text-muted">No data found for the selected period.</p>
        @endif
    </div>
</div>
@endsection
