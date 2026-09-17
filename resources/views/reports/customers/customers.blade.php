@extends('layouts.fleet')

@section('title', 'Customers Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Customers Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Customers Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.customers.customers') }}" class="sx-acc-filter">
            <input type="hidden" name="show" value="1">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Customers</label>
                        <select name="customer_id" class="form-control">
                            <option value="">~~All Customers~~</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @if((string) $customerId === (string) $customer->id) selected @endif>{{ $customer->name }}</option>
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
            <div>
                <button type="button" class="btn btn-info btn-sm" id="sx-cr-excel"><i class="fa fa-file-excel-o"></i> Excel</button>
            </div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-cr-table">
                    <thead>
                        <tr><th colspan="10" class="text-center">CUSTOMERS REPORT</th></tr>
                        <tr>
                            <th>#</th>
                            <th>Branch</th>
                            <th>CustomerCode</th>
                            <th>CustomerName</th>
                            <th>PhoneNo</th>
                            <th>RegDate</th>
                            <th>Registered By</th>
                            <th class="text-right">Savings</th>
                            <th class="text-right">Loyalty Points</th>
                            <th class="text-right">Balances</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                <td>{{ $row['branch'] }}</td>
                                <td>{{ $row['code'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['phone'] }}</td>
                                <td>{{ $row['reg_date'] }}</td>
                                <td>{{ $row['registered_by'] }}</td>
                                <td class="text-right">{{ number_format($row['savings'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['loyalty'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['balance'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10">No customers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

@if($generated)
@include('reports.summary.partials.export-scripts', [
    'tableId' => 'sx-cr-table',
    'printId' => 'sx-cr-print-unused',
    'excelId' => 'sx-cr-excel',
    'title' => 'Customers Report',
    'filename' => 'customers-report',
])
@endif
