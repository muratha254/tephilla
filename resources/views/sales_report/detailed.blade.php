@extends('layouts.master')

@section('title')
    Sales Detailed Report
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Sales Detailed Report</li>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
    <style>
        .daily-total-row {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .transaction-row {
            background-color: #ffffff;
        }
    </style>
@endpush

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Sales Detailed Report</h3>
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
                <form method="GET" action="{{ route('sales-report.detailed') }}" id="filterForm">
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

                <div class="row">
                    <div class="col-lg-12">
                        <table class="table table-stiped table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Receipt No</th>
                                    <th>Customer</th>
                                    <th>Payment Type</th>
                                    <th class="text-right">Gross Amount</th>
                                    <th class="text-right">Discount(%)</th>
                                    <th class="text-right">Discount</th>
                                    <th class="text-right">Sale Amount</th>
                                    <th class="text-right">Cash on Hand</th>
                                    <th class="text-right">Daily Total Sales</th>
                                    <th class="text-right">Net Sales</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($detailedData as $row)
                                @if($row['type'] == 'transaction')
                                <tr class="transaction-row">
                                    <td>{{ $row['date_formatted'] }}</td>
                                    <td>{{ $row['receipt_no'] }}</td>
                                    <td>{{ $row['customer'] }}</td>
                                    <td>
                                        <span class="label label-{{ $row['payment_method'] == 'Cash' ? 'primary' : ($row['payment_method'] == 'Mpesa' ? 'success' : 'info') }}">
                                            {{ $row['payment_method'] }}
                                        </span>
                                    </td>
                                    <td class="text-right">Ksh {{ number_format($row['gross_amount'], 2) }}</td>
                                    <td class="text-right">{{ ($row['discount_percentage'] ?? 0) > 0 ? number_format($row['discount_percentage'], 2) . '%' : '-' }}</td>
                                    <td class="text-right">{{ ($row['discount'] ?? 0) > 0 ? 'Ksh ' . number_format($row['discount'], (($row['discount_percentage'] ?? 0) > 0 ? 0 : 2)) : '-' }}</td>
                                    <td class="text-right">Ksh {{ number_format($row['amount'], 2) }}</td>
                                    <td class="text-right">
                                        @if(isset($row['cash_on_hand']) && $row['cash_on_hand'] !== null)
                                            Ksh {{ number_format($row['cash_on_hand'], 2) }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-right">-</td>
                                    <td class="text-right">-</td>
                                </tr>
                                @else
                                <tr class="daily-total-row">
                                    <td colspan="4" class="text-right"><strong>Daily Totals for {{ date('F d, Y', strtotime($row['date'])) }}:</strong></td>
                                    <td class="text-right">-</td>
                                    <td class="text-right">-</td>
                                    <td class="text-right"><strong>Ksh {{ number_format($row['total_discount'], 0) }}</strong></td>
                                    <td class="text-right">-</td>
                                    <td class="text-right"><strong>Ksh {{ number_format($row['cash_on_hand'], 2) }}</strong></td>
                                    <td class="text-right"><strong>Ksh {{ number_format($row['total_sales'], 2) }}</strong></td>
                                    <td class="text-right"><strong>Ksh {{ number_format($row['net_sales'], 2) }}</strong></td>
                                </tr>
                                @endif
                                @empty
                                <tr>
                                    <td colspan="11" class="text-center">No data available for the selected period.</td>
                                </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="bg-primary">
                                    <th colspan="4" class="text-right">Grand Total:</th>
                                    <th class="text-right">Ksh {{ number_format($totalGrossSales ?? $totalSales, 2) }}</th>
                                    <th class="text-right">-</th>
                                    <th class="text-right">Ksh {{ number_format($totalDiscount ?? 0, 0) }}</th>
                                    <th class="text-right">Ksh {{ number_format($totalSales, 2) }}</th>
                                    <th class="text-right">Ksh {{ number_format($totalCashOnHand, 2) }}</th>
                                    <th class="text-right">Ksh {{ number_format($totalSales, 2) }}</th>
                                    <th class="text-right">Ksh {{ number_format($totalNetSales, 2) }}</th>
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
        var url = '{{ route("sales-report.export-detailed-pdf") }}?filter_type=' + filterType;
        if (filterType === 'custom') {
            url += '&start_date=' + startDate + '&end_date=' + endDate;
        }
        window.open(url, '_blank');
    }

    function exportExcel() {
        var filterType = $('#filter_type').val();
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();
        var url = '{{ route("sales-report.export-detailed-excel") }}?filter_type=' + filterType;
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

