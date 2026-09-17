@extends('layouts.fleet')
@section('title', 'Stock Transfers')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Stock Transfers',
    'subtitle' => 'Transfer stock between branches',
    'backUrl' => route('stock.manager'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Stock Transfers'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div></div>
        @if($canTransfer)
            <div class="sx-toolbar-actions">
                <a href="{{ route('stock.transfers.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> New Transfer</a>
            </div>
        @endif
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'transfer-table'])
        <div class="table-responsive">
            <table id="transfer-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Date</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Posted By</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transfers as $row)
                        <tr>
                            <td>{{ $row->number }}</td>
                            <td data-order="{{ optional($row->transfer_date)->format('Y-m-d') }}">{{ optional($row->transfer_date)->format('Y-m-d') }}</td>
                            <td>{{ optional($row->fromBranch)->name }}</td>
                            <td>{{ optional($row->toBranch)->name }}</td>
                            <td>{{ $row->items->count() }}</td>
                            <td>
                                @if($row->status === 'completed')
                                    <span class="sx-status-active">Completed</span>
                                @elseif($row->status === 'cancelled')
                                    <span class="sx-status-inactive">Cancelled</span>
                                @else
                                    <span class="label label-warning">{{ ucfirst($row->status) }}</span>
                                @endif
                            </td>
                            <td>{{ optional($row->user)->name }}</td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">Action <span class="caret"></span></button>
                                    <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                        <li><a href="{{ route('stock.transfers.show', $row) }}"><i class="fa fa-eye"></i> View</a></li>
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
@endsection

@include('accounting.partials.datatable-js', [
    'tableId' => 'transfer-table', 'lengthId' => 'transfer-table-length', 'searchId' => 'transfer-table-search',
    'exportId' => 'transfer-table-export', 'colvisId' => 'transfer-table-colvis',
    'title' => 'Stock Transfers', 'filename' => 'stock-transfers', 'noSort' => [7], 'order' => [[1, 'desc']],
])
