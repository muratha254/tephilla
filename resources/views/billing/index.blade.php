@extends('layouts.fleet')

@section('title', 'Billing')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Billing',
    'subtitle' => 'Subscription invoices and payment history',
    'backUrl' => $billingHome ?? route('billing.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => $billingHome ?? route('billing.index'), 'icon' => 'fa-home'],
        ['label' => 'Billing'],
    ],
])

<div class="sx-box">
    <div class="sx-box-body">
        <h4>Invoices</h4>
        <div class="table-responsive">
            <table class="table table-bordered sx-gold-table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Plan</th>
                        <th>Amount</th>
                        <th>Due</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        <tr>
                            <td>{{ $invoice->invoice_number }}</td>
                            <td>{{ $invoice->plan_name ?: '-' }}</td>
                            <td>{{ $invoice->currency }} {{ number_format((float) $invoice->amount, 2) }}</td>
                            <td>{{ optional($invoice->due_date)->format('d M Y') }}</td>
                            <td><span class="sx-sub-badge {{ $invoice->isPaid() ? 'sx-sub-active' : ($invoice->isOverdue() ? 'sx-sub-expired' : 'sx-sub-soon') }}">{{ $invoice->displayStatus() }}</span></td>
                            <td><a href="{{ route('billing.invoices.show', $invoice) }}" class="btn btn-xs btn-primary">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No invoices yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h4>Payment history</h4>
        <div class="table-responsive">
            <table class="table table-bordered sx-gold-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Invoice</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $pay)
                        <tr>
                            <td>{{ optional($pay->paid_at)->format('d M Y') }}</td>
                            <td>{{ number_format((float) $pay->amount, 2) }}</td>
                            <td>{{ $pay->method }}</td>
                            <td>{{ $pay->reference ?: '-' }}</td>
                            <td>{{ optional($pay->invoice)->invoice_number ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No payments recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
