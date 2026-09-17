@extends('layouts.fleet')

@section('title', 'Z-Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Z-Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Z-Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.summary.z') }}" class="sx-acc-filter">
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
                        <label>Category</label>
                        <select name="category" class="form-control">
                            <option value="detailed" @if($category === 'detailed') selected @endif>Detailed</option>
                            <option value="summary" @if($category === 'summary') selected @endif>Summary</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Report Selection</label>
                        <select name="report" class="form-control">
                            <option value="all" @if($report === 'all') selected @endif>-All Reports-</option>
                            <option value="sales" @if($report === 'sales') selected @endif>Sales</option>
                            <option value="payments" @if($report === 'payments') selected @endif>Payments</option>
                            <option value="tax" @if($report === 'tax') selected @endif>Tax</option>
                            <option value="voids" @if($report === 'voids') selected @endif>Voids</option>
                            <option value="returns" @if($report === 'returns') selected @endif>Returns</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="sx-form-actions">
                <button type="submit" class="btn btn-success">Show Report</button>
                <a href="{{ route('reports.summary.z') }}" class="btn btn-info">Refresh</a>
            </div>
        </form>
    </div>
</div>

@if($generated && $data)
    <div class="sx-acc-card">
        <div class="sx-acc-result-head">
            <span>Sales Summary</span>
            <div>
                <button type="button" class="btn btn-info btn-sm" id="sx-z-print"><i class="fa fa-print"></i> Print</button>
            </div>
        </div>
        <div class="sx-acc-card-body" id="sx-z-report">
            <table class="table table-bordered sx-report-table">
                <tbody>
                    <tr><th>Invoices</th><td class="text-right">{{ number_format($data['summary']['invoices']) }}</td></tr>
                    <tr><th>Subtotal</th><td class="text-right">{{ number_format($data['summary']['subtotal'], 2) }}</td></tr>
                    <tr><th>Discount</th><td class="text-right">{{ number_format($data['summary']['discount'], 2) }}</td></tr>
                    <tr><th>Tax</th><td class="text-right">{{ number_format($data['summary']['tax'], 2) }}</td></tr>
                    <tr><th>Total Sales</th><td class="text-right">{{ number_format($data['summary']['total'], 2) }}</td></tr>
                    <tr><th>Paid</th><td class="text-right">{{ number_format($data['summary']['paid'], 2) }}</td></tr>
                    <tr><th>Balance</th><td class="text-right">{{ number_format($data['summary']['balance'], 2) }}</td></tr>
                </tbody>
            </table>

            @if(!empty($data['payment_methods']))
                <h4 style="margin-top:20px;">Payment Methods</h4>
                <table class="table table-bordered sx-gold-table">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th class="text-right">Count</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['payment_methods'] as $row)
                            <tr>
                                <td>{{ $row['method'] }}</td>
                                <td class="text-right">{{ number_format($row['count']) }}</td>
                                <td class="text-right">{{ number_format($row['total'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(!empty($data['tax_breakdown']))
                <h4 style="margin-top:20px;">Tax</h4>
                <table class="table table-bordered sx-gold-table">
                    <tbody>
                        @foreach($data['tax_breakdown'] as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td class="text-right">{{ number_format($row['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if($category === 'detailed' && !empty($data['items']))
                <h4 style="margin-top:20px;">Item Sales</h4>
                <table class="table table-bordered sx-gold-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['items'] as $row)
                            <tr>
                                <td>{{ $row['product'] }}</td>
                                <td class="text-right">{{ number_format($row['qty'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['total'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(!empty($data['voids']))
                <h4 style="margin-top:20px;">Voids</h4>
                <table class="table table-bordered sx-gold-table">
                    <thead>
                        <tr>
                            <th>Receipt</th>
                            <th>Date</th>
                            <th class="text-right">Amount</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['voids'] as $row)
                            <tr>
                                <td>{{ $row['number'] }}</td>
                                <td>{{ $row['date'] }}</td>
                                <td class="text-right">{{ number_format($row['total'], 2) }}</td>
                                <td>{{ $row['reason'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(!empty($data['returns']))
                <h4 style="margin-top:20px;">Returns</h4>
                <table class="table table-bordered sx-gold-table">
                    <thead>
                        <tr>
                            <th>Number</th>
                            <th>Date</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['returns'] as $row)
                            <tr>
                                <td>{{ $row['number'] }}</td>
                                <td>{{ $row['date'] }}</td>
                                <td class="text-right">{{ number_format($row['total'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endif
@endsection

@if($generated && $data)
@push('scripts')
<script>
document.getElementById('sx-z-print') && document.getElementById('sx-z-print').addEventListener('click', function () {
    var content = document.getElementById('sx-z-report');
    if (!content) return;
    var win = window.open('', '_blank');
    win.document.write('<html><head><title>Z-Report</title>');
    win.document.write('<style>body{font-family:Arial,sans-serif;font-size:12px}table{width:100%;border-collapse:collapse;margin-bottom:16px}th,td{border:1px solid #ccc;padding:6px}h4{margin:16px 0 8px}</style>');
    win.document.write('</head><body><h2>Z-Report</h2>');
    win.document.write(content.innerHTML);
    win.document.write('</body></html>');
    win.document.close();
    win.focus();
    win.print();
});
</script>
@endpush
@endif
