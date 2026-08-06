@extends('layouts.master')

@section('title')
    Accounts
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Accounts</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Payment Accounts</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    @foreach($accounts as $account)
                    <div class="col-lg-4">
                        <div class="small-box bg-{{ $account->name == 'Cash' ? 'green' : ($account->name == 'Mpesa' ? 'blue' : 'yellow') }}">
                            <div class="inner">
                                <h3>Ksh {{ number_format($account->balance, 2) }}</h3>
                                <p>{{ $account->name }} Account</p>
                            </div>
                            <div class="icon">
                                <i class="fa fa-{{ $account->name == 'Cash' ? 'money' : ($account->name == 'Mpesa' ? 'mobile' : 'credit-card') }}"></i>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                
                <div class="row">
                    <div class="col-lg-12">
                        <div class="alert alert-info">
                            <h4><i class="icon fa fa-info"></i> Total Balance</h4>
                            <h3>Ksh {{ number_format($totalBalance, 2) }}</h3>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <table class="table table-stiped table-bordered table-account">
                            <thead>
                                <th width="5%">#</th>
                                <th>Account Name</th>
                                <th>Type</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Description</th>
                                <th width="15%"><i class="fa fa-cog"></i></th>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let table;

    $(function () {
        table = $('.table-account').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: {
                url: '{{ route('account.data') }}',
            },
            columns: [
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'name'},
                {data: 'type'},
                {data: 'balance'},
                {data: 'status'},
                {data: 'description'},
                {data: 'aksi', searchable: false, sortable: false},
            ],
            dom: 'Brt',
            bSort: false,
            paginate: false
        });
    });

    function viewAccount(url) {
        window.location.href = url;
    }
</script>
@endpush





