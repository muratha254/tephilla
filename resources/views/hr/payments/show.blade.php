@extends('layouts.fleet')
@section('title', 'Payment ' . $payment->number)

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Salary Payment',
    'subtitle' => $payment->number,
    'backUrl' => route('hr.payments.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Payments', 'url' => route('hr.payments.index')],
        ['label' => $payment->number],
    ],
])

<div class="sx-box">
    <div class="sx-box-body" style="min-height:auto; max-width:720px; margin:0 auto;">
        <table class="table table-bordered">
            <tr><th>Number</th><td>{{ $payment->number }}</td></tr>
            <tr><th>Date</th><td>{{ optional($payment->payment_date)->format('Y-m-d') }}</td></tr>
            <tr><th>Employee</th><td>{{ optional($payment->employee)->fullName() }}</td></tr>
            <tr><th>Payroll</th><td>{{ optional($payment->payrollRun)->number ?: 'Ad-hoc' }}</td></tr>
            <tr><th>Method</th><td>{{ ucfirst(str_replace('_', ' ', $payment->method)) }}</td></tr>
            <tr><th>Amount</th><td>{{ number_format((float) $payment->amount, 2) }}</td></tr>
            <tr><th>Reference</th><td>{{ $payment->reference ?: '-' }}</td></tr>
            <tr><th>Status</th><td><span class="sx-status-active">{{ ucfirst($payment->status) }}</span></td></tr>
            <tr><th>Notes</th><td>{{ $payment->notes ?: '-' }}</td></tr>
            <tr><th>Posted By</th><td>{{ optional($payment->user)->name ?: '-' }}</td></tr>
        </table>
        <div class="text-center sx-form-actions">
            <a href="{{ route('hr.payments.index') }}" class="btn btn-default">Back</a>
        </div>
    </div>
</div>
@endsection
