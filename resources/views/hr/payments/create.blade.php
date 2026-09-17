@extends('layouts.fleet')
@section('title', 'Record Salary Payment')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Record Salary Payment',
    'subtitle' => 'Pay against payroll item or ad-hoc',
    'backUrl' => route('hr.payments.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Payments', 'url' => route('hr.payments.index')],
        ['label' => 'Record Payment'],
    ],
])

@php
    $selectedItemId = old('payroll_item_id', optional($payrollItem)->id);
    $selectedEmployeeId = old('employee_id', optional(optional($payrollItem)->employee)->id);
    $defaultAmount = old('amount', optional($payrollItem)->net_pay);
@endphp

<form method="post" action="{{ route('hr.payments.store') }}" class="sx-item-form" id="sx-salary-pay-form">
    @csrf
    <div class="sx-box">
        <div class="sx-box-body" style="min-height:auto; max-width:720px; margin:0 auto;">
            <div class="form-group">
                <label>Payroll Item (optional)</label>
                <select name="payroll_item_id" id="sx-payroll-item" class="form-control">
                    <option value="">Ad-hoc payment</option>
                    @foreach($payrollItems as $item)
                        <option value="{{ $item->id }}"
                            data-employee="{{ $item->employee_id }}"
                            data-amount="{{ $item->net_pay }}"
                            @if((string) $selectedItemId === (string) $item->id) selected @endif>
                            {{ optional($item->payrollRun)->number }} — {{ optional($item->employee)->fullName() }} (Net {{ number_format((float) $item->net_pay, 2) }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Employee <span class="sx-req">*</span></label>
                <select name="employee_id" id="sx-pay-employee" class="form-control" required>
                    <option value="">~~Select Employee~~</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" @if((string) $selectedEmployeeId === (string) $employee->id) selected @endif>
                            {{ $employee->fullName() }}-{{ $employee->displayCode() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Payment Date <span class="sx-req">*</span></label>
                <input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', now()->toDateString()) }}" required>
            </div>
            <div class="form-group">
                <label>Method <span class="sx-req">*</span></label>
                <select name="method" class="form-control" required>
                    @foreach($methods as $key => $label)
                        <option value="{{ $key }}" @if(old('method', 'bank_transfer') === $key) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Amount <span class="sx-req">*</span></label>
                <input type="number" step="0.01" min="0.01" name="amount" id="sx-pay-amount" class="form-control" value="{{ $defaultAmount }}" required>
            </div>
            <div class="form-group">
                <label>Reference</label>
                <input type="text" name="reference" class="form-control" value="{{ old('reference') }}">
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
            </div>
            <div class="text-center sx-form-actions">
                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save Payment</button>
                <a href="{{ route('hr.payments.index') }}" class="btn btn-warning"><i class="fa fa-times"></i> Close</a>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function ($) {
    $('#sx-payroll-item').on('change', function () {
        var opt = $(this).find(':selected');
        var emp = opt.data('employee');
        var amount = opt.data('amount');
        if (emp) $('#sx-pay-employee').val(String(emp));
        if (amount != null && amount !== '') $('#sx-pay-amount').val(amount);
    });
})(jQuery);
</script>
@endpush
