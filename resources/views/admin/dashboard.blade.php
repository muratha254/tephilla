@extends('layouts.master')

@section('title')
    Dashboard
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Dashboard</li>
@endsection

@section('content')
<!-- Small boxes (Stat box) -->
<div class="row">
    <div class="col-lg-3 col-md-6 col-sm-6">
        <!-- small box -->
        <div class="small-box bg-primary">
            <div class="inner">
                <h3>{{ $outOfStockCount }}</h3>

                <p>Products Out of Stock</p>
            </div>
            <div class="icon">
                <i class="fa fa-exclamation-triangle"></i>
            </div>
            <a href="{{ route('produk.index') }}" class="small-box-footer">Restock <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <!-- ./col --><!-- visit "codeastro" for more projects! -->
    <div class="col-lg-3 col-md-6 col-sm-6">
        <!-- small box -->
        <div class="small-box bg-purple">
            <div class="inner">
                <h3>{{ $produk }}</h3>

                <p>Total Products</p>
            </div>
            <div class="icon">
                <i class="fa fa-cubes"></i>
            </div>
            <a href="{{ route('produk.index') }}" class="small-box-footer">View <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <!-- ./col -->
    <div class="col-lg-3 col-md-6 col-sm-6">
        <!-- small box -->
        <div class="small-box bg-olive">
            <div class="inner">
                <h3>{{ $supplier }}</h3>

                <p>Total Suppliers</p>
            </div>
            <div class="icon">
                <i class="fa fa-truck"></i>
            </div>
            <a href="{{ route('supplier.index') }}" class="small-box-footer">View <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <!-- ./col -->
    <div class="col-lg-3 col-md-6 col-sm-6">
        <!-- small box -->
        <div class="small-box bg-blue">
            <div class="inner">
                <h3>{{ $penjualan }}</h3>

                <p>Sales</p>
            </div>
            <div class="icon">
                <i class="fa fa-shopping-cart"></i>
            </div>
            <a href="{{ route('penjualan.index') }}" class="small-box-footer">View <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>
<!-- /.row -->

<div class="row">
    <div class="col-lg-4 col-md-4 col-sm-6">
        <!-- small box -->
        <div class="small-box bg-green">
            <div class="inner">
                <h3>{{ $pengeluaran }}</h3>

                <p>Total Expenses</p>
            </div>
            <div class="icon">
                <i class="fa fa-dollar"></i>
            </div>
            <a href="{{ route('pengeluaran.index') }}" class="small-box-footer">View <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <!-- ./col -->

    <div class="col-lg-4 col-md-4 col-sm-6">
        <!-- small box -->
        <div class="small-box bg-red">
            <div class="inner">
                <h3>{{ $pembelian }}</h3>

                <p>Total Purchases</p>
            </div>
            <div class="icon">
                <i class="fa fa-dollar"></i>
            </div>
            <a href="{{ route('pembelian.index') }}" class="small-box-footer">View <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <!-- ./col -->
    <!-- visit "codeastro" for more projects! -->
    <div class="col-lg-4 col-md-4 col-sm-6">
        <!-- small box -->
        <div class="small-box bg-orange">
            <div class="inner">
                <h3>{{ $pembelian - $pengeluaran }}</h3>

                <p>Net Spend</p>
            </div>
            <div class="icon">
                <i class="fa fa-balance-scale"></i>
            </div>
            <a href="{{ route('pengeluaran.index') }}" class="small-box-footer">Details <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-exclamation-triangle"></i> Out of Stock Products ({{ $outOfStockCount }})</h3>
                <div class="box-tools">
                    <a href="{{ route('produk.index') }}" class="btn btn-danger btn-sm btn-flat">
                        <i class="fa fa-list"></i> Go to Products
                    </a>
                </div>
            </div>
            <div class="box-body table-responsive">
                @if($outOfStockCount === 0)
                    <p class="text-muted">Great news! All products currently have stock.</p>
                @else
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Shop</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($outOfStockProducts as $index => $item)
                        <tr class="danger">
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $item->nama_produk }}</td>
                            <td>{{ $item->shop->shop_name ?? 'N/A' }}</td>
                            <td><span class="label label-danger">{{ number_format($item->stok, 0) }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($outOfStockCount > $outOfStockProducts->count())
                    <p class="text-muted"><em>{{ $outOfStockCount - $outOfStockProducts->count() }} more item(s) are out of stock. Visit the products page for the full list.</em></p>
                @endif
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Main row -->
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Income Chart {{ tanggal_indonesia($tanggal_awal, false) }} - {{ tanggal_indonesia($tanggal_akhir, false) }}</h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="chart">
                            <!-- Sales Chart Canvas -->
                            <canvas id="salesChart" style="height: 280px;"></canvas>
                        </div>
                        <!-- /.chart-responsive -->
                    </div>
                </div>
                <!-- /.row -->
            </div>
        </div>
        <!-- /.box -->
    </div>
    <!-- /.col -->
</div>
<!-- /.row (main row) -->
@endsection

@push('scripts')
<!-- ChartJS -->
<script src="{{ asset('AdminLTE-2/bower_components/chart.js/Chart.js') }}"></script>
<script>
$(function() {
    // Get context with jQuery - using jQuery's .get() method.
    var salesChartCanvas = $('#salesChart').get(0).getContext('2d');
    // This will get the first returned node in the jQuery collection.
    var salesChart = new Chart(salesChartCanvas);

    var salesChartData = {
        labels: {{ json_encode($data_tanggal) }},
        datasets: [
            {
                label: 'Pendapatan',
                fillColor           : 'rgba(60,141,188,0.9)',
                strokeColor         : 'rgba(60,141,188,0.8)',
                pointColor          : '#3b8bba',
                pointStrokeColor    : 'rgba(60,141,188,1)',
                pointHighlightFill  : '#fff',
                pointHighlightStroke: 'rgba(60,141,188,1)',
                data: {{ json_encode($data_pendapatan) }}
            }
        ]
    };

    var salesChartOptions = {
        pointDot : false,
        responsive : true
    };

    salesChart.Line(salesChartData, salesChartOptions);
});
</script>
@endpush