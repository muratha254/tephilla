@extends('layouts.fleet')

@section('title', 'User Logs Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'User Logs Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'User Logs Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.user-logs') }}" class="sx-acc-filter">
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
                        <label>Users</label>
                        <select name="user_id" class="form-control">
                            <option value="">~~All Users~~</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @if((string) $userId === (string) $user->id) selected @endif>{{ $user->name }}</option>
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
                <button type="button" class="btn btn-info btn-sm" id="sx-ul-excel"><i class="fa fa-file-excel-o"></i> Excel</button>
            </div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-ul-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Username</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>IP Address</th>
                            <th>System Used</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                <td>{{ $row['username'] }}</td>
                                <td>{{ $row['date'] }}</td>
                                <td>{{ $row['status'] }}</td>
                                <td>{{ $row['ip'] }}</td>
                                <td>{{ $row['system'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No user log records found for the selected period.</td></tr>
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
    'tableId' => 'sx-ul-table',
    'printId' => 'sx-ul-print-unused',
    'excelId' => 'sx-ul-excel',
    'title' => 'User Logs Report',
    'filename' => 'user-logs-report',
])
@endif
