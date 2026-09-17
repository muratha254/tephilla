@extends('layouts.fleet')

@section('title', 'Credit Note ' . $note->number)

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Credit Note',
    'subtitle' => $note->number,
    'backUrl' => route('sales.credit-notes'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Credit Notes', 'url' => route('sales.credit-notes')],
        ['label' => $note->number],
    ],
])

@php
    $money = function ($amount) {
        return 'Ksh ' . number_format((float) $amount, 2);
    };
@endphp

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <h3 class="sx-box-title" style="flex:none;margin:0;">
            <i class="fa fa-file-text-o"></i> {{ $note->number }}
            <span class="label label-{{ $note->isVoided() ? 'danger' : ($note->status === 'draft' ? 'default' : 'success') }}">
                {{ ucfirst($note->status) }}
            </span>
        </h3>
        <div class="sx-toolbar-actions">
            <a href="{{ route('sales.credit-notes.print', $note) }}" class="btn btn-default" target="_blank">
                <i class="fa fa-print"></i> Print
            </a>
            @if(!empty($canPost))
                <form method="post" action="{{ route('sales.credit-notes.post', $note) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn sx-btn-gold" onclick="return confirm('Post this credit note?');">
                        <i class="fa fa-check"></i> Post
                    </button>
                </form>
            @endif
            @if(!empty($canVoid))
                <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#sx-cn-void-modal">
                    <i class="fa fa-ban"></i> Void
                </button>
            @endif
            <a href="{{ route('sales.credit-notes') }}" class="btn btn-default"><i class="fa fa-list"></i> List</a>
        </div>
    </div>

    <div class="sx-box-body">
        <div class="row">
            <div class="col-md-4">
                <div><strong>Date:</strong> {{ optional($note->credit_date)->format('d-m-Y') }}</div>
                <div><strong>Invoice:</strong>
                    @if($note->sale)
                        <a href="{{ route('sales.show', $note->sale) }}">{{ $note->sale->documentNumber() }}</a>
                    @else
                        -
                    @endif
                </div>
                <div><strong>Customer:</strong>
                    {{ optional($note->customer)->name
                        ?: (optional($note->sale)->customerDisplayName() ?: 'WALK-IN') }}
                </div>
            </div>
            <div class="col-md-4">
                <div><strong>Reason:</strong> {{ $note->reason ?: '-' }}</div>
                <div><strong>Notes:</strong> {{ $note->notes ?: '-' }}</div>
                <div><strong>Created by:</strong> {{ optional($note->user)->name ?: '-' }}</div>
            </div>
            <div class="col-md-4">
                <div><strong>Restore stock:</strong> {{ $note->restore_stock ? 'Yes' : 'No' }}</div>
                <div><strong>Stock restored:</strong> {{ $note->stock_restored ? 'Yes' : 'No' }}</div>
                <div><strong>Accounting posted:</strong> {{ $note->accounting_posted ? 'Yes' : 'No' }}</div>
                <div><strong>Loyalty adjusted:</strong> {{ $note->loyalty_adjusted ? 'Yes' : 'No' }}</div>
                @if($note->isVoided())
                    <div><strong>Voided:</strong> {{ optional($note->voided_at)->format('d-m-Y H:i') }}</div>
                    <div><strong>Voided by:</strong> {{ optional($note->voidedBy)->name ?: '-' }}</div>
                    <div><strong>Void reason:</strong> {{ $note->void_reason }}</div>
                @endif
            </div>
        </div>

        <div class="table-responsive" style="margin-top:18px;">
            <table class="table table-bordered sx-gold-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Discount</th>
                        <th>Tax</th>
                        <th>Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($note->items as $i => $item)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ optional($item->product)->name ?: (optional($item->saleItem)->name ?: ('Item #' . $item->sale_item_id)) }}</td>
                            <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') }}</td>
                            <td>{{ $money($item->unit_price) }}</td>
                            <td>{{ $money($item->discount_amount) }}</td>
                            <td>{{ $money($item->tax_amount) }}</td>
                            <td>{{ $money($item->line_total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="6" class="text-right">Subtotal</th>
                        <th>{{ $money($note->subtotal) }}</th>
                    </tr>
                    <tr>
                        <th colspan="6" class="text-right">Tax</th>
                        <th>{{ $money($note->tax_amount) }}</th>
                    </tr>
                    <tr>
                        <th colspan="6" class="text-right">Total</th>
                        <th>{{ $money($note->total) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

@if(!empty($canVoid))
<div class="modal fade" id="sx-cn-void-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="post" action="{{ route('sales.credit-notes.void', $note) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-ban"></i> Void Credit Note</h4>
            </div>
            <div class="modal-body">
                <p>This will reverse stock (if restored), accounting, and loyalty adjustments for posted notes.</p>
                <div class="form-group">
                    <label>Void Reason <span class="sx-req">*</span></label>
                    <textarea name="void_reason" class="form-control" rows="3" required maxlength="500"
                              placeholder="Reason for void"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fa fa-ban"></i> Void</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
