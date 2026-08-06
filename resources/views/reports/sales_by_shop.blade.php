@extends('layouts.master')

@section('title')
    Sales by Shop
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Sales by Shop</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Sales Grouped by Shop</h3>
                <div class="box-tools">
                    <form action="{{ route('reports.sales-by-shop.export-pdf') }}" method="GET" class="form-inline">
                        <input type="date" name="start_date" value="{{ $start }}" class="form-control input-sm" required>
                        <input type="date" name="end_date" value="{{ $end }}" class="form-control input-sm" required>
                        <button type="submit" class="btn btn-default btn-sm"><i class="fa fa-file-pdf-o"></i> Export PDF</button>
                    </form>
                </div>
            </div>
            <div class="box-body">
                <form class="form-inline" method="GET" style="margin-bottom: 15px;">
                    <div class="form-group">
                        <label>From</label>
                        <input type="date" name="start_date" value="{{ $start }}" class="form-control input-sm" required>
                    </div>
                    <div class="form-group" style="margin-left: 10px;">
                        <label>To</label>
                        <input type="date" name="end_date" value="{{ $end }}" class="form-control input-sm" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="margin-left: 10px;"><i class="fa fa-filter"></i> Filter</button>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Shop</th>
                                <th class="text-right">Total Qty</th>
                                <th class="text-right">Total Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $row['shop_name'] }}</td>
                                <td class="text-right">{{ number_format($row['total_qty'], 2) }}</td>
                                <td class="text-right">Ksh {{ number_format($row['total_amount'], 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">No data for the selected period.</td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2" class="text-right">Totals</th>
                                <th class="text-right">{{ number_format($totalQty, 2) }}</th>
                                <th class="text-right">Ksh {{ number_format($totalAmount, 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection










