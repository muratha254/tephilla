@extends('layouts.fleet')
@section('title', 'Payslip')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Payslip',
    'subtitle' => optional($item->payrollRun)->number . ' · ' . optional($item->employee)->fullName(),
    'backUrl' => route('hr.payroll.show', $item->payroll_run_id),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Payroll', 'url' => route('hr.payroll.index')],
        ['label' => 'Payslip'],
    ],
])

<div class="sx-box" id="sx-payslip">
    <div class="sx-box-body" style="min-height:auto; max-width:720px; margin:0 auto;">
        <div class="text-center" style="margin-bottom:16px;">
            <h3 style="margin:0;">{{ optional(optional($item->payrollRun)->company)->name ?? config('app.name') }}</h3>
            <p>Payslip — {{ optional($item->payrollRun)->period_label }}</p>
        </div>
        <table class="table table-bordered">
            <tr><th>Employee</th><td>{{ optional($item->employee)->fullName() }} ({{ optional($item->employee)->displayCode() }})</td></tr>
            <tr><th>Department</th><td>{{ optional(optional($item->employee)->department)->name ?: '-' }}</td></tr>
            <tr><th>Designation</th><td>{{ optional(optional($item->employee)->designation)->name ?: '-' }}</td></tr>
            <tr><th>Period</th><td>{{ optional(optional($item->payrollRun)->period_start)->format('Y-m-d') }} — {{ optional(optional($item->payrollRun)->period_end)->format('Y-m-d') }}</td></tr>
        </table>

        <table class="table table-bordered sx-gold-table">
            <thead>
                <tr><th>Description</th><th class="text-right">Amount</th></tr>
            </thead>
            <tbody>
                <tr><td>Basic Salary</td><td class="text-right">{{ number_format((float) $item->basic_salary, 2) }}</td></tr>
                @foreach(($item->breakdown_json['allowances'] ?? []) as $line)
                    <tr><td>{{ $line['name'] }} (Allowance)</td><td class="text-right">{{ number_format((float) $line['amount'], 2) }}</td></tr>
                @endforeach
                @if((float) $item->allowances > 0 && empty($item->breakdown_json['allowances']))
                    <tr><td>Allowances</td><td class="text-right">{{ number_format((float) $item->allowances, 2) }}</td></tr>
                @endif
                <tr><th>Gross Pay</th><th class="text-right">{{ number_format((float) $item->gross_pay, 2) }}</th></tr>
                @foreach(($item->breakdown_json['deductions'] ?? []) as $line)
                    <tr><td>{{ $line['name'] }} (Deduction)</td><td class="text-right">{{ number_format((float) $line['amount'], 2) }}</td></tr>
                @endforeach
                @if((float) $item->deductions > 0 && empty($item->breakdown_json['deductions']))
                    <tr><td>Deductions</td><td class="text-right">{{ number_format((float) $item->deductions, 2) }}</td></tr>
                @endif
                <tr><th>Net Pay</th><th class="text-right">{{ number_format((float) $item->net_pay, 2) }}</th></tr>
            </tbody>
        </table>

        <div class="text-center sx-form-actions no-print">
            <a href="{{ route('hr.payroll.payslip', $item) }}?print=1" class="btn btn-primary" target="_blank"><i class="fa fa-print"></i> Print</a>
            <a href="{{ route('hr.payroll.show', $item->payroll_run_id) }}" class="btn btn-default">Back</a>
        </div>
    </div>
</div>
@endsection

@if(!empty($print))
@push('scripts')
<script>window.addEventListener('load', function () { window.print(); });</script>
@endpush
@endif

@push('css')
<style>
@media print {
    .main-header, .main-sidebar, .page-header, .no-print, .sx-toolbar-actions { display: none !important; }
    .content-wrapper { margin: 0 !important; }
}
</style>
@endpush
