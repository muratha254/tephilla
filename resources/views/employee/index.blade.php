@extends('layouts.master')

@section('title')
    Employee List
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Employee List</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                @php($u = auth()->user())
                @if($u && ($u->hasModulePermission('payroll', 'create') || $u->hasRole('admin')))
                <button onclick="addForm('{{ route('employee.store') }}')" class="btn btn-success btn-flat"><i class="fa fa-plus-circle"></i> Add New Employee</button>
                @endif
            </div>
            <div class="box-body table-responsive">
                <table class="table table-stiped table-bordered table-hover">
                    <thead>
                        <th width="5%">#</th>
                        <th>Name</th>
                        <th>ID Number</th>
                        <th>KRA PIN</th>
                        <th>NSSF Number</th>
                        <th>Gross Salary</th>
                        <th>Advance Salary</th>
                        <th>Status</th>
                        <th width="20%"><i class="fa fa-cog"></i></th>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

@includeIf('employee.form')
@includeIf('employee.advance_form')
@includeIf('employee.payment_form')
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
                url: '{{ route('employee.data') }}',
            },
            columns: [
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'name'},
                {data: 'id_number'},
                {data: 'kra_pin'},
                {data: 'nssf_number'},
                {data: 'gross_salary'},
                {data: 'advance_salary'},
                {data: 'status_badge'},
                {data: 'aksi', searchable: false, sortable: false},
            ]
        });

        $('#modal-form').validator().on('submit', function (e) {
            if (! e.preventDefault()) {
                $.ajax({
                    url: $('#modal-form form').attr('action'),
                    type: $('#modal-form form').find('input[name="_method"]').val() || 'POST',
                    data: $('#modal-form form').serialize(),
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                })
                .done((response) => {
                    $('#modal-form').modal('hide');
                    table.ajax.reload();
                    alert('Data saved successfully');
                })
                .fail((errors) => {
                    if (errors.responseJSON && errors.responseJSON.errors) {
                        alert('Validation errors: ' + JSON.stringify(errors.responseJSON.errors));
                    } else {
                        alert('Unable to save data');
                    }
                    return;
                });
            }
        });
    });

    function addForm(url) {
        $('#modal-form').modal('show');
        $('#modal-form .modal-title').text('Add Employee');

        $('#modal-form form')[0].reset();
        $('#modal-form form').attr('action', url);
        $('#modal-form [name=_method]').val('post');
        $('#modal-form [name=name]').focus();
    }

    function editForm(url) {
        $('#modal-form').modal('show');
        $('#modal-form .modal-title').text('Edit Employee');

        $('#modal-form form')[0].reset();
        $('#modal-form form').attr('action', url);
        $('#modal-form [name=_method]').val('put');
        $('#modal-form [name=name]').focus();

        $.get(url)
            .done((response) => {
                const data = response.data;
                $('#modal-form [name=name]').val(data.name);
                $('#modal-form [name=employer_number]').val(data.employer_number);
                $('#modal-form [name=id_number]').val(data.id_number);
                $('#modal-form [name=kra_pin]').val(data.kra_pin);
                $('#modal-form [name=nssf_number]').val(data.nssf_number);
                $('#modal-form [name=email]').val(data.email);
                $('#modal-form [name=phone]').val(data.phone);
                $('#modal-form [name=address]').val(data.address);
                $('#modal-form [name=date_of_birth]').val(data.date_of_birth);
                $('#modal-form [name=date_of_employment]').val(data.date_of_employment);
                $('#modal-form [name=status]').val(data.status);
                $('#modal-form [name=gross_salary]').val(data.gross_salary);
                $('#modal-form [name=advance_salary]').val(data.advance_salary || 0);
            })
            .fail((errors) => {
                alert('Unable to retrieve data');
                return;
            });
    }

    function deleteData(url) {
        if (confirm('Are you sure you want to delete this data?')) {
            $.post(url, {
                    '_token': $('[name=csrf-token]').attr('content'),
                    '_method': 'delete'
                })
                .done((response) => {
                    table.ajax.reload();
                    alert('Data deleted successfully');
                })
                .fail((errors) => {
                    alert('Unable to delete data');
                    return;
                });
        }
    }

    function addAdvanceSalary(employeeId, employeeName, currentAdvance) {
        $('#modal-advance').modal('show');
        $('#modal-advance .modal-title').text('Add Advance Salary - ' + employeeName);
        
        $('#modal-advance form')[0].reset();
        const routeUrl = '{{ url('/employee') }}/' + employeeId + '/add-advance';
        $('#modal-advance form').attr('action', routeUrl);
        $('#modal-advance [name=employee_id]').val(employeeId);
        $('#modal-advance [name=current_advance]').val(currentAdvance || 0);
        $('#modal-advance #current-advance-display').text('KES ' + parseFloat(currentAdvance || 0).toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#modal-advance [name=request_date]').val('{{ date('Y-m-d') }}');
        $('#modal-advance [name=amount]').focus();
    }

    function recordAdvancePayment(employeeId, employeeName, outstandingAdvance) {
        $('#modal-payment').modal('show');
        $('#modal-payment .modal-title').text('Record Payment - ' + employeeName);
        
        $('#modal-payment form')[0].reset();
        const routeUrl = '{{ url('/employee') }}/' + employeeId + '/record-payment';
        $('#modal-payment form').attr('action', routeUrl);
        $('#payment_employee_id').val(employeeId);
        $('#outstanding-advance-display').text('KES ' + parseFloat(outstandingAdvance || 0).toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#payment_date').val('{{ date('Y-m-d') }}');
        $('#payment_amount').attr('max', outstandingAdvance || 0);
        $('#payment_amount').focus();
    }

    // Handle advance salary form submission
    $(document).ready(function() {
        // Handle button click
        $(document).on('click', '#btn-save-advance', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Button clicked');
            
            const $btn = $(this);
            const originalText = $btn.html();
            
            // Disable button and show loading state
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');
            
            // Basic validation
            const amount = $('#modal-advance [name=amount]').val();
            const requestDate = $('#modal-advance [name=request_date]').val();
            
            if (!amount || parseFloat(amount) <= 0) {
                alert('Please enter a valid advance amount');
                $btn.prop('disabled', false).html(originalText);
                return false;
            }
            
            if (!requestDate) {
                alert('Please select a request date');
                $btn.prop('disabled', false).html(originalText);
                return false;
            }
            
            const form = $('#modal-advance form');
            const formAction = form.attr('action');
            
            console.log('Form action:', formAction);
            
            if (!formAction) {
                alert('Error: Form action not set');
                $btn.prop('disabled', false).html(originalText);
                return false;
            }
            
            $.ajax({
                url: formAction,
                type: 'POST',
                dataType: 'json',
                data: form.serialize(),
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                timeout: 30000
            })
            .done(function(response) {
                console.log('Success:', response);
                // Re-enable button
                $btn.prop('disabled', false).html(originalText);
                
                $('#modal-advance').modal('hide');
                
                // Reload table if it exists
                if (typeof table !== 'undefined' && table) {
                    table.ajax.reload(null, false);
                }
                
                // Extract message from response
                let message = 'Advance salary added successfully';
                if (response && response.message) {
                    message = response.message;
                }
                alert(message);
            })
            .fail(function(xhr, status, error) {
                console.log('Error:', status, error, xhr);
                // Re-enable button
                $btn.prop('disabled', false).html(originalText);
                
                let errorMessage = 'Unable to save advance salary';
                
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                } else if (xhr.responseJSON) {
                    if (xhr.responseJSON.errors) {
                        let errorMsg = 'Validation errors:\n';
                        $.each(xhr.responseJSON.errors, function(key, value) {
                            if (Array.isArray(value)) {
                                errorMsg += value[0] + '\n';
                            } else {
                                errorMsg += value + '\n';
                            }
                        });
                        errorMessage = errorMsg;
                    } else if (xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                } else if (xhr.responseText) {
                    // Try to parse response text if it's JSON
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMessage = response.message;
                        }
                    } catch(e) {
                        console.error('Error parsing response:', e);
                    }
                }
                
                alert(errorMessage);
            })
            .always(function() {
                // Ensure button is re-enabled
                $btn.prop('disabled', false).html(originalText);
            });
            
            return false;
        });
        
        // Also prevent form submission
        $(document).on('submit', '#modal-advance form', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $('#btn-save-advance').click();
            return false;
        });

        // Handle payment button click
        $(document).on('click', '#btn-save-payment', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Payment button clicked');
            
            const $btn = $(this);
            const originalText = $btn.html();
            
            // Disable button and show loading state
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');
            
            // Basic validation
            const amount = $('#payment_amount').val();
            const paymentDate = $('#payment_date').val();
            const outstandingText = $('#outstanding-advance-display').text();
            const outstanding = parseFloat(outstandingText.replace(/[KES,\s]/g, '')) || 0;
            
            if (!amount || parseFloat(amount) <= 0) {
                alert('Please enter a valid payment amount');
                $btn.prop('disabled', false).html(originalText);
                return false;
            }
            
            if (parseFloat(amount) > outstanding) {
                alert('Payment amount cannot exceed outstanding advance salary of KES ' + outstanding.toLocaleString('en-US', {minimumFractionDigits: 2}));
                $btn.prop('disabled', false).html(originalText);
                return false;
            }
            
            if (!paymentDate) {
                alert('Please select a payment date');
                $btn.prop('disabled', false).html(originalText);
                return false;
            }
            
            const form = $('#modal-payment form');
            const formAction = form.attr('action');
            
            console.log('Payment form action:', formAction);
            
            if (!formAction) {
                alert('Error: Form action not set');
                $btn.prop('disabled', false).html(originalText);
                return false;
            }
            
            $.ajax({
                url: formAction,
                type: 'POST',
                dataType: 'json',
                data: form.serialize(),
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                timeout: 30000
            })
            .done(function(response) {
                console.log('Payment success:', response);
                // Re-enable button
                $btn.prop('disabled', false).html(originalText);
                
                $('#modal-payment').modal('hide');
                
                // Reload table if it exists
                if (typeof table !== 'undefined' && table) {
                    table.ajax.reload(null, false);
                }
                
                // Extract message from response
                let message = 'Payment recorded successfully';
                if (response && response.message) {
                    message = response.message;
                }
                alert(message);
            })
            .fail(function(xhr, status, error) {
                console.log('Payment error:', status, error, xhr);
                // Re-enable button
                $btn.prop('disabled', false).html(originalText);
                
                let errorMessage = 'Unable to record payment';
                
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                } else if (xhr.responseJSON) {
                    if (xhr.responseJSON.errors) {
                        let errorMsg = 'Validation errors:\n';
                        $.each(xhr.responseJSON.errors, function(key, value) {
                            if (Array.isArray(value)) {
                                errorMsg += value[0] + '\n';
                            } else {
                                errorMsg += value + '\n';
                            }
                        });
                        errorMessage = errorMsg;
                    } else if (xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                } else if (xhr.responseText) {
                    // Try to parse response text if it's JSON
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMessage = response.message;
                        }
                    } catch(e) {
                        console.error('Error parsing response:', e);
                    }
                }
                
                alert(errorMessage);
            })
            .always(function() {
                // Ensure button is re-enabled
                $btn.prop('disabled', false).html(originalText);
            });
            
            return false;
        });
        
        // Prevent payment form submission
        $(document).on('submit', '#modal-payment form', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $('#btn-save-payment').click();
            return false;
        });
    });
</script>
@endpush


