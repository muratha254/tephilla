@extends('layouts.fleet')
@section('title', 'Payroll ' . $payroll->number)

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Payroll Run',
    'subtitle' => $payroll->number . ' · ' . $payroll->period_label,
    'backUrl' => route('hr.payroll.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Payroll', 'url' => route('hr.payroll.index')],
        ['label' => $payroll->number],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div>
            <strong>Status:</strong> {{ ucfirst($payroll->status) }}
            · Gross {{ number_format((float) $payroll->total_gross, 2) }}
            · Deductions {{ number_format((float) $payroll->total_deductions, 2) }}
            · Net {{ number_format((float) $payroll->total_net, 2) }}
        </div>
        <div class="sx-toolbar-actions">
            @if($canManage && $payroll->status === 'draft')
                <form method="post" action="{{ route('hr.payroll.process', $payroll) }}" style="display:inline;">@csrf
                    <button type="submit" class="btn btn-info"><i class="fa fa-play"></i> Process</button>
                </form>
            @endif
            @if($canManage && in_array($payroll->status, ['draft', 'processed'], true))
                <form method="post" action="{{ route('hr.payroll.approve', $payroll) }}" style="display:inline;">@csrf
                    <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Approve</button>
                </form>
            @endif
            <a href="{{ route('hr.payroll.index') }}" class="btn btn-default">Back</a>
        </div>
    </div>
    <div class="sx-box-body sx-items-body">
        <div class="table-responsive">
            <table class="table table-bordered sx-gold-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th class="text-right">Basic</th>
                        <th class="text-right">Allowances</th>
                        <th class="text-right">Deductions</th>
                        <th class="text-right">Gross</th>
                        <th class="text-right">Net</th>
                        <th class="sx-no-export">Payslip</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payroll->items as $item)
                        <tr>
                            <td>{{ optional($item->employee)->fullName() }}</td>
                            <td class="text-right">{{ number_format((float) $item->basic_salary, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $item->allowances, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $item->deductions, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $item->gross_pay, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $item->net_pay, 2) }}</td>
                            <td>
                                <a href="{{ route('hr.payroll.payslip', $item) }}" class="btn btn-xs btn-primary">View</a>
                                <a href="{{ route('hr.payroll.payslip', $item) }}?print=1" class="btn btn-xs btn-default" target="_blank">Print</a>
                                @if($canManage && in_array($payroll->status, ['processed', 'approved'], true))
                                    <a href="{{ route('hr.payments.create', ['payroll_item_id' => $item->id]) }}" class="btn btn-xs btn-success">Pay</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
