@extends('layouts.master')

@section('title')
    User List
@endsection

@section('breadcrumb')
    @parent
    <li class="active">User List</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                @php($u = auth()->user())
                @if($u && (($u->role ?? '') === 'admin' || $u->can_create))
                <button onclick="addForm('{{ route('user.store') }}')" class="btn btn-success btn-flat"><i class="fa fa-plus-circle"></i> Add New System User</button>
                @endif
            </div>
            <div class="box-body table-responsive">
                <table class="table table-stiped table-bordered table-hover">
                    <thead>
                        <th width="5%">#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Permissions</th>
                        <th width="15%"><i class="fa fa-cog"></i></th>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

@includeIf('user.form')
@endsection

@push('scripts')
<script>
    let table;

    $(function () {
        table = $('.table').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: {
                url: '{{ route('user.data') }}',
            },
            columns: [
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'name'},
                {data: 'email'},
                {data: 'role'},
                {data: 'permissions'},
                {data: 'aksi', searchable: false, sortable: false},
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

        // Handle "Check All Permissions" checkbox
        $(document).on('change', '#check-all-permissions', function() {
            var isChecked = $(this).prop('checked');
            // Get all permission checkboxes (excluding the check-all checkbox itself)
            $('#modal-form input[type="checkbox"][name^="can_"], #modal-form input[type="checkbox"][name^="inv_"], #modal-form input[type="checkbox"][name^="sal_"], #modal-form input[type="checkbox"][name^="exp_"], #modal-form input[type="checkbox"][name^="rep_"], #modal-form input[type="checkbox"][name^="con_"], #modal-form input[type="checkbox"][name^="pay_"]').prop('checked', isChecked);
        });

        // Update "Check All" checkbox state when individual checkboxes change
        $(document).on('change', '#modal-form input[type="checkbox"][name^="can_"], #modal-form input[type="checkbox"][name^="inv_"], #modal-form input[type="checkbox"][name^="sal_"], #modal-form input[type="checkbox"][name^="exp_"], #modal-form input[type="checkbox"][name^="rep_"], #modal-form input[type="checkbox"][name^="con_"], #modal-form input[type="checkbox"][name^="pay_"]', function() {
            var totalCheckboxes = $('#modal-form input[type="checkbox"][name^="can_"], #modal-form input[type="checkbox"][name^="inv_"], #modal-form input[type="checkbox"][name^="sal_"], #modal-form input[type="checkbox"][name^="exp_"], #modal-form input[type="checkbox"][name^="rep_"], #modal-form input[type="checkbox"][name^="con_"], #modal-form input[type="checkbox"][name^="pay_"]').length;
            var checkedCheckboxes = $('#modal-form input[type="checkbox"][name^="can_"]:checked, #modal-form input[type="checkbox"][name^="inv_"]:checked, #modal-form input[type="checkbox"][name^="sal_"]:checked, #modal-form input[type="checkbox"][name^="exp_"]:checked, #modal-form input[type="checkbox"][name^="rep_"]:checked, #modal-form input[type="checkbox"][name^="con_"]:checked, #modal-form input[type="checkbox"][name^="pay_"]:checked').length;
            $('#check-all-permissions').prop('checked', totalCheckboxes === checkedCheckboxes);
        });
    });

    function addForm(url) {
        $('#modal-form').modal('show');
        $('#modal-form .modal-title').text('Add User');

        $('#modal-form form')[0].reset();
        $('#modal-form form').attr('action', url);
        $('#modal-form [name=_method]').val('post');
        $('#modal-form [name=name]').focus();

        $('#password, #password_confirmation').attr('required', true);
        $('#check-all-permissions').prop('checked', false);
    }

    function editForm(url) {
        $('#modal-form').modal('show');
        $('#modal-form .modal-title').text('Edit User');

        $('#modal-form form')[0].reset();
        $('#modal-form form').attr('action', url);
        $('#modal-form [name=_method]').val('put');
        $('#modal-form [name=name]').focus();

        $('#password, #password_confirmation').attr('required', false);
        $('#check-all-permissions').prop('checked', false);

        $.get(url)
            .done((response) => {
                $('#modal-form [name=name]').val(response.name);
                $('#modal-form [name=email]').val(response.email);
                $('#modal-form [name=role]').val(response.role || 'cashier');
                $('#modal-form [name=can_create]').prop('checked', !!response.can_create);
                $('#modal-form [name=can_read]').prop('checked', response.can_read !== 0 && response.can_read !== false);
                $('#modal-form [name=can_update]').prop('checked', !!response.can_update);
                $('#modal-form [name=can_delete]').prop('checked', !!response.can_delete);
                $('#modal-form [name=inv_create]').prop('checked', !!response.inv_create);
                $('#modal-form [name=inv_read]').prop('checked', response.inv_read !== 0 && response.inv_read !== false);
                $('#modal-form [name=inv_update]').prop('checked', !!response.inv_update);
                $('#modal-form [name=inv_delete]').prop('checked', !!response.inv_delete);
                $('#modal-form [name=sal_create]').prop('checked', !!response.sal_create);
                $('#modal-form [name=sal_read]').prop('checked', response.sal_read !== 0 && response.sal_read !== false);
                $('#modal-form [name=sal_update]').prop('checked', !!response.sal_update);
                $('#modal-form [name=sal_delete]').prop('checked', !!response.sal_delete);
                $('#modal-form [name=exp_create]').prop('checked', !!response.exp_create);
                $('#modal-form [name=exp_read]').prop('checked', response.exp_read !== 0 && response.exp_read !== false);
                $('#modal-form [name=exp_update]').prop('checked', !!response.exp_update);
                $('#modal-form [name=exp_delete]').prop('checked', !!response.exp_delete);
                $('#modal-form [name=rep_create]').prop('checked', !!response.rep_create);
                $('#modal-form [name=rep_read]').prop('checked', response.rep_read !== 0 && response.rep_read !== false);
                $('#modal-form [name=rep_update]').prop('checked', !!response.rep_update);
                $('#modal-form [name=rep_delete]').prop('checked', !!response.rep_delete);
                $('#modal-form [name=con_create]').prop('checked', !!response.con_create);
                $('#modal-form [name=con_read]').prop('checked', response.con_read !== 0 && response.con_read !== false);
                $('#modal-form [name=con_update]').prop('checked', !!response.con_update);
                $('#modal-form [name=con_delete]').prop('checked', !!response.con_delete);
                $('#modal-form [name=pay_create]').prop('checked', !!response.pay_create);
                $('#modal-form [name=pay_read]').prop('checked', response.pay_read !== 0 && response.pay_read !== false);
                $('#modal-form [name=pay_update]').prop('checked', !!response.pay_update);
                $('#modal-form [name=pay_delete]').prop('checked', !!response.pay_delete);
                
                // Update "Check All" checkbox state after loading user data
                setTimeout(function() {
                    var totalCheckboxes = $('#modal-form input[type="checkbox"][name^="can_"], #modal-form input[type="checkbox"][name^="inv_"], #modal-form input[type="checkbox"][name^="sal_"], #modal-form input[type="checkbox"][name^="exp_"], #modal-form input[type="checkbox"][name^="rep_"], #modal-form input[type="checkbox"][name^="con_"], #modal-form input[type="checkbox"][name^="pay_"]').length;
                    var checkedCheckboxes = $('#modal-form input[type="checkbox"][name^="can_"]:checked, #modal-form input[type="checkbox"][name^="inv_"]:checked, #modal-form input[type="checkbox"][name^="sal_"]:checked, #modal-form input[type="checkbox"][name^="exp_"]:checked, #modal-form input[type="checkbox"][name^="rep_"]:checked, #modal-form input[type="checkbox"][name^="con_"]:checked, #modal-form input[type="checkbox"][name^="pay_"]:checked').length;
                    $('#check-all-permissions').prop('checked', totalCheckboxes === checkedCheckboxes);
                }, 100);
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
</script>
@endpush