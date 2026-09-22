@extends('layouts.fleet')

@section('title', $invoice->invoice_number)

@section('content')
@include('layouts.partials.page-header', [
    'title' => $invoice->invoice_number,
    'subtitle' => 'Subscription invoice',
    'backUrl' => route('billing.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Billing', 'url' => route('billing.index')],
        ['label' => $invoice->invoice_number],
    ],
])

<div class="sx-box" style="max-width:720px;">
    <div class="sx-box-body">
        <p><strong>{{ $invoice->company->name }}</strong></p>
        <p>Plan: {{ $invoice->plan_name ?: '-' }}</p>
        <p>Amount: <strong>{{ $invoice->currency }} {{ number_format((float) $invoice->amount, 2) }}</strong></p>
        <p>Due date: {{ optional($invoice->due_date)->format('d M Y') }}</p>
        <p>Status: <span class="sx-sub-badge {{ $invoice->isPaid() ? 'sx-sub-active' : ($invoice->isOverdue() ? 'sx-sub-expired' : 'sx-sub-soon') }}">{{ $invoice->displayStatus() }}</span></p>
        @if($invoice->notes)
            <p>{{ $invoice->notes }}</p>
        @endif
        <p>Sent to: {{ $invoice->sent_to_email ?: '-' }} on {{ optional($invoice->sent_at)->format('d M Y H:i') ?: '-' }}</p>
        @if($invoice->isPaid())
            <p>Paid on {{ optional($invoice->paid_at)->format('d M Y') }}</p>
        @endif
        <a href="{{ route('billing.index') }}" class="btn btn-default">Back to billing</a>
    </div>
</div>
@endsection
