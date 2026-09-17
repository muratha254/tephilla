@extends('layouts.fleet')

@section('title', 'Quotation ' . $quotation->number)

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Quotation',
    'subtitle' => $quotation->number,
    'backUrl' => route('quotations.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Quotations', 'url' => route('quotations.index')],
        ['label' => $quotation->number],
    ],
])

@php
    $statusLabel = $statuses[$quotation->status] ?? ucfirst((string) $quotation->status);
@endphp

<div class="sx-invoice">
    <div class="sx-invoice-title">
        <h3><i class="fa fa-file-text-o"></i> Quotation</h3>
        <div class="sx-invoice-date">Date: {{ optional($quotation->quote_date)->format('d-m-Y') }}</div>
    </div>

    <div class="row sx-invoice-parties">
        <div class="col-md-4">
            <div class="sx-invoice-label">Customer</div>
            <div class="sx-invoice-name">{{ optional($quotation->customer)->name ?: '-' }}</div>
            <div>Phone: {{ optional($quotation->customer)->phone ?: '-' }}</div>
            <div>Email: {{ optional($quotation->customer)->email ?: '-' }}</div>
        </div>
        <div class="col-md-4">
            <div class="sx-invoice-label">Quote Details</div>
            <div>Number: <strong>{{ $quotation->number }}</strong></div>
            <div>Status: {{ $statusLabel }}</div>
            <div>Valid Until: {{ optional($quotation->valid_until)->format('d-m-Y') ?: '-' }}</div>
            <div>Created by: {{ optional($quotation->user)->name ?: '-' }}</div>
        </div>
        <div class="col-md-4">
            <div class="sx-invoice-label">Actions</div>
            <div class="sx-toolbar-actions" style="margin-top:6px;">
                <a href="{{ route('quotations.print', $quotation) }}" target="_blank" class="btn btn-default btn-sm"><i class="fa fa-print"></i> Print</a>
                @if($canUpdate)
                    <a href="{{ route('quotations.edit', $quotation) }}" class="btn btn-primary btn-sm"><i class="fa fa-pencil"></i> Edit</a>
                @endif
                @if($canSend)
                    <form action="{{ route('quotations.send', $quotation) }}" method="post" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-info btn-sm"><i class="fa fa-paper-plane"></i> Mark Sent</button>
                    </form>
                @endif
                @if($canConvert)
                    <form action="{{ route('quotations.convert', $quotation) }}" method="post" style="display:inline;" onsubmit="return confirm('Convert this quotation to an unpaid invoice?');">
                        @csrf
                        <button type="submit" class="btn sx-btn-aqua btn-sm"><i class="fa fa-exchange"></i> Convert to Sale</button>
                    </form>
                @endif
                @if($canDelete)
                    <form action="{{ route('quotations.destroy', $quotation) }}" method="post" style="display:inline;" onsubmit="return confirm('Delete this draft quotation?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm"><i class="fa fa-trash"></i> Delete</button>
                    </form>
                @endif
            </div>
            @if($quotation->convertedSale)
                <div style="margin-top:10px;">
                    Converted sale:
                    <a href="{{ route('sales.show', $quotation->convertedSale) }}">
                        {{ $quotation->convertedSale->invoice_number ?: $quotation->convertedSale->number }}
                    </a>
                </div>
            @endif
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered sx-invoice-items">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Discount</th>
                    <th>Tax %</th>
                    <th>Tax Amt</th>
                    <th>Line Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quotation->items as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $item->description ?: optional($item->product)->name }}</td>
                        <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 4), '0'), '.') }}</td>
                        <td>{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td>{{ number_format((float) $item->discount_amount, 2) }}</td>
                        <td>{{ number_format((float) $item->tax_rate, 2) }}</td>
                        <td>{{ number_format((float) $item->tax_amount, 2) }}</td>
                        <td>{{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="sx-po-totals">
        <div class="sx-po-totals-left">
            @if($quotation->notes)
                <div class="form-group">
                    <label>Notes</label>
                    <div>{{ $quotation->notes }}</div>
                </div>
            @endif
            @if($quotation->terms)
                <div class="form-group">
                    <label>Terms</label>
                    <div>{{ $quotation->terms }}</div>
                </div>
            @endif
        </div>
        <div class="sx-po-totals-right">
            <div class="sx-po-total-row"><span>Subtotal</span><strong>Ksh {{ number_format((float) $quotation->subtotal, 2) }}</strong></div>
            <div class="sx-po-total-row"><span>Discount</span><strong>Ksh {{ number_format((float) $quotation->discount_amount, 2) }}</strong></div>
            <div class="sx-po-total-row"><span>Tax</span><strong>Ksh {{ number_format((float) $quotation->tax_amount, 2) }}</strong></div>
            <div class="sx-po-total-row"><span>Grand Total</span><strong>Ksh {{ number_format((float) $quotation->total, 2) }}</strong></div>
        </div>
    </div>
</div>
@endsection
