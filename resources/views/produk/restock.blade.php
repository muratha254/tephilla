@extends('layouts.master')

@section('title')
    Restock Products
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Restock Products</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Restock Products</h3>
                <div class="box-tools">
                    <div class="input-group input-group-sm" style="width: 300px;">
                        <input type="text" id="restock-search" class="form-control pull-right" placeholder="Search by name or code">
                        <div class="input-group-btn">
                            <button type="button" class="btn btn-default"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="box-body table-responsive">
                <table id="restock-table" class="table table-stiped table-bordered">
                    <thead>
                        <th width="5%">#</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Current Stock</th>
                        <th>Add Stock</th>
                        <th>Action</th>
                    </thead>
                    <tbody>
                        @foreach($products as $key => $item)
                        <tr>
                            <td>{{ $key+1 }}</td>
                            <td><span class="label label-success">{{ $item->item_code }}</span></td>
                            <td>{{ $item->nama_produk }}</td>
                            <td>{{ format_uang($item->stok) }}</td>
                            <td>
                                <form id="form-{{ $item->id_produk }}" class="form-inline">
                                    @csrf
                                    <input type="hidden" name="id_produk" value="{{ $item->id_produk }}">
                                    <input type="number" name="additional_stock" class="form-control input-sm" min="1" style="width: 100px" required>
                                    <input type="date" name="date_in" class="form-control input-sm" value="{{ date('Y-m-d') }}" required>
                                </form>
                            </td>
                            <td>
                                <button onclick="submitRestock({{ $item->id_produk }})" class="btn btn-info btn-sm btn-flat"><i class="fa fa-plus"></i> Restock</button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function submitRestock(id) {
        let form = $('#form-' + id);
        let formData = form.serialize();

        $.post('{{ route('produk.restock') }}', formData)
            .done(response => {
                alert('Stock updated successfully');
                location.reload();
            })
            .fail(errors => {
                alert('Unable to update stock');
                return;
            });
    }

    $(function () {
        $('#restock-search').on('keyup', function () {
            const query = $(this).val().toLowerCase();
            $('#restock-table tbody tr').each(function () {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(query) > -1);
            });
        });
    });
</script>
@endpush
