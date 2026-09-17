@extends('layouts.fleet')

@php $isEdit = $advance->exists; @endphp
@section('title', $isEdit ? 'Update Advance Salary' : 'Allocate Advance Salary')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Advance Salary',
    'subtitle' => 'Allocate/Update Advance Salary',
    'backUrl' => route('hr.advance-salary.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Advance Salary List', 'url' => route('hr.advance-salary.index')],
        ['label' => 'Advance Salary'],
    ],
])

<form method="post" action="{{ $isEdit ? route('hr.advance-salary.update', $advance) : route('hr.advance-salary.store') }}" class="sx-item-form">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <div class="sx-box">
        <div class="sx-box-body" style="min-height:auto; max-width:720px; margin:0 auto;">
            <div class="form-group">
                <label>Employees <span class="sx-req">*</span></label>
                <select name="employee_id" class="form-control" required>
                    <option value="">~~Select Employee~~</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" @if((string) old('employee_id', $advance->employee_id) === (string) $employee->id) selected @endif>
                            {{ $employee->fullName() }}-{{ $employee->displayCode() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Paying Account <span class="sx-req">*</span></label>
                <select name="ledger_account_id" class="form-control" required>
                    <option value="">~~Select Payment Account~~</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @if((string) old('ledger_account_id', $advance->ledger_account_id) === (string) $account->id) selected @endif>
                            {{ $account->name }}@if($account->gl_code) ({{ $account->gl_code }})@endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Date <span class="sx-req">*</span></label>
                <input type="date" name="advance_date" class="form-control" value="{{ old('advance_date', optional($advance->advance_date)->format('Y-m-d') ?: now()->toDateString()) }}" required>
            </div>
            <div class="form-group">
                <label>Amount <span class="sx-req">*</span></label>
                <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="Amount" value="{{ old('amount', $advance->amount) }}" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="4" placeholder="Type here...">{{ old('description', $advance->description) }}</textarea>
            </div>
            <div class="text-center sx-form-actions">
                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
                <a href="{{ route('hr.advance-salary.index') }}" class="btn btn-warning"><i class="fa fa-times"></i> Close</a>
            </div>
        </div>
    </div>
</form>
@endsection
