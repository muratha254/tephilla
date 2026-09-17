@extends('layouts.fleet')

@section('title', 'Sales Return Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Sales Return Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Sales Return Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.sales.returns') }}" class="sx-acc-filter">
            <input type="hidden" name="show" value="1">
            <div class="row">
                <div class="col-md-6"><div class="form-group"><label>From Date</label><input type="date" name="from" class="form-control" value="{{ $from }}" required></div></div>
                <div class="col-md-6"><div class="form-group"><label>To Date</label><input type="date" name="to" class="form-control" value="{{ $to }}" required></div></div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Employee Name</label>
                        <select name="user_id" class="form-control">
                            <option value="">-Select-</option>
                            @foreach($staff as $member)
                                <option value="{{ $member->id }}" @if((string) $userId === (string) $member->id) selected @endif>{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="sx-form-actions">
                <button type="submit" class="btn btn-success">Show</button>
                <a href="{{ route('dashboard') }}" class="btn btn-warning">Close</a>
            </div>
        </form>
    </div>
</div>

<div class="sx-acc-card">
    <div class="sx-acc-result-head">
        <span>Records Table</span>
        @if($generated)
            <div><button type="button" class="btn btn-info btn-sm" id="sx-ret-excel">Excel</button></div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-ret-table">
                    <thead>
                        <tr><th colspan="11" class="text-center">SALES RETURN REPORT</th></tr>
                        <tr>
                            <th>#</th><th>Branch</th><th>Employee</th><th>Invoice Number</th><th>Return Date</th>
                            <th>Date/Time</th><th>Customer Name</th><th>Item Name</th>
                            <th class="text-right">Return Qty</th><th>Note</th><th class="text-right">Stock Value Return</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                <td>{{ $row['branch'] }}</td>
                                <td>{{ $row['employee'] }}</td>
                                <td>{{ $row['invoice'] }}</td>
                                <td>{{ $row['return_date'] }}</td>
                                <td>{{ $row['datetime'] }}</td>
                                <td>{{ $row['customer'] }}</td>
                                <td>{{ $row['item'] }}</td>
                                <td class="text-right">{{ number_format($row['qty'], 2) }}</td>
                                <td>{{ $row['note'] }}</td>
                                <td class="text-right">{{ number_format($row['stock_value'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="11">No records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

@if($generated)
@include('reports.summary.partials.export-scripts', ['tableId' => 'sx-ret-table', 'printId' => 'sx-ret-print-unused', 'excelId' => 'sx-ret-excel', 'title' => 'Sales Return Report', 'filename' => 'sales-return-report'])
@endif
