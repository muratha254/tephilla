@extends('layouts.master')

@section('title')
    Sales Summary Report
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Sales Summary Report</li>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
@endpush

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Sales Summary Report</h3>
                <div class="pull-right">
                    <button onclick="exportPdf()" class="btn btn-danger btn-flat">
                        <i class="fa fa-file-pdf-o"></i> Export PDF
                    </button>
                    <button onclick="exportExcel()" class="btn btn-success btn-flat">
                        <i class="fa fa-file-excel-o"></i> Export Excel
                    </button>
                </div>
            </div>
            <div class="box-body">
                <form method="GET" action="{{ route('sales-report.summary') }}" id="filterForm">
                    <div class="row">
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label for="filter_type">Filter Type</label>
                                <select name="filter_type" id="filter_type" class="form-control" onchange="toggleCustomDates()">
                                    <option value="daily" {{ $filterType == 'daily' ? 'selected' : '' }}>Daily</option>
                                    <option value="monthly" {{ $filterType == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                    <option value="yearly" {{ $filterType == 'yearly' ? 'selected' : '' }}>Yearly</option>
                                    <option value="custom" {{ $filterType == 'custom' ? 'selected' : '' }}>Custom Date Range</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-3" id="start_date_group" style="display: {{ $filterType == 'custom' ? 'block' : 'none' }};">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" name="start_date" id="start_date" class="form-control" value="{{ $startDate ?? date('Y-m-01') }}">
                            </div>
                        </div>
                        <div class="col-lg-3" id="end_date_group" style="display: {{ $filterType == 'custom' ? 'block' : 'none' }};">
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" name="end_date" id="end_date" class="form-control" value="{{ $endDate ?? date('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label>&nbsp;</label><br>
                                <button type="submit" class="btn btn-primary btn-flat">
                                    <i class="fa fa-filter"></i> Apply Filter
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="alert alert-info">
                            <h4><i class="icon fa fa-info"></i> Report Period</h4>
                            <p><strong>From:</strong> {{ $start->format('F d, Y') }} <strong>To:</strong> {{ $end->format('F d, Y') }}</p>
                        </div>
                    </div>
                </div>

                @if($filterType == 'daily')
                <div class="row">
                    <div class="col-lg-4">
                        <div class="info-box bg-green">
                            <span class="info-box-icon"><i class="fa fa-money"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Cash Account</span>
                                <span class="info-box-number">Ksh {{ number_format($accountBalances['Cash'] ?? 0, 2) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="info-box bg-blue">
                            <span class="info-box-icon"><i class="fa fa-mobile"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Mpesa Account</span>
                                <span class="info-box-number">Ksh {{ number_format($accountBalances['Mpesa'] ?? 0, 2) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="info-box bg-yellow">
                            <span class="info-box-icon"><i class="fa fa-credit-card"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Card Account</span>
                                <span class="info-box-number">Ksh {{ number_format($accountBalances['Card'] ?? 0, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <div class="row">
                    <div class="col-lg-12">
                        <table class="table table-stiped table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th class="text-right">Cash on Hand</th>
                                    <th class="text-right">Gross Sales</th>
                                    <th class="text-right">Discount</th>
                                    <th class="text-right">Total Sales</th>
                                    <th class="text-right">Net Sales</th>
                                    <th class="text-center">Transactions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($summaryData as $row)
                                <tr>
                                    <td>{{ $row['date_formatted'] }}</td>
                                    <td class="text-right">Ksh {{ number_format($row['cash_on_hand'], 2) }}</td>
                                    <td class="text-right">Ksh {{ number_format($row['gross_sales'], 2) }}</td>
                                    <td class="text-right">Ksh {{ number_format($row['total_discount'], 0) }}</td>
                                    <td class="text-right">Ksh {{ number_format($row['total_sales'], 2) }}</td>
                                    <td class="text-right">Ksh {{ number_format($row['net_sales'], 2) }}</td>
                                    <td class="text-center">{{ $row['transaction_count'] }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center">No data available for the selected period.</td>
                                </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="bg-primary">
                                    <th>Total</th>
                                    <th class="text-right">Ksh {{ number_format($totalCashOnHand, 2) }}</th>
                                    <th class="text-right">Ksh {{ number_format($totalGrossSales ?? $totalSales, 2) }}</th>
                                    <th class="text-right">Ksh {{ number_format($totalDiscount ?? 0, 0) }}</th>
                                    <th class="text-right">Ksh {{ number_format($totalSales, 2) }}</th>
                                    <th class="text-right">Ksh {{ number_format($totalNetSales, 2) }}</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
<script>
    function toggleCustomDates() {
        var filterType = $('#filter_type').val();
        if (filterType === 'custom') {
            $('#start_date_group').show();
            $('#end_date_group').show();
        } else {
            $('#start_date_group').hide();
            $('#end_date_group').hide();
        }
    }

    function exportPdf() {
        var filterType = $('#filter_type').val();
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();
        var url = '{{ route("sales-report.export-summary-pdf") }}?filter_type=' + filterType;
        if (filterType === 'custom') {
            url += '&start_date=' + startDate + '&end_date=' + endDate;
        }
        window.open(url, '_blank');
    }

    function exportExcel() {
        var filterType = $('#filter_type').val();
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();
        var url = '{{ route("sales-report.export-summary-excel") }}?filter_type=' + filterType;
        if (filterType === 'custom') {
            url += '&start_date=' + startDate + '&end_date=' + endDate;
        }
        window.location.href = url;
    }

    $(function() {
        toggleCustomDates();
    });
</script>
@endpush

