@extends('layouts.master')

@section('title')
    Daily Closing Report
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Daily Closing Report</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Daily Closing Report</h3>
                <div class="pull-right">
                    <button onclick="exportPdf()" class="btn btn-danger">
                        <i class="fa fa-file-pdf-o"></i> Export PDF
                    </button>
                </div>
            </div>
            <div class="box-body">
                <form action="{{ route('reports.daily-closing') }}" method="GET" id="filter-form" class="mb-3">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="date">Date</label>
                                <input type="date" name="date" id="date" class="form-control"
                                    value="{{ $date }}" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fa fa-search"></i> View Report
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="row">
                    <!-- Opening Balance -->
                    <div class="col-md-12">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title">Opening Balance</h3>
                            </div>
                            <div class="box-body">
                                <h2 class="text-primary">KES {{ number_format($openingCash, 2) }}</h2>
                                @if($dailyCash)
                                    <p class="text-muted">
                                        Opened by: {{ $dailyCash->openedByUser->name ?? 'N/A' }} 
                                        @if($dailyCash->opened_at)
                                            at {{ $dailyCash->opened_at->format('H:i:s') }}
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Sales by Payment Method -->
                    <div class="col-md-6">
                        <div class="box box-success">
                            <div class="box-header with-border">
                                <h3 class="box-title">Sales by Payment Method</h3>
                            </div>
                            <div class="box-body">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Payment Method</th>
                                            <th class="text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($salesByMethod as $method => $amount)
                                            @if($amount > 0)
                                                <tr>
                                                    <td><strong>{{ $method }}</strong></td>
                                                    <td class="text-right">KES {{ number_format($amount, 2) }}</td>
                                                </tr>
                                            @endif
                                        @endforeach
                                        <tr class="bg-gray">
                                            <td><strong>Total Sales</strong></td>
                                            <td class="text-right"><strong>KES {{ number_format($totalSales, 2) }}</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Expenses -->
                    <div class="col-md-6">
                        <div class="box box-danger">
                            <div class="box-header with-border">
                                <h3 class="box-title">Expenses</h3>
                            </div>
                            <div class="box-body">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th class="text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>Purchases</strong></td>
                                            <td class="text-right">KES {{ number_format($totalPurchases, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Supplier Payments</strong></td>
                                            <td class="text-right">KES {{ number_format($totalSupplierPayments, 2) }}</td>
                                        </tr>
                                        <tr class="bg-gray">
                                            <td><strong>Total Expenses</strong></td>
                                            <td class="text-right"><strong>KES {{ number_format($totalPurchases + $totalSupplierPayments, 2) }}</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Supplier Payments by Method -->
                    <div class="col-md-6">
                        <div class="box box-warning">
                            <div class="box-header with-border">
                                <h3 class="box-title">Supplier Payments by Method</h3>
                            </div>
                            <div class="box-body">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Payment Method</th>
                                            <th class="text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($paymentsByMethod as $method => $amount)
                                            @if($amount > 0)
                                                <tr>
                                                    <td><strong>{{ $method }}</strong></td>
                                                    <td class="text-right">KES {{ number_format($amount, 2) }}</td>
                                                </tr>
                                            @endif
                                        @endforeach
                                        <tr class="bg-gray">
                                            <td><strong>Total Payments</strong></td>
                                            <td class="text-right"><strong>KES {{ number_format($totalSupplierPayments, 2) }}</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Account Balances -->
                    <div class="col-md-6">
                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title">Account Balances</h3>
                            </div>
                            <div class="box-body">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Account</th>
                                            <th class="text-right">Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($accounts as $account)
                                            <tr>
                                                <td><strong>{{ $account->name }}</strong></td>
                                                <td class="text-right">KES {{ number_format($account->balance, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Summary -->
                    <div class="col-md-12">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title">Summary</h3>
                            </div>
                            <div class="box-body">
                                <table class="table table-bordered">
                                    <tbody>
                                        <tr>
                                            <td width="30%"><strong>Opening Cash</strong></td>
                                            <td class="text-right">KES {{ number_format($openingCash, 2) }}</td>
                                        </tr>
                                        <tr class="bg-green">
                                            <td><strong>Total Sales</strong></td>
                                            <td class="text-right">KES {{ number_format($totalSales, 2) }}</td>
                                        </tr>
                                        <tr class="bg-red">
                                            <td><strong>Total Purchases</strong></td>
                                            <td class="text-right">KES {{ number_format($totalPurchases, 2) }}</td>
                                        </tr>
                                        <tr class="bg-red">
                                            <td><strong>Total Supplier Payments</strong></td>
                                            <td class="text-right">KES {{ number_format($totalSupplierPayments, 2) }}</td>
                                        </tr>
                                        <tr class="bg-gray">
                                            <td><strong>Net Cash Position</strong></td>
                                            <td class="text-right">KES {{ number_format($netCash, 2) }}</td>
                                        </tr>
                                        <tr class="bg-primary">
                                            <td><strong>Closing Balance</strong></td>
                                            <td class="text-right"><strong>KES {{ number_format($closingBalance, 2) }}</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function exportPdf() {
        const date = document.getElementById('date').value;
        const url = '{{ route("reports.daily-closing.export-pdf") }}?date=' + date;
        window.open(url, '_blank');
    }
</script>
@endpush










