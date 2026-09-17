@extends('layouts.fleet')

@php $isEdit = $employee->exists; @endphp
@section('title', $isEdit ? 'Update Employee' : 'Add Employee')

@section('content')
@include('layouts.partials.page-header', [
    'title' => $isEdit ? 'Update Employee' : 'Add Employee',
    'subtitle' => 'Add/Update Employee',
    'backUrl' => route('hr.employees.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Employee List', 'url' => route('hr.employees.index')],
        ['label' => $isEdit ? 'Edit' : 'Add Employee'],
    ],
])

<form method="post" action="{{ $isEdit ? route('hr.employees.update', $employee) : route('hr.employees.store') }}" class="sx-item-form" id="sx-employee-form">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <div class="sx-box">
        <div class="sx-box-body" style="min-height:auto;">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>First Name <span class="sx-req">*</span></label>
                        <input type="text" name="first_name" class="form-control" placeholder="First Name" value="{{ old('first_name', $employee->first_name) }}" required>
                    </div>
                    <div class="form-group">
                        <label>Joining Date <span class="sx-req">*</span></label>
                        <input type="date" name="joining_date" class="form-control" value="{{ old('joining_date', optional($employee->joining_date)->format('Y-m-d') ?: now()->toDateString()) }}" required>
                    </div>
                    <div class="form-group">
                        <label>Branch <span class="sx-req">*</span></label>
                        <select name="branch_id" class="form-control" required>
                            @foreach($branches as $option)
                                <option value="{{ $option->id }}" @if((string) old('branch_id', $employee->branch_id ?: $selectedBranchId) === (string) $option->id) selected @endif>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>National ID <span class="sx-req">*</span></label>
                        <input type="text" name="national_id" class="form-control" placeholder="National ID" value="{{ old('national_id', $employee->national_id) }}" required>
                    </div>
                    <div class="form-group">
                        <label>KRA PIN</label>
                        <input type="text" name="kra_pin" class="form-control" placeholder="KRA PIN" value="{{ old('kra_pin', $employee->kra_pin) }}">
                    </div>
                    <div class="form-group">
                        <label>Payment Period <span class="sx-req">*</span></label>
                        <select name="payment_period" class="form-control" required>
                            @foreach(['Monthly','Weekly','Bi-weekly','Daily'] as $period)
                                <option value="{{ $period }}" @if(old('payment_period', $employee->payment_period ?: 'Monthly') === $period) selected @endif>{{ $period }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Postcode</label>
                        <input type="text" name="postcode" class="form-control" placeholder="Postcode" value="{{ old('postcode', $employee->postcode) }}">
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" class="form-control" placeholder="Middle Name" value="{{ old('middle_name', $employee->middle_name) }}">
                    </div>
                    <div class="form-group">
                        <label>User Role</label>
                        <select name="role_id" class="form-control">
                            <option value="">Select Employee Role</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" @if((string) old('role_id', $employee->role_id) === (string) $role->id) selected @endif>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Department <span class="sx-req">*</span></label>
                        <select name="department_id" id="sx-emp-department" class="form-control" required>
                            <option value="">Select Department</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" @if((string) old('department_id', $employee->department_id) === (string) $department->id) selected @endif>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Phone No. <span class="sx-req">*</span></label>
                        <input type="text" name="phone" class="form-control" placeholder="Phone No." value="{{ old('phone', $employee->phone) }}" required>
                    </div>
                    <div class="form-group">
                        <label>SHIF NO.</label>
                        <input type="text" name="shif_no" class="form-control" placeholder="SHIF NO." value="{{ old('shif_no', $employee->shif_no) }}">
                    </div>
                    <div class="form-group">
                        <label>Advance Salary Limit</label>
                        <input type="number" step="0.01" min="0" name="advance_salary_limit" class="form-control" placeholder="Advance Salary Limit" value="{{ old('advance_salary_limit', $employee->advance_salary_limit) }}">
                    </div>
                    <div class="form-group">
                        <label>Home Address</label>
                        <input type="text" name="home_address" class="form-control" placeholder="Home Address" value="{{ old('home_address', $employee->home_address) }}">
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        <label>Last Name <span class="sx-req">*</span></label>
                        <input type="text" name="last_name" class="form-control" placeholder="Last Name" value="{{ old('last_name', $employee->last_name) }}" required>
                    </div>
                    <div class="form-group">
                        <label>Marital Status <span class="sx-req">*</span></label>
                        <select name="marital_status" class="form-control" required>
                            <option value="">Select Marital Status</option>
                            @foreach(['Single','Married','Divorced','Widowed'] as $status)
                                <option value="{{ $status }}" @if(old('marital_status', $employee->marital_status) === $status) selected @endif>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Designation/Position</label>
                        <select name="designation_id" id="sx-emp-designation" class="form-control">
                            <option value="">Select Designation</option>
                            @foreach($designations as $designation)
                                <option value="{{ $designation->id }}" data-department="{{ $designation->department_id }}" @if((string) old('designation_id', $employee->designation_id) === (string) $designation->id) selected @endif>{{ $designation->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Alt. Phone</label>
                        <input type="text" name="alt_phone" class="form-control" placeholder="Alt. Phone" value="{{ old('alt_phone', $employee->alt_phone) }}">
                    </div>
                    <div class="form-group">
                        <label>NSSF NO.</label>
                        <input type="text" name="nssf_no" class="form-control" placeholder="NSSF NO." value="{{ old('nssf_no', $employee->nssf_no) }}">
                    </div>
                    <div class="form-group">
                        <label>Leave Counts(per year)</label>
                        <input type="number" min="0" name="leave_counts" class="form-control" placeholder="Leave Counts" value="{{ old('leave_counts', $employee->leave_counts) }}">
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        <label>Payroll Number</label>
                        <input type="text" name="payroll_number" class="form-control" placeholder="Payroll Number" value="{{ old('payroll_number', $employee->payroll_number) }}">
                    </div>
                    <div class="form-group">
                        <label>Gender <span class="sx-req">*</span></label>
                        <select name="gender" class="form-control" required>
                            <option value="">~~Select Gender~~</option>
                            @foreach(['Male','Female','Other'] as $gender)
                                <option value="{{ $gender }}" @if(old('gender', $employee->gender) === $gender) selected @endif>{{ $gender }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Employment Category <span class="sx-req">*</span></label>
                        <select name="category_id" class="form-control" required>
                            <option value="">Select Employment Category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @if((string) old('category_id', $employee->category_id) === (string) $category->id) selected @endif>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="Email Address" value="{{ old('email', $employee->email) }}">
                    </div>
                    <div class="form-group">
                        <label>Basic Salary <span class="sx-req">*</span></label>
                        <input type="number" step="0.01" min="0" name="basic_salary" class="form-control" placeholder="Basic Salary" value="{{ old('basic_salary', $employee->basic_salary) }}" required>
                    </div>
                    <div class="form-group">
                        <label>County <span class="sx-req">*</span></label>
                        <select name="county" class="form-control" required>
                            <option value="">-Select County-</option>
                            @foreach($counties as $county)
                                <option value="{{ $county }}" @if(old('county', $employee->county) === $county) selected @endif>{{ $county }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <h4 style="margin-top:10px;"><strong>Bank Details</strong></h4>
            <hr style="margin-top:6px;">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Account Holder's Name</label>
                        <input type="text" name="bank_account_name" class="form-control" placeholder="Account Holder's Name" value="{{ old('bank_account_name', $employee->bank_account_name) }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Account number</label>
                        <input type="text" name="bank_account_number" class="form-control" placeholder="Account Number" value="{{ old('bank_account_number', $employee->bank_account_number) }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Bank Name</label>
                        <input type="text" name="bank_name" class="form-control" placeholder="Bank Name" value="{{ old('bank_name', $employee->bank_name) }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Bank Branch</label>
                        <input type="text" name="bank_branch" class="form-control" placeholder="Bank Branch" value="{{ old('bank_branch', $employee->bank_branch) }}">
                    </div>
                </div>
            </div>

            <h4 style="margin-top:10px;"><strong>Statutory Deductions</strong></h4>
            <hr style="margin-top:6px;">
            <div class="row">
                @php
                    $yesNo = [['1','Yes'],['0','No']];
                    $statFields = [
                        'apply_paye' => 'Apply PAYE',
                        'apply_shif' => 'Apply SHIF',
                        'apply_nssf' => 'Apply NSSF',
                        'apply_housing_levy' => 'Apply Housing Levy',
                    ];
                @endphp
                @foreach($statFields as $field => $label)
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>{{ $label }} <span class="sx-req">*</span></label>
                            <select name="{{ $field }}" class="form-control" required>
                                @foreach($yesNo as [$val, $text])
                                    <option value="{{ $val }}" @if((string) old($field, $employee->{$field} ? '1' : '0') === $val) selected @endif>{{ $text }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="text-center sx-form-actions">
                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save Employee</button>
                <a href="{{ route('hr.employees.index') }}" class="btn btn-warning"><i class="fa fa-times"></i> Close</a>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function ($) {
    function filterDesignations() {
        var dept = $('#sx-emp-department').val();
        $('#sx-emp-designation option').each(function () {
            var d = $(this).data('department');
            if (!$(this).val()) { $(this).prop('hidden', false); return; }
            $(this).prop('hidden', dept && String(d) !== String(dept));
        });
        var selected = $('#sx-emp-designation option:selected');
        if (selected.prop('hidden')) $('#sx-emp-designation').val('');
    }
    $('#sx-emp-department').on('change', filterDesignations);
    filterDesignations();
})(jQuery);
</script>
@endpush
