@extends('layouts.fleet')

@section('title', 'Dashboard')

@section('content')
@php
    $money = function ($amount) use ($currencySymbol) {
        return $currencySymbol . ' ' . number_format((float) $amount, 2);
    };
@endphp

<div class="sx-content-header">
    <div class="sx-content-title">
        <a href="{{ route('dashboard') }}" class="sx-back-btn" aria-label="Back"><i class="fa fa-chevron-left"></i></a>
        <div>
            <h1>Dashboard</h1>
            <small>Overall Information on Single Screen</small>
        </div>
    </div>
    <a href="{{ route('dashboard') }}" class="sx-home-link"><i class="fa fa-home"></i> Home</a>
</div>

<div class="sx-dash-card">
    <form method="get" action="{{ route('dashboard') }}">
        <label for="branch_id">Branch/Station<sup style="color:#dd4b39;">*</sup></label>
        <select id="branch_id" name="branch_id" class="form-control sx-branch-select" onchange="this.form.submit()">
            @foreach($branches as $option)
                <option value="{{ $option->id }}" @if($branch && (int) $branch->id === (int) $option->id) selected @endif>
                    {{ $option->name }}
                </option>
            @endforeach
        </select>
    </form>

    <div class="row">
        <div class="col-md-3 col-sm-6">
            @if(!empty($can['purchases']))
                <a href="{{ route('purchases.index') }}" class="sx-info-box-link" title="View purchases">
            @endif
            <div class="sx-info-box sx-aqua {{ !empty($can['purchases']) ? 'is-clickable' : '' }}">
                <div class="sx-info-icon"><i class="fa fa-minus-circle"></i></div>
                <div class="sx-info-body">
                    <span class="sx-info-label">Purchase Due</span>
                    <span class="sx-info-value">{{ $money($can['purchases'] ? $purchaseDue : 0) }}</span>
                    <span class="sx-info-meta">Accounts Payable</span>
                </div>
            </div>
            @if(!empty($can['purchases']))
                </a>
            @endif
        </div>
        <div class="col-md-3 col-sm-6">
            @if(!empty($can['sales']))
                <a href="{{ route('sales.index', ['period' => 'all']) }}" class="sx-info-box-link" title="View sales due">
            @endif
            <div class="sx-info-box sx-green {{ !empty($can['sales']) ? 'is-clickable' : '' }}">
                <div class="sx-info-icon"><i class="fa fa-money"></i></div>
                <div class="sx-info-body">
                    <span class="sx-info-label">Sales Due</span>
                    <span class="sx-info-value">{{ $money($can['sales'] ? $salesDue : 0) }}</span>
                    <span class="sx-info-meta">Accounts Receivable</span>
                </div>
            </div>
            @if(!empty($can['sales']))
                </a>
            @endif
        </div>
        <div class="col-md-3 col-sm-6">
            @if(!empty($can['sales']))
                <a href="{{ route('sales.index', ['period' => 'today']) }}" class="sx-info-box-link" title="View today's sales">
            @endif
            <div class="sx-info-box sx-aqua {{ !empty($can['sales']) ? 'is-clickable' : '' }}">
                <div class="sx-info-icon"><i class="fa fa-shopping-cart"></i></div>
                <div class="sx-info-body">
                    <span class="sx-info-label">Today's Sales</span>
                    <span class="sx-info-value">{{ $money($can['sales'] ? $todaySales : 0) }}</span>
                    <span class="sx-info-meta">{{ $todayDate }}</span>
                </div>
            </div>
            @if(!empty($can['sales']))
                </a>
            @endif
        </div>
        <div class="col-md-3 col-sm-6">
            @if(!empty($can['expenses']))
                <a href="{{ route('expenses.index') }}" class="sx-info-box-link" title="View expenses">
            @endif
            <div class="sx-info-box sx-lime {{ !empty($can['expenses']) ? 'is-clickable' : '' }}">
                <div class="sx-info-icon"><i class="fa fa-flag"></i></div>
                <div class="sx-info-body">
                    <span class="sx-info-label">Expense Amount</span>
                    <span class="sx-info-value">{{ $money($can['expenses'] ? $monthExpenses : 0) }}</span>
                    <span class="sx-info-meta">{{ $monthLabel }}</span>
                </div>
            </div>
            @if(!empty($can['expenses']))
                </a>
            @endif
        </div>
        <div class="col-md-3 col-sm-6">
            @if(!empty($can['customers']))
                <a href="{{ route('customers.index') }}" class="sx-info-box-link" title="View customers">
            @endif
            <div class="sx-info-box sx-red {{ !empty($can['customers']) ? 'is-clickable' : '' }}">
                <div class="sx-info-icon"><i class="fa fa-users"></i></div>
                <div class="sx-info-body">
                    <span class="sx-info-label">Customers</span>
                    <span class="sx-info-value">Active: {{ $can['customers'] ? $customersActive : 0 }}</span>
                    <span class="sx-info-meta">Inactive: {{ $can['customers'] ? $customersInactive : 0 }}</span>
                </div>
            </div>
            @if(!empty($can['customers']))
                </a>
            @endif
        </div>
        <div class="col-md-3 col-sm-6">
            @if(!empty($can['suppliers']))
                <a href="{{ route('suppliers.index') }}" class="sx-info-box-link" title="View suppliers">
            @endif
            <div class="sx-info-box sx-aqua {{ !empty($can['suppliers']) ? 'is-clickable' : '' }}">
                <div class="sx-info-icon"><i class="fa fa-car"></i></div>
                <div class="sx-info-body">
                    <span class="sx-info-label">Suppliers</span>
                    <span class="sx-info-value">Active: {{ $can['suppliers'] ? $suppliersActive : 0 }}</span>
                    <span class="sx-info-meta">Inactive: {{ $can['suppliers'] ? $suppliersInactive : 0 }}</span>
                </div>
            </div>
            @if(!empty($can['suppliers']))
                </a>
            @endif
        </div>
        <div class="col-md-3 col-sm-6">
            @if(!empty($can['products']))
                <a href="{{ route('products.index') }}" class="sx-info-box-link" title="View items">
            @endif
            <div class="sx-info-box sx-green {{ !empty($can['products']) ? 'is-clickable' : '' }}">
                <div class="sx-info-icon"><i class="fa fa-th"></i></div>
                <div class="sx-info-body">
                    <span class="sx-info-label">Items/Products</span>
                    <span class="sx-info-value">Active: {{ $can['products'] ? $productsActive : 0 }}</span>
                    <span class="sx-info-meta">Inactive: {{ $can['products'] ? $productsInactive : 0 }}</span>
                </div>
            </div>
            @if(!empty($can['products']))
                </a>
            @endif
        </div>
        <div class="col-md-3 col-sm-6">
            @if(!empty($can['sales']))
                <a href="{{ route('sales.index', ['period' => 'month']) }}" class="sx-info-box-link" title="View sales invoices">
            @endif
            <div class="sx-info-box sx-orange {{ !empty($can['sales']) ? 'is-clickable' : '' }}">
                <div class="sx-info-icon"><i class="fa fa-sun-o"></i></div>
                <div class="sx-info-body">
                    <span class="sx-info-label">Sales Invoice</span>
                    <span class="sx-info-value">{{ $can['sales'] ? $invoiceCount : 0 }}</span>
                    <span class="sx-info-meta">{{ $monthLabel }}</span>
                </div>
            </div>
            @if(!empty($can['sales']))
                </a>
            @endif
        </div>
    </div>
</div>

<div class="sx-tabs-card">
    <ul class="nav nav-tabs" role="tablist">
        <li class="active"><a href="#sx-tab-main" data-toggle="tab"><i class="fa fa-th-large"></i> 1st Tab</a></li>
        <li><a href="#sx-tab-advance" data-toggle="tab"><i class="fa fa-credit-card"></i> Advance/Deposits</a></li>
        <li><a href="#sx-tab-expiry" data-toggle="tab"><i class="fa fa-calendar"></i> Expiry Summary</a></li>
        <li><a href="#sx-tab-happy" data-toggle="tab"><i class="fa fa-smile-o"></i> Happy Hour <span class="sx-tab-new">new</span></a></li>
        <li><a href="#sx-tab-aging" data-toggle="tab"><i class="fa fa-clock-o"></i> Credit Aging Summary <span class="sx-tab-new">new</span></a></li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane active sx-tab-pane" id="sx-tab-main">
            <div class="sx-split">
                <div>
                    <div class="sx-panel-title">
                        Stock Alert
                        <span class="sx-stock-badge"><i class="fa fa-warning"></i> Stocks Running Low {{ $lowStockCount }}</span>
                    </div>
                    <table class="sx-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Category Name</th>
                                <th>Brand</th>
                                <th>Item Name</th>
                                <th>Reorder</th>
                                <th>Stock Aval.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lowStock as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ optional($item->category)->name }}</td>
                                    <td>{{ optional($item->brand)->name }}</td>
                                    <td>{{ $item->name }}</td>
                                    <td>{{ number_format((float) $item->reorder_level, 0) }}</td>
                                    <td>{{ number_format((float) $item->stock_on_hand, 0) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="sx-empty">No low-stock items</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <a href="{{ route('stock.alert') }}" class="sx-view-all">View All</a>
                </div>
                <div>
                    <div class="sx-panel-title">Top 5 Fast Moving Items</div>
                    <table class="sx-table">
                        <thead>
                            <tr>
                                <th>SN</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topMovers as $index => $row)
                                <tr>
                                    <td>{{ $index + 1 }}.</td>
                                    <td>{{ $row->name }}</td>
                                    <td>{{ optional(optional($row->product)->category)->name }}</td>
                                    <td><span class="sx-qty-badge">{{ number_format((float) $row->qty_sold, 0) }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="sx-empty">No sales this month</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="tab-pane sx-tab-pane" id="sx-tab-advance">
            <div class="sx-empty">No advances or deposits recorded.</div>
        </div>
        <div class="tab-pane sx-tab-pane" id="sx-tab-expiry">
            <div class="sx-empty">No expiring items.</div>
        </div>
        <div class="tab-pane sx-tab-pane" id="sx-tab-happy">
            <div class="sx-empty">No happy hour promotions configured.</div>
        </div>
        <div class="tab-pane sx-tab-pane" id="sx-tab-aging">
            <div class="sx-empty">No outstanding credit invoices.</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="sx-box sx-box-primary">
            <div class="sx-box-header">
                <h3 class="sx-box-title">Items/Category Sales %</h3>
                <div class="sx-box-tools">
                    <button type="button" class="sx-box-collapse" title="Collapse"><i class="fa fa-minus"></i></button>
                    <button type="button" class="sx-box-remove" title="Remove"><i class="fa fa-times"></i></button>
                </div>
            </div>
            <div class="sx-box-body">
                @if($can['sales'] && count($categorySales))
                    <div class="sx-chart-wrap">
                        <canvas id="sx-category-pie" height="240"></canvas>
                    </div>
                    <div class="sx-chart-legend" id="sx-category-legend"></div>
                @else
                    <div class="sx-none">No data found!.</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="sx-box sx-box-primary">
            <div class="sx-box-header">
                <h3 class="sx-box-title is-center">Pending Sales/Accounts Receivable</h3>
                <div class="sx-box-tools">
                    <button type="button" class="sx-box-collapse" title="Collapse"><i class="fa fa-minus"></i></button>
                    <button type="button" class="sx-box-remove" title="Remove"><i class="fa fa-times"></i></button>
                </div>
            </div>
            <div class="sx-box-body">
                <table class="sx-table">
                    <thead>
                        <tr>
                            <th>Sales Date</th>
                            <th>Due Date</th>
                            <th>Invoice No</th>
                            <th>Due Amount</th>
                            <th>Customer</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingSales as $sale)
                            <tr>
                                <td>{{ optional($sale->sale_date)->format('d-m-Y') }}</td>
                                <td>{{ optional($sale->due_date)->format('d-m-Y') }}</td>
                                <td>{{ $sale->invoice_number ?: $sale->number }}</td>
                                <td>{{ $money($sale->balance) }}</td>
                                <td>{{ optional($sale->customer)->name }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="sx-none">No data found!.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <span class="sx-view-all">View All</span>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="sx-box sx-box-success">
            <div class="sx-box-header">
                <h3 class="sx-box-title">Purchase | Sales | Expense Bar Chart</h3>
                <div class="sx-box-tools">
                    <button type="button" class="sx-box-collapse" title="Collapse"><i class="fa fa-minus"></i></button>
                    <button type="button" class="sx-box-remove" title="Remove"><i class="fa fa-times"></i></button>
                </div>
            </div>
            <div class="sx-box-body">
                <div class="sx-bar-legend">
                    <span><i style="background:#d2d6de;"></i> Purchase</span>
                    <span><i style="background:#00a65a;"></i> Sales</span>
                    <span><i style="background:#f39c12;"></i> Expense</span>
                </div>
                <div class="sx-chart-wrap">
                    <canvas id="sx-bar-chart" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="sx-box sx-box-primary">
            <div class="sx-box-header">
                <h3 class="sx-box-title">Today's Sales Summary</h3>
                <div class="sx-box-tools">
                    <button type="button" class="sx-box-collapse" title="Collapse"><i class="fa fa-minus"></i></button>
                    <button type="button" class="sx-box-remove" title="Remove"><i class="fa fa-times"></i></button>
                </div>
            </div>
            <div class="sx-box-body">
                <div class="sx-summary-heading">{{ $todayDate }} Summary Report</div>
                <table class="sx-summary-table">
                    <tr>
                        <td class="label-col">Total Sales</td>
                        <td class="amount-col">{{ $currencySymbol }}</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td class="amount-col">{{ number_format($can['sales'] ? $todaySales : 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label-col">Paid Sales</td>
                        <td class="amount-col">{{ $currencySymbol }}</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td class="amount-col">{{ number_format($can['sales'] ? $todayPaid : 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label-col">Due Sales</td>
                        <td class="amount-col">{{ $currencySymbol }}</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td class="amount-col">{{ number_format($can['sales'] ? $todayDue : 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label-col">Transactions</td>
                        <td class="amount-col">{{ $can['sales'] ? $todayCount : 0 }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('AdminLTE-2/bower_components/chart.js/Chart.js') }}"></script>
<script>
(function () {
    $(document).on('click', '.sx-box-collapse', function () {
        var box = $(this).closest('.sx-box');
        box.find('.sx-box-body').slideToggle(180);
        $(this).find('i').toggleClass('fa-minus fa-plus');
    });
    $(document).on('click', '.sx-box-remove', function () {
        $(this).closest('.sx-box').slideUp(180);
    });

    var pieEl = document.getElementById('sx-category-pie');
    var pieData = {!! json_encode($categorySales->values()) !!};
    var pieColors = ['#3c8dbc', '#00a65a', '#f39c12', '#dd4b39', '#00c0ef', '#605ca8', '#d81b60', '#39cccc'];
    if (pieEl && pieData.length && window.Chart) {
        var slices = pieData.map(function (row, i) {
            return {
                value: row.percent || row.amount,
                color: pieColors[i % pieColors.length],
                highlight: pieColors[i % pieColors.length],
                label: row.label + ': ' + row.percent + ' %'
            };
        });
        new Chart(pieEl.getContext('2d')).Pie(slices, {
            animation: false,
            tooltipTemplate: '<%=label%>'
        });
        var legend = document.getElementById('sx-category-legend');
        if (legend) {
            legend.innerHTML = pieData.map(function (row, i) {
                return '<span><i style="background:' + pieColors[i % pieColors.length] + '"></i>' + row.label + ': ' + row.percent + ' %</span>';
            }).join('');
        }
    }

    var barEl = document.getElementById('sx-bar-chart');
    var barChart = {!! json_encode($barChart) !!};
    if (barEl && barChart && window.Chart) {
        new Chart(barEl.getContext('2d')).Bar({
            labels: barChart.labels || [],
            datasets: [
                { label: 'Purchase', fillColor: '#d2d6de', strokeColor: '#d2d6de', data: barChart.purchases || [] },
                { label: 'Sales', fillColor: '#00a65a', strokeColor: '#00a65a', data: barChart.sales || [] },
                { label: 'Expense', fillColor: '#f39c12', strokeColor: '#f39c12', data: barChart.expenses || [] }
            ]
        }, {
            animation: false,
            scaleBeginAtZero: true,
            barShowStroke: false,
            tooltipFillColor: 'rgba(0,0,0,0.7)'
        });
    }
})();
</script>
@endpush
