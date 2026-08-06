@extends('layouts.master')

@section('title')
    Sales Reports
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Sales Reports</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Sales Reports</h3>
                <div class="pull-right">
                    <button onclick="printReport()" class="btn btn-success">
                        <i class="fa fa-print"></i> Print Report
                    </button>
                </div>
            </div>
            <div class="box-body">
                <!-- Report Type Selector -->
                <div class="form-group">
                    <label for="report-type">Report Type</label>
                    <select class="form-control" id="report-type">
                        <option value="daily">Daily Report</option>
                        <option value="weekly">Weekly Report</option>
                        <option value="monthly">Monthly Report</option>
                        <option value="annual">Annual Report</option>
                        <option value="custom">Custom Date Range</option>
                    </select>
                </div>

                <!-- Date Range Picker (for custom date range) -->
                <div class="form-group" id="date-range-container" style="display: none;">
                    <label>Date Range</label>
                    <div class="input-group">
                        <input type="date" class="form-control" id="start-date">
                        <span class="input-group-addon">to</span>
                        <input type="date" class="form-control" id="end-date">
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-primary" id="btn-apply-date-filter">
                                <i class="fa fa-filter"></i> Apply Filter
                            </button>
                        </span>
                    </div>
                </div>

                <!-- Charts Container -->
                <div class="row">
                    <div class="col-md-6">
                        <canvas id="sales-chart"></canvas>
                    </div>
                    <div class="col-md-6">
                        <canvas id="transactions-chart"></canvas>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mt-4">
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-aqua">
                            <div class="inner">
                                <h3 id="total-sales">0</h3>
                                <p>Total Sales</p>
                            </div>
                            <div class="icon">
                                <i class="fa fa-shopping-cart"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-green">
                            <div class="inner">
                                <h3 id="total-transactions">0</h3>
                                <p>Total Transactions</p>
                            </div>
                            <div class="icon">
                                <i class="fa fa-money"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-yellow">
                            <div class="inner">
                                <h3 id="avg-transaction">0</h3>
                                <p>Average Transaction</p>
                            </div>
                            <div class="icon">
                                <i class="fa fa-calculator"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-red">
                            <div class="inner">
                                <h3 id="total-discount">0</h3>
                                <p>Total Discount</p>
                            </div>
                            <div class="icon">
                                <i class="fa fa-tag"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Date/Period</th>
                                <th>Transactions</th>
                                <th>Items Sold</th>
                                <th>Total Sales</th>
                                <th>Average Sale</th>
                                <th>Discount</th>
                            </tr>
                        </thead>
                        <tbody id="report-table-body">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let salesChart = null;
let transactionsChart = null;

$(document).ready(function() {
    // Initialize charts
    initializeCharts();
    
    // Load daily report by default
    loadReport('daily');

    // Handle report type change
    $('#report-type').change(function() {
        var reportType = $(this).val();
        if (reportType === 'custom') {
            $('#date-range-container').show();
            // Set default dates (current month)
            var today = new Date();
            var firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            var lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            $('#start-date').val(firstDay.toISOString().split('T')[0]);
            $('#end-date').val(lastDay.toISOString().split('T')[0]);
        } else {
            $('#date-range-container').hide();
            loadReport(reportType);
        }
    });
    
    // Handle apply date filter button
    $('#btn-apply-date-filter').click(function() {
        loadCustomDateReport();
    });
});

function initializeCharts() {
    const salesCtx = document.getElementById('sales-chart').getContext('2d');
    const transactionsCtx = document.getElementById('transactions-chart').getContext('2d');

    salesChart = new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Sales Amount',
                data: [],
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }]
        }
    });

    transactionsChart = new Chart(transactionsCtx, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: 'Number of Transactions',
                data: [],
                backgroundColor: 'rgb(54, 162, 235)'
            }]
        }
    });
}

function loadReport(type) {
    let url;
    switch(type) {
        case 'daily':
            url = '{{ route("report.daily") }}';
            break;
        case 'weekly':
            url = '{{ route("report.weekly") }}';
            break;
        case 'monthly':
            url = '{{ route("report.monthly") }}';
            break;
        case 'annual':
            url = '{{ route("report.annual") }}';
            break;
        case 'custom':
            loadCustomDateReport();
            return;
    }

    $.get(url)
        .done(function(data) {
            updateCharts(data, type);
            updateSummary(data);
            updateTable(data, type);
        })
        .fail(function(error) {
            alert('Error loading report data');
        });
}

function loadCustomDateReport() {
    var startDate = $('#start-date').val();
    var endDate = $('#end-date').val();
    
    if (!startDate || !endDate) {
        alert('Please select both start and end dates');
        return;
    }
    
    if (startDate > endDate) {
        alert('Start date cannot be after end date');
        return;
    }
    
    var url = '{{ route("report.data") }}?start_date=' + encodeURIComponent(startDate) + '&end_date=' + encodeURIComponent(endDate);
    
    $.get(url)
        .done(function(data) {
            updateCharts(data, 'custom');
            updateSummary(data);
            updateTable(data, 'custom');
        })
        .fail(function(error) {
            alert('Error loading report data');
        });
}

function updateCharts(data, type) {
    const labels = data.map(item => {
        switch(type) {
            case 'daily':
                return item.hour;
            case 'annual':
                return getMonthName(item.month);
            case 'custom':
                return item.date;
            default:
                return item.date;
        }
    });

    const salesData = data.map(item => item.total_amount);
    const transactionData = data.map(item => item.total_transactions);

    salesChart.data.labels = labels;
    salesChart.data.datasets[0].data = salesData;
    salesChart.update();

    transactionsChart.data.labels = labels;
    transactionsChart.data.datasets[0].data = transactionData;
    transactionsChart.update();
}

function updateSummary(data) {
    const totalSales = data.reduce((sum, item) => sum + parseFloat(item.total_amount), 0);
    const totalTransactions = data.reduce((sum, item) => sum + parseInt(item.total_transactions), 0);
    const avgTransaction = totalTransactions > 0 ? totalSales / totalTransactions : 0;
    const totalDiscount = data.reduce((sum, item) => sum + (parseFloat(item.total_discount) || 0), 0);

    $('#total-sales').text('Ksh ' + formatNumber(totalSales));
    $('#total-transactions').text(totalTransactions);
    $('#avg-transaction').text('Ksh ' + formatNumber(avgTransaction));
    $('#total-discount').text('Ksh ' + formatNumber(totalDiscount));
}

function updateTable(data, type) {
    const tbody = $('#report-table-body');
    tbody.empty();

    data.forEach(item => {
        const row = $('<tr>');
        let date;
        if (type === 'annual') {
            date = getMonthName(item.month);
        } else if (type === 'daily') {
            date = item.hour;
        } else if (type === 'custom') {
            date = item.date;
        } else {
            date = item.date;
        }
        
        row.append(`<td>${date}</td>`);
        row.append(`<td>${item.total_transactions}</td>`);
        row.append(`<td>${item.total_items || 0}</td>`);
        const avgSale = item.total_transactions > 0 ? (item.total_amount / item.total_transactions) : 0;
        row.append(`<td>Ksh ${formatNumber(item.total_amount)}</td>`);
        row.append(`<td>Ksh ${formatNumber(avgSale)}</td>`);
        row.append(`<td>Ksh ${formatNumber(item.total_discount || 0)}</td>`);
        
        tbody.append(row);
    });
}

function formatNumber(number) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(number);
}

function getMonthName(monthNumber) {
    const months = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    return months[monthNumber - 1];
}

function printReport() {
    const reportType = $('#report-type').val();
    let url = `{{ route('report.print') }}?type=${reportType}`;
    
    // If custom date range, include date parameters
    if (reportType === 'custom') {
        const startDate = $('#start-date').val();
        const endDate = $('#end-date').val();
        if (startDate && endDate) {
            url += `&start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`;
        } else {
            alert('Please select both start and end dates before printing');
            return;
        }
    }
    
    window.open(url, '_blank');
}
</script>
@endpush
