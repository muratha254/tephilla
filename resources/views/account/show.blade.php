@extends('layouts.master')

@section('title')
    Account Transactions - {{ $account->name }}
@endsection

@section('breadcrumb')
    @parent
    <li><a href="{{ route('account.index') }}">Accounts</a></li>
    <li class="active">Transactions</li>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
@endpush

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ $account->name }} Account Transactions</h3>
                <div class="pull-right">
                    <button onclick="exportPdf()" class="btn btn-primary btn-flat"><i class="fa fa-file-pdf-o"></i> Print PDF</button>
                </div>
            </div>
            <div class="box-body">
                <form method="GET" action="{{ route('account.show', $account->id) }}" id="filterForm">
                    <div class="row">
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" name="start_date" id="start_date" class="form-control" value="{{ $startDate }}">
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" name="end_date" id="end_date" class="form-control" value="{{ $endDate }}">
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label>&nbsp;</label><br>
                                <button type="submit" class="btn btn-primary btn-flat"><i class="fa fa-filter"></i> Filter</button>
                                <a href="{{ route('account.show', $account->id) }}" class="btn btn-default btn-flat"><i class="fa fa-refresh"></i> Reset</a>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label>Current Balance</label>
                                <h3 class="text-primary">Ksh {{ number_format($account->balance, 2) }}</h3>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="alert alert-info">
                            <h4><i class="icon fa fa-info"></i> Summary</h4>
                            <p><strong>Period:</strong> {{ date('F d, Y', strtotime($startDate)) }} to {{ date('F d, Y', strtotime($endDate)) }}</p>
                            <p><strong>Total Transactions:</strong> {{ $transactions->count() }}</p>
                            <p><strong>Total Amount:</strong> Ksh {{ number_format($totalAmount, 2) }}</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <table class="table table-stiped table-bordered table-transactions">
                            <thead>
                                <th width="5%">#</th>
                                <th>Date</th>
                                <th>Receipt No</th>
                                <th>Total Items</th>
                                <th>Total Price</th>
                                <th>Discount</th>
                                <th>Amount</th>
                                <th>Cashier</th>
                            </thead>
                            <tbody>
                                @forelse($transactions as $index => $transaction)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ date('Y-m-d H:i', strtotime($transaction->created_at)) }}</td>
                                    <td>{{ $transaction->receiptno }}</td>
                                    <td>{{ $transaction->total_item }}</td>
                                    <td>Ksh {{ number_format($transaction->total_harga, 2) }}</td>
                                    <td>{{ $transaction->diskon }}%</td>
                                    <td>Ksh {{ number_format($transaction->bayar, 2) }}</td>
                                    <td>{{ $transaction->user->name ?? 'N/A' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center">No transactions found for the selected period.</td>
                                </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="6" class="text-right">Total:</th>
                                    <th>Ksh {{ number_format($totalAmount, 2) }}</th>
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
    function exportPdf() {
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();
        var url = '{{ route("account.export-pdf", $account->id) }}?start_date=' + startDate + '&end_date=' + endDate;
        window.open(url, '_blank');
    }
</script>
@endpush









