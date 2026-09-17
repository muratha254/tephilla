@extends('layouts.fleet')

@section('title', 'Expense Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Expense Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Expense Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.expenses') }}" class="sx-acc-filter" id="sx-expense-form">
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
                        <label>Category Name</label>
                        <select name="category_id" class="form-control">
                            <option value="">All Categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @if((string) $categoryId === (string) $category->id) selected @endif>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="sx-form-actions">
                <button type="submit" class="btn btn-info"><i class="fa fa-filter"></i> Filter</button>
                <a href="{{ route('reports.expenses.pdf', ['from' => $from, 'to' => $to, 'category_id' => $categoryId]) }}" class="btn btn-success" id="sx-expense-pdf">
                    <i class="fa fa-file-pdf-o"></i> Generate Pdf
                </a>
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
                <button type="button" class="btn btn-info btn-sm" id="sx-ex-excel"><i class="fa fa-file-excel-o"></i> Excel</button>
            </div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-ex-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Branch</th>
                            <th>Expense Code</th>
                            <th>Expense Date</th>
                            <th>Expense for</th>
                            <th class="text-right">Amount</th>
                            <th>Note</th>
                            <th>Created by</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                <td>{{ $row['branch'] }}</td>
                                <td>{{ $row['code'] }}</td>
                                <td>{{ $row['expense_date'] }}</td>
                                <td>{{ $row['expense_for'] }}</td>
                                <td class="text-right">{{ number_format($row['amount'], 2) }}</td>
                                <td>{{ $row['note'] }}</td>
                                <td>{{ $row['created_by'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8">No expense records found for the selected period.</td></tr>
                        @endforelse
                        @if($rows->isNotEmpty())
                            <tr>
                                <th colspan="5" class="text-right">Total</th>
                                <th class="text-right">{{ number_format($rows->sum('amount'), 2) }}</th>
                                <th colspan="2"></th>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

@if($generated)
@include('reports.summary.partials.export-scripts', [
    'tableId' => 'sx-ex-table',
    'printId' => 'sx-ex-print-unused',
    'excelId' => 'sx-ex-excel',
    'title' => 'Expense Report',
    'filename' => 'expense-report',
])
@endif
