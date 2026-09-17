@extends('layouts.fleet')

@section('title', 'Employee Sales Summary Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Employee Sales Summary Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Employee Sales Summary Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.summary.employee-branch') }}" class="sx-acc-filter">
            <input type="hidden" name="show" value="1">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>From Date</label>
                        <input type="date" name="from" class="form-control" value="{{ $from }}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>To Date</label>
                        <input type="date" name="to" class="form-control" value="{{ $to }}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Employees:</label>
                        <select name="user_id" class="form-control">
                            <option value="">~~Select Employee~~</option>
                            @foreach($staff as $member)
                                <option value="{{ $member->id }}" @if((string) $userId === (string) $member->id) selected @endif>{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="sx-form-actions">
                <button type="submit" class="btn btn-success">Generate Statement</button>
                <a href="{{ route('reports.summary.employee-branch') }}" class="btn btn-warning">Refresh</a>
            </div>
        </form>
    </div>
</div>

<div class="sx-acc-card">
    <div class="sx-acc-result-head">
        <span>Records Table</span>
        @if($generated)
            <div>
                <button type="button" class="btn btn-info btn-sm" id="sx-emp-print"><i class="fa fa-print"></i> Print</button>
                <button type="button" class="btn btn-success btn-sm" id="sx-emp-excel"><i class="fa fa-file-excel-o"></i> Excel</button>
            </div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-emp-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Employee</th>
                            <th>Branch</th>
                            <th class="text-right">Invoices</th>
                            <th class="text-right">Total Sales</th>
                            <th class="text-right">Paid</th>
                            <th class="text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $row['employee'] }}</td>
                                <td>{{ $row['branch'] }}</td>
                                <td class="text-right">{{ number_format($row['invoices']) }}</td>
                                <td class="text-right">{{ number_format($row['total'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['paid'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['balance'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7">No records found for the selected period.</td></tr>
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
    'tableId' => 'sx-emp-table',
    'printId' => 'sx-emp-print',
    'excelId' => 'sx-emp-excel',
    'title' => 'Employee Sales Summary Report',
    'filename' => 'employee-sales-summary',
])
@endif
