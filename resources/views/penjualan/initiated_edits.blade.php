@extends('layouts.master')

@section('title')
    Initiated Sale Edits
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Initiated Sale Edits</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Initiated Sale Edits</h3>
                    <div class="box-tools">
                        <a href="{{ route('transaksi.baru') }}" class="btn btn-primary btn-flat"><i class="fa fa-plus-circle"></i> New Transaction</a>
                        <a href="{{ route('transaksi.index') }}" class="btn btn-info btn-flat"><i class="fa fa-cart-arrow-down"></i> Active Sale</a>
                    </div>
                </div>

                <div class="box-body table-responsive">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                            {{ session('error') }}
                        </div>
                    @endif

                    <p class="text-muted">
                        Sales that are <strong>waiting for you to finish in POS</strong> after an admin used <em>Edit sale</em> or someone used
                        <a href="{{ route('transaksi.initiate_edit_form') }}">Find sale to edit (receipt)</a>.
                        They stay on this list until you <strong>complete the transaction</strong> in POS (then they move off automatically).
                        Click <strong>Continue edit</strong> to reopen the sale in POS.
                    </p>

                    @if($initiatedSales->count() > 0)
                        <table class="table table-stiped table-bordered table-hover">
                            <thead>
                                <th width="5%">#</th>
                                <th>Date</th>
                                <th>Receipt No</th>
                                <th>Quantity</th>
                                <th>Total Price</th>
                                <th>Discount</th>
                                <th>Total Pay</th>
                                <th>Cashier</th>
                                <th>Initiated At</th>
                                <th width="15%"><i class="fa fa-cog"></i></th>
                            </thead>
                            <tbody>
                                @foreach($initiatedSales as $index => $sale)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $sale->saledate ?? $sale->created_at->format('Y-m-d') }}</td>
                                        <td><span class="label label-info">{{ $sale->receiptno }}</span></td>
                                        <td>{{ $sale->total_item }}</td>
                                        <td>Ksh {{ format_uang($sale->total_harga) }}</td>
                                        <td>{{ $sale->getDiscountDisplayLabel() }}</td>
                                        <td>Ksh {{ format_uang($sale->bayar) }}</td>
                                        <td>{{ $sale->user->name ?? 'N/A' }}</td>
                                        <td>
                                            @if(($sale->status ?? '') === 'edit_initiated')
                                                <span class="label label-warning">Edit queued</span>
                                            @else
                                                <span class="label label-info">In POS</span>
                                            @endif
                                        </td>
                                        <td>{{ $sale->edit_initiated_at ? $sale->edit_initiated_at->format('Y-m-d H:i') : $sale->updated_at->format('Y-m-d H:i') }}</td>
                                        <td>
                                            <a href="{{ url('/transaksi') }}?resume={{ $sale->id_penjualan }}" 
                                               class="btn btn-xs btn-success btn-flat" 
                                               title="Continue editing this sale in POS">
                                                <i class="fa fa-edit"></i> Continue edit
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i> No initiated sale edits.
                            You can start one with <a href="{{ route('transaksi.initiate_edit_form') }}" class="alert-link">Find sale to edit (receipt)</a>
                            (your own receipts), or an admin can initiate from the Sales List.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
