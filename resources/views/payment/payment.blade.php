@extends('layouts.master')

@section('title', 'Payment Page')

@push('css')
<style>
    .tampil-bayar {
        font-size: 5em;
        text-align: center;
        height: 100px;
    }
    .tampil-terbilang {
        padding: 10px;
        background: #f0f0f0;
    }
    .table-pembelian tbody tr:last-child {
        display: none;
    }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Payment for Supplier: {{ $supplier->nama }}</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-lg-6">
                        <h4>Supplier Information</h4>
                        <p><strong>Telephone:</strong> {{ $supplier->telepon }}</p>
                        <p><strong>Address:</strong> {{ $supplier->alamat }}</p>
                    </div>
                    <div class="col-lg-6">
                        <h4>Total Payment</h4>
                        <input type="number" id="totalPayment" class="form-control" placeholder="Enter total payment" min="0" step="0.01">
                    </div>
                </div>

                <h4>Invoices</h4>
                <table id="invoiceTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Invoice Number</th>
                            <th>Description</th>
                            <th>Total Amount</th>
                            <th>Amount Due</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $invoice)
                        <tr>
                            <td>{{ $invoice->number }}</td>
                            <td>{{ $invoice->description }}</td>
                            <td>{{ number_format($invoice->total_amount, 2) }}</td>
                            <td class="amount-due">{{ number_format($invoice->amount_due, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="box-footer">
                <button type="button" class="btn btn-primary btn-flat" id="processPayment">Process Payment</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    $(document).ready(function () {
        $('#processPayment').on('click', function () {
            var totalPayment = parseFloat($('#totalPayment').val()) || 0;
            var invoiceData = [];

            $('#invoiceTable tbody tr').each(function () {
                var invoiceNumber = $(this).find('td:eq(0)').text();
                var amountDue = parseFloat($(this).find('.amount-due').text().replace(/,/g, '')) || 0;

                if (amountDue > 0) {
                    invoiceData.push({
                        invoice_number: invoiceNumber,
                        amount_due: amountDue
                    });
                }
            });

            // Here you would typically send the data to the server
            console.log('Processing payment for:', totalPayment, invoiceData);
            // You can use AJAX to send this data to your backend for processing
        });
    });
</script>
@endpush