@extends('layouts.master')

@section('title')
    List of Shops
@endsection

@section('breadcrumb')
    @parent
    <li class="active">List of Shop</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <button onclick="addForm('{{ route('shop.store') }}')" class="btn btn-success btn-flat"><i class="fa fa-plus-circle"></i> Add New Shop</button>
                <button type="button" onclick="deleteSelected('{{ route('shop.delete_selected') }}')" class="btn btn-danger btn-flat"><i class="fa fa-trash"></i> Delete Selected</button>
            </div>
            <div class="box-body table-responsive">
                <form action="" method="post" class="form-member">
                    @csrf
                    <table id="shop-table" class="table table-stiped table-bordered table-hover">
                        <thead>
                            <th width="5%">
                                <input type="checkbox" name="select_all" id="select_all">
                            </th>
                            <th width="5%">#</th>
                            <th>Code</th>
                            <th>Name</th>
                            
                            <th width="15%"><i class="fa fa-cog"></i></th>
                        </thead>
                    </table>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- visit "codeastro" for more projects! -->
@includeIf('shop.form')
@endsection

@push('scripts')
<script>
    let table;

    $(function () {
        table = $('#shop-table').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: {
                url: '{{ route('shop.data') }}',
            },
            columns: [
                {data: 'select_all', searchable: false, sortable: false},
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'shop_code'},
                {data: 'shop_name'},
               
                {data: 'action', searchable: false, sortable: false},
            ]
        });

        $('#modal-form').validator().on('submit', function (e) {
            if (! e.preventDefault()) {
                $.post($('#modal-form form').attr('action'), $('#modal-form form').serialize())
                    .done((response) => {
                        $('#modal-form').modal('hide');
                        table.ajax.reload();
                    })
                    .fail((errors) => {
                        alert('Unable to save data');
                        return;
                    });
            }
        });

        $('#select_all').on('click', function () {
            $('input[name="id_shop[]"]').prop('checked', this.checked);
        });
    });

    function addForm(url) {
        $('#modal-form').modal('show');
        $('#modal-form .modal-title').text('Add Shop');

        $('#modal-form form')[0].reset();
        $('#modal-form form').attr('action', url);
        $('#modal-form [name=_method]').val('post');
        $('#modal-form [name=shop_name]').focus();
    }

    function editForm(url) {
        $('#modal-form').modal('show');
        $('#modal-form .modal-title').text('Edit Shop');

        $('#modal-form form')[0].reset();
        $('#modal-form form').attr('action', url);
        $('#modal-form [name=_method]').val('put');
        $('#modal-form [name=shop_code]').focus();

        $.get(url)
            .done((response) => {
                $('#modal-form [name=shop_code]').val(response.shop_code);
                $('#modal-form [name=shop_name]').val(response.shop_name);
                //$('#modal-form [name=alamat]').val(response.alamat);
            })
            .fail((errors) => {
                alert('Unable to display data');
                return;
            });
    }

    function deleteData(url) {
        if (confirm('Are you sure you want to delete selected data?')) {
            $.post(url, {
                    '_token': $('[name=csrf-token]').attr('content'),
                    '_method': 'delete'
                })
                .done((response) => {
                    table.ajax.reload();
                })
                .fail((errors) => {
                    alert('Unable to delete data');
                    return;
                });
        }
    }

    function deleteSelected(url) {
        var selected = $('input[name="id_shop[]"]:checked');
        if (selected.length === 0) {
            alert('Select at least one shop to delete.');
            return;
        }
        if (!confirm('Delete ' + selected.length + ' selected shop(s)?')) {
            return;
        }
        $.post(url, $('.form-member').serialize())
            .done(function () {
                $('#select_all').prop('checked', false);
                table.ajax.reload();
            })
            .fail(function () {
                alert('Unable to delete selected shops.');
            });
    }

    
</script>
@endpush