@extends('layouts.master')

@section('title')
    Payment Details - {{ $payment->reference_number }}
@endsection

@section('breadcrumb')
    @parent
    <li><a href="{{ route('payment.index') }}">Payment List</a></li>
    <li class="active">Payment Details</li>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
@endpush

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Payment Details - {{ $payment->reference_number }}</h3>
                <div class="box-tools pull-right">
                    <a href="{{ route('payment.index') }}" class="btn btn-default btn-sm">
                        <i class="fa fa-arrow-left"></i> Back to List
                    </a>
                    <button type="button" id="btn-export-pdf" class="btn btn-danger btn-sm">
                        <i class="fa fa-file-pdf-o"></i> Print PDF
                    </button>
                    <a href="{{ route('payment.export-excel', $payment->id) }}" class="btn btn-success btn-sm">
                        <i class="fa fa-file-excel-o"></i> Export Excel
                    </a>
                </div>
            </div>
            <div class="box-body">
                <!-- Supplier Name and Date Range (use same dates as filtered items: request or notes) -->
                <div class="row">
                    <div class="col-md-12">
                        @php
                            $storedNotes = $payment->notes ? json_decode($payment->notes, true) : null;
                            $displayStart = isset($startDate) && $startDate ? $startDate : ($storedNotes['start_date'] ?? ($payment->date ? date('Y-m-d', strtotime($payment->date)) : date('Y-m-d')));
                            $displayEnd = isset($endDate) && $endDate ? $endDate : ($storedNotes['end_date'] ?? ($payment->date ? date('Y-m-d', strtotime($payment->date)) : date('Y-m-d')));
                        @endphp
                        <h3 style="margin-top: 0;">{{ $payment->supplier->nama ?? 'N/A' }}</h3>
                        <p style="margin-top: 10px; margin-bottom: 20px;">
                            <strong>Start Date:</strong> {{ date('d/m/Y', strtotime($displayStart)) }} | 
                            <strong>End Date:</strong> {{ date('d/m/Y', strtotime($displayEnd)) }}
                        </p>
                    </div>
                </div>

                <!-- Items Paid For -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-warning">
                            <div class="box-header with-border">
                                <h3 class="box-title">Items Paid For</h3>
                                <div class="box-tools pull-right">
                                    <button type="button" id="btn-export-pdf-items" class="btn btn-danger btn-sm">
                                        <i class="fa fa-file-pdf-o"></i> Print PDF
                                    </button>
                                    <a href="{{ route('payment.export-excel', $payment->id) }}" class="btn btn-success btn-sm">
                                        <i class="fa fa-file-excel-o"></i> Export Excel
                                    </a>
                                </div>
                            </div>
                            <div class="box-body">
                                @if(count($items) > 0)
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th width="5%">#</th>
                                            <th>Product Code</th>
                                            <th>Product Name</th>
                                            <th>Quantity</th>
                                            <th>Cost</th>
                                            <th>Total Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($items as $index => $item)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $item['product_code'] }}</td>
                                            <td>{{ $item['product_name'] }}</td>
                                            <td>{{ $item['quantity'] }}</td>
                                            <td>Ksh {{ number_format($item['unit_amount'] ?? 0, 2) }}</td>
                                            <td>Ksh {{ number_format($item['invoice_amount'], 2) }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="5" style="text-align: right;">Total:</th>
                                            <th>Ksh {{ number_format($items->sum('invoice_amount'), 2) }}</th>
                                        </tr>
                                    </tfoot>
                                </table>
                                @else
                                <div class="alert alert-info">
                                    <i class="fa fa-info-circle"></i> No items found for this payment.
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script>
        $(function () {
            function exportPdf() {
                @php
                    $storedNotes = $payment->notes ? json_decode($payment->notes, true) : null;
                    $pdfStart = isset($startDate) && $startDate ? $startDate : ($storedNotes['start_date'] ?? ($payment->date ? date('Y-m-d', strtotime($payment->date)) : date('Y-m-d')));
                    $pdfEnd = isset($endDate) && $endDate ? $endDate : ($storedNotes['end_date'] ?? ($payment->date ? date('Y-m-d', strtotime($payment->date)) : date('Y-m-d')));
                @endphp
                var url = '{{ route('payment.export-pdf', $payment->id) }}?start_date=' + encodeURIComponent('{{ $pdfStart }}') + '&end_date=' + encodeURIComponent('{{ $pdfEnd }}');
                window.open(url, '_blank');
            }

            $('#btn-export-pdf, #btn-export-pdf-items').on('click', function() {
                exportPdf();
            });
        });
    </script>
@endpush





