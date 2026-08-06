@extends('layouts.master')

@section('title')
    Suspended Sales
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Suspended Sales</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Suspended Sales</h3>
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

                    @if($suspendedSales->count() > 0)
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
                                <th>Suspended At</th>
                                <th width="15%"><i class="fa fa-cog"></i></th>
                            </thead>
                            <tbody>
                                @foreach($suspendedSales as $index => $sale)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $sale->saledate ?? $sale->created_at->format('Y-m-d') }}</td>
                                        <td><span class="label label-info">{{ $sale->receiptno }}</span></td>
                                        <td>{{ $sale->total_item }}</td>
                                        <td>Ksh {{ format_uang($sale->total_harga) }}</td>
                                        <td>{{ $sale->diskon }}%</td>
                                        <td>Ksh {{ format_uang($sale->bayar) }}</td>
                                        <td>{{ $sale->user->name ?? 'N/A' }}</td>
                                        <td>{{ $sale->updated_at->format('Y-m-d H:i:s') }}</td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="{{ url('/transaksi') }}?resume={{ $sale->id_penjualan }}" 
                                                   class="btn btn-xs btn-success btn-flat" 
                                                   title="Continue this sale">
                                                    <i class="fa fa-play"></i> Continue
                                                </a>
                                                <button onclick="deleteData(`{{ route('penjualan.destroy', $sale->id_penjualan) }}`)" 
                                                        class="btn btn-xs btn-danger btn-flat" 
                                                        title="Delete this sale">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i> No suspended sales found.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function deleteData(url) {
            if (confirm('Are you sure you want to delete this suspended sale? This action cannot be undone.')) {
                $.post(url, {
                    '_token': $('[name=csrf-token]').attr('content'),
                    '_method': 'delete'
                })
                .done((response) => {
                    location.reload();
                })
                .fail((errors) => {
                    alert('Unable to delete data');
                    return;
                });
            }
        }
    </script>
@endpush


























