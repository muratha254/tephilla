@extends('layouts.master')

@section('title')
    Supplier Withdrawal
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Supplier Withdrawal</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Supplier Withdrawal</h3>
                <div class="box-tools">
                    <a href="{{ route('supplier.index') }}" class="btn btn-default btn-sm">
                        <i class="fa fa-list"></i> All Suppliers
                    </a>
                </div>
            </div>
            <div class="box-body table-responsive">
                <table id="supplier-withdrawal-table" class="table table-stiped table-bordered table-hover">
                    <thead>
                        <th width="5%">#</th>
                        <th>Name</th>
                        <th>Telephone</th>
                        <th>Address</th>
                        <th width="15%"><i class="fa fa-cog"></i></th>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let table;

    $(function () {
        table = $('#supplier-withdrawal-table').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: {
                url: '{{ route('supplier.data') }}',
            },
            columns: [
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'nama'},
                {data: 'telepon'},
                {data: 'alamat'},
                {
                    data: 'id_supplier',
                    searchable: false,
                    sortable: false,
                    render: function(data, type, row) {
                        return '<a href="' + '{{ url("/supplier") }}/' + data + '/withdrawal" class="btn btn-xs btn-warning btn-flat" title="Withdraw Items"><i class="fa fa-arrow-down"></i> Withdraw</a>';
                    }
                },
            ]
        });
    });
</script>
@endpush










