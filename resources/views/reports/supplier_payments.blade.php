@extends('layouts.master')

@section('title')
    Supplier Payment Report
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Supplier Payment Report</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Supplier Payment Report</h3>
            </div>
            <div class="box-body">
                @if(session('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                    </div>
                @endif
                
                <form action="{{ route('reports.supplier-payments') }}" method="GET" id="filter-form" class="mb-3">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" name="start_date" id="start_date" class="form-control"
                                    value="{{ request('start_date', date('Y-m-01')) }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" name="end_date" id="end_date" class="form-control"
                                    value="{{ request('end_date', date('Y-m-d')) }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="supplier_id">Supplier</label>
                                <select name="supplier_id" id="supplier_id" class="form-control select2">
                                    <option value="">All Suppliers</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id_supplier }}"
                                            {{ request('supplier_id') == $supplier->id_supplier ? 'selected' : '' }}>
                                            {{ $supplier->nama }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-search"></i> Filter
                                    </button>
                                    <button type="button" class="btn btn-success" onclick="exportReport()">
                                        <i class="fa fa-file-pdf-o"></i> Export PDF
                                    </button>
                                    <button type="button" class="btn btn-primary" onclick="exportExcel()">
                                        <i class="fa fa-file-excel-o"></i> Export Excel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-bordered" id="payments-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Reference #</th>
                                <th>Supplier</th>
                                <th class="text-right">Amount</th>
                                <th>Payment Method</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($payments as $payment)
                            <tr>
                                <td>{{ date('Y-m-d', strtotime($payment->date)) }}</td>
                                <td>{{ $payment->reference_number }}</td>
                                <td>{{ $payment->supplier->nama ?? '' }}</td>
                                <td class="text-right">{{ number_format($payment->amount, 2) }}</td>
                                <td>{{ $payment->payment_method ?? '' }}</td>
                                <td>{{ $payment->notes ?? '' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-right"><strong>Total:</strong></td>
                                <td class="text-right"><strong>{{ number_format($payments->sum('amount'), 2) }}</strong></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('.select2').select2();

    $('#payments-table').DataTable({
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'print'
        ],
        order: [[0, 'desc']],
        pageLength: 25
    });

    // Validate date range
    $('#filter-form').on('submit', function(e) {
        var startDate = new Date($('#start_date').val());
        var endDate = new Date($('#end_date').val());

        if (endDate < startDate) {
            e.preventDefault();
            alert('End date cannot be earlier than start date');
            return false;
        }
    });
});

function exportReport() {
    var startDate = $('#start_date').val();
    var endDate = $('#end_date').val();
    var supplierId = $('#supplier_id').val();

    var url = "{{ route('reports.supplier-payments.export-pdf') }}";
    url += '?start_date=' + startDate + '&end_date=' + endDate;
    if (supplierId) {
        url += '&supplier_id=' + supplierId;
    }

    window.location.href = url;
}

function exportExcel() {
    var startDate = $('#start_date').val();
    var endDate = $('#end_date').val();
    var supplierId = $('#supplier_id').val();

    var url = "{{ route('reports.supplier-payments.export-excel') }}";
    url += '?start_date=' + startDate + '&end_date=' + endDate;
    if (supplierId) {
        url += '&supplier_id=' + supplierId;
    }

    window.location.href = url;
}
</script>
@endpush
