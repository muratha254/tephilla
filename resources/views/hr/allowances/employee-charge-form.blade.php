@extends('layouts.fleet')

@php $isEdit = $record->exists; @endphp
@section('title', $isEdit ? 'Update Emp. Allowance/Deduction' : 'Record Emp. Allowance/Deduction')

@section('content')
@include('layouts.partials.page-header', [
    'title' => $isEdit ? 'Update Emp. Allowance/Deduction' : 'Record Emp. Allowance/Deduction',
    'subtitle' => 'Employee Allowances/Deductions',
    'backUrl' => route('hr.allowances.employee'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Allowances/Deductions', 'url' => route('hr.allowances.employee')],
        ['label' => $isEdit ? 'Edit' : 'Record'],
    ],
])

<form method="post" action="{{ $isEdit ? route('hr.allowances.employee.update', $record) : route('hr.allowances.employee.store') }}" class="sx-item-form">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <div class="sx-box">
        <div class="sx-box-body" style="min-height:auto; max-width:720px; margin:0 auto;">
            <div class="form-group">
                <label>Employee <span class="sx-req">*</span></label>
                <select name="employee_id" class="form-control" required>
                    <option value="">~~Select Employee~~</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" @if((string) old('employee_id', $record->employee_id) === (string) $employee->id) selected @endif>
                            {{ $employee->fullName() }}-{{ $employee->displayCode() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Charge <span class="sx-req">*</span></label>
                <select name="charge_id" class="form-control" required>
                    <option value="">~~Select Charge~~</option>
                    @foreach($charges as $charge)
                        <option value="{{ $charge->id }}" @if((string) old('charge_id', $record->charge_id) === (string) $charge->id) selected @endif>
                            {{ $charge->name }} ({{ $charge->category }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Branch</label>
                <select name="branch_id" class="form-control">
                    @foreach($branches as $option)
                        <option value="{{ $option->id }}" @if((string) old('branch_id', $record->branch_id ?: $selectedBranchId) === (string) $option->id) selected @endif>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Amount <span class="sx-req">*</span></label>
                <input type="number" step="0.01" min="0" name="amount" class="form-control" placeholder="Amount" value="{{ old('amount', $record->amount) }}" required>
            </div>
            <div class="form-group">
                <label>Month/Year <span class="sx-req">*</span></label>
                <input type="text" name="month_year" class="form-control" placeholder="e.g. September 2026" value="{{ old('month_year', $record->month_year) }}" required>
            </div>
            <div class="form-group">
                <label>Recorded Date <span class="sx-req">*</span></label>
                <input type="date" name="recorded_date" class="form-control" value="{{ old('recorded_date', optional($record->recorded_date)->format('Y-m-d') ?: now()->toDateString()) }}" required>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Type here...">{{ old('notes', $record->notes) }}</textarea>
            </div>
            @if($isEdit)
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="active" @if(old('status', $record->status) === 'active') selected @endif>Active</option>
                    <option value="inactive" @if(old('status', $record->status) === 'inactive') selected @endif>Inactive</option>
                </select>
            </div>
            @endif
            <div class="text-center sx-form-actions">
                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
                <a href="{{ route('hr.allowances.employee') }}" class="btn btn-warning"><i class="fa fa-times"></i> Close</a>
            </div>
        </div>
    </div>
</form>
@endsection
