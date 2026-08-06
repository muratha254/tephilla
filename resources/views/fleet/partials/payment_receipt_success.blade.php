@if (session('success'))
<div class="fleet-alert fleet-alert-success fleet-payment-success-alert">
    <div class="fleet-payment-success-message">{{ session('success') }}</div>
    @if (session('payment_receipt'))
        @php $receipt = session('payment_receipt'); @endphp
        <div class="fleet-payment-receipt-actions">
            <span class="fleet-payment-receipt-label">Receipt <strong>{{ $receipt['receipt_number'] }}</strong></span>
            <a href="{{ $receipt['pdf_url'] }}" class="fleet-btn fleet-btn-sm fleet-btn-receipt-pdf" target="_blank" rel="noopener">
                <i class="fa fa-download"></i> Download PDF
            </a>
            <a href="{{ $receipt['print_url'] }}" class="fleet-btn fleet-btn-sm fleet-btn-receipt-print" target="_blank" rel="noopener">
                <i class="fa fa-print"></i> Print Receipt
            </a>
        </div>
    @endif
</div>
@endif
