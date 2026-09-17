@extends('layouts.fleet')

@section('title', 'Account Balances')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Account Balances',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'List of Charts of Accounts'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <button type="button" class="btn btn-success" data-toggle="modal" data-target="#sx-statement-modal">
            <i class="fa fa-file-text-o"></i> Generate Statement
        </button>
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'bal-table'])
        <div class="table-responsive">
            <table id="bal-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="bal-check-all"></th>
                        <th>Account Name</th>
                        <th>Type</th>
                        <th>Current Balance</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $row)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="bal-row-check" value="{{ $row->id }}"></td>
                            <td>{{ $row->name }}</td>
                            <td>{{ optional(optional($row->subType)->accountType)->name }}</td>
                            <td data-order="{{ $row->current_balance }}">Ksh {{ number_format($row->current_balance, 2) }}</td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">Action <span class="caret"></span></button>
                                    <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                        <li><a href="{{ route('accounting.combined-gl', ['from' => now()->startOfYear()->toDateString(), 'to' => now()->toDateString(), 'show' => 1]) }}"><i class="fa fa-list"></i> Ledger</a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="sx-statement-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="get" action="{{ route('accounting.statement') }}" class="modal-content sx-conversion-modal sx-statement-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
                <h4 class="modal-title">Payments Accounts Statements</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>From Date</label>
                            <input type="date" name="from" class="form-control" value="{{ now()->toDateString() }}" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>To Date</label>
                            <input type="date" name="to" class="form-control" value="{{ now()->toDateString() }}" required>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Accounts</label>
                    <select name="account_id" class="form-control" required>
                        <option value="">---Select Account---</option>
                        @foreach($accounts as $row)
                            <option value="{{ $row->id }}">{{ $row->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Submit</button>
                <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
            </div>
        </form>
    </div>
</div>
@endsection

@include('accounting.partials.datatable-js', [
    'tableId' => 'bal-table',
    'lengthId' => 'bal-table-length',
    'searchId' => 'bal-table-search',
    'exportId' => 'bal-table-export',
    'colvisId' => 'bal-table-colvis',
    'checkAll' => 'bal-check-all',
    'rowCheck' => 'bal-row-check',
    'title' => 'Account Balances',
    'filename' => 'account-balances',
    'noSort' => [0, 4],
])
