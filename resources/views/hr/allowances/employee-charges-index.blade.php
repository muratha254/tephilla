@extends('layouts.fleet')
@section('title', 'Employee Allowances/Deductions')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Employee Allowances/Deductions',
    'subtitle' => 'View/Search Charges (Employee Allowances/Deductions)',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Allowances/Deductions'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div></div>
        @if($canManage)
            <div class="sx-toolbar-actions">
                <button type="button" class="btn btn-primary" id="sx-open-emp-charge"><i class="fa fa-plus"></i> Record Emp. Allowance/Deduction</button>
            </div>
        @endif
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'emp-charge-table'])
        <div class="table-responsive">
            <table id="emp-charge-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="emp-charge-check-all"></th>
                        <th>Employee Name</th>
                        <th>Charge</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Month/Year</th>
                        <th>Recorded Date</th>
                        <th>Posted By</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($records as $row)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="emp-charge-row-check"></td>
                            <td>{{ optional($row->employee)->fullName() }}</td>
                            <td>{{ optional($row->charge)->name }}</td>
                            <td>{{ optional($row->charge)->category }}</td>
                            <td data-order="{{ $row->amount }}">{{ number_format((float) $row->amount, 2) }}</td>
                            <td>{{ $row->month_year }}</td>
                            <td data-order="{{ optional($row->recorded_date)->format('Y-m-d') }}">{{ optional($row->recorded_date)->format('Y-m-d') }}</td>
                            <td>{{ optional($row->user)->name }}</td>
                            <td>{{ optional($row->branch)->name }}</td>
                            <td>
                                @if($row->status === 'active')
                                    <span class="sx-status-active">Active</span>
                                @else
                                    <span class="sx-status-inactive">{{ ucfirst($row->status) }}</span>
                                @endif
                            </td>
                            <td>
                                @if($canManage)
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">Action <span class="caret"></span></button>
                                        <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                            <li>
                                                <a href="#" class="sx-edit-emp-charge"
                                                    data-id="{{ $row->id }}"
                                                    data-url="{{ route('hr.allowances.employee.update', $row) }}"
                                                    data-employee="{{ $row->employee_id }}"
                                                    data-category="{{ optional($row->charge)->category }}"
                                                    data-charge="{{ $row->charge_id }}"
                                                    data-date="{{ optional($row->recorded_date)->format('Y-m-d') }}"
                                                    data-amount="{{ $row->amount }}"
                                                    data-notes="{{ $row->notes }}">
                                                    <i class="fa fa-edit"></i> Edit
                                                </a>
                                            </li>
                                            <li>
                                                <a href="#" class="sx-delete-expense" data-form="sx-del-empcharge-{{ $row->id }}"><i class="fa fa-trash"></i> Delete</a>
                                                <form id="sx-del-empcharge-{{ $row->id }}" action="{{ route('hr.allowances.employee.destroy', $row) }}" method="post" class="hidden">@csrf @method('DELETE')</form>
                                            </li>
                                        </ul>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($canManage)
<div class="modal fade" id="sx-emp-charge-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="post" action="{{ route('hr.allowances.employee.store') }}" class="modal-content" id="sx-emp-charge-form">
            @csrf
            <input type="hidden" name="_method" id="sx-emp-charge-method" value="POST">
            <div class="modal-header" style="background:#c9a027;color:#fff;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1;">&times;</button>
                <h4 class="modal-title" id="sx-emp-charge-title"><i class="fa fa-plus-square"></i> Record Emp. Allowance/Deduction</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Employees <span class="sx-req">*</span></label>
                            <select name="employee_id" id="sx-ec-employee" class="form-control" required>
                                <option value="">~~Select Employee~~</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->fullName() }}-{{ $employee->displayCode() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Category <span class="sx-req">*</span></label>
                            <select id="sx-ec-category" class="form-control" required>
                                <option value="">~~Select Category~~</option>
                                <option value="Allowance">Allowance</option>
                                <option value="Deduction">Deduction</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Charge Name <span class="sx-req">*</span></label>
                            <select name="charge_id" id="sx-ec-charge" class="form-control" required>
                                <option value="">~~Select Charge~~</option>
                                @foreach($charges as $charge)
                                    <option value="{{ $charge->id }}" data-category="{{ $charge->category }}">{{ $charge->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date <span class="sx-req">*</span></label>
                            <input type="date" name="recorded_date" id="sx-ec-date" class="form-control" value="{{ now()->toDateString() }}" required>
                            <p class="help-block" style="color:#00a65a;margin:4px 0 0;">The record date should be for the payroll month</p>
                        </div>
                        <div class="form-group">
                            <label>Amount <span class="sx-req">*</span></label>
                            <input type="number" step="0.01" min="0" name="amount" id="sx-ec-amount" class="form-control" placeholder="Amount" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="notes" id="sx-ec-notes" class="form-control" rows="12" placeholder="Description"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="text-align:center;">
                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
                <button type="button" class="btn btn-warning" data-dismiss="modal"><i class="fa fa-times"></i> Close</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@include('accounting.partials.datatable-js', [
    'tableId' => 'emp-charge-table', 'lengthId' => 'emp-charge-table-length', 'searchId' => 'emp-charge-table-search',
    'exportId' => 'emp-charge-table-export', 'colvisId' => 'emp-charge-table-colvis',
    'checkAll' => 'emp-charge-check-all', 'rowCheck' => 'emp-charge-row-check',
    'title' => 'Employee Allowances Deductions', 'filename' => 'employee-allowances-deductions', 'noSort' => [0, 10], 'order' => [[6, 'desc']],
])
@push('scripts')
<script>
(function ($) {
    var storeUrl = @json(route('hr.allowances.employee.store'));

    function filterCharges(category, selected) {
        $('#sx-ec-charge option').each(function () {
            if (!$(this).val()) {
                $(this).prop('hidden', false);
                return;
            }
            var match = !category || String($(this).data('category')) === String(category);
            $(this).prop('hidden', !match);
        });
        if (selected) {
            $('#sx-ec-charge').val(String(selected));
        } else if ($('#sx-ec-charge option:selected').prop('hidden')) {
            $('#sx-ec-charge').val('');
        }
    }

    function resetModal() {
        $('#sx-emp-charge-form').attr('action', storeUrl);
        $('#sx-emp-charge-method').val('POST');
        $('#sx-emp-charge-title').html('<i class="fa fa-plus-square"></i> Record Emp. Allowance/Deduction');
        $('#sx-ec-employee').val('');
        $('#sx-ec-category').val('');
        $('#sx-ec-charge').val('');
        $('#sx-ec-date').val(@json(now()->toDateString()));
        $('#sx-ec-amount').val('');
        $('#sx-ec-notes').val('');
        filterCharges('', '');
    }

    $('#sx-open-emp-charge').on('click', function () {
        resetModal();
        $('#sx-emp-charge-modal').modal('show');
    });

    $('#sx-ec-category').on('change', function () {
        filterCharges($(this).val(), '');
    });

    $(document).on('click', '.sx-edit-emp-charge', function (e) {
        e.preventDefault();
        var btn = $(this);
        $('#sx-emp-charge-form').attr('action', btn.data('url'));
        $('#sx-emp-charge-method').val('PUT');
        $('#sx-emp-charge-title').html('<i class="fa fa-pencil"></i> Update Emp. Allowance/Deduction');
        $('#sx-ec-employee').val(String(btn.data('employee')));
        $('#sx-ec-category').val(btn.data('category') || '');
        filterCharges(btn.data('category') || '', btn.data('charge'));
        $('#sx-ec-date').val(btn.data('date'));
        $('#sx-ec-amount').val(btn.data('amount'));
        $('#sx-ec-notes').val(btn.data('notes') || '');
        $('#sx-emp-charge-modal').modal('show');
    });

    $(document).on('click', '.sx-delete-expense', function (e) {
        e.preventDefault();
        var form = document.getElementById($(this).data('form'));
        if (!form) return;
        if (window.Swal) {
            Swal.fire({icon:'warning',title:'Delete Record',text:'This employee charge will be removed.',showCancelButton:true,confirmButtonColor:'#dd4b39',confirmButtonText:'Delete'})
                .then(function (r) { if (r.isConfirmed) form.submit(); });
            return;
        }
        if (confirm('Delete this record?')) form.submit();
    });

    @if(request('edit'))
        $('.sx-edit-emp-charge[data-id="{{ (int) request('edit') }}"]').trigger('click');
    @endif
})(jQuery);
</script>
@endpush
