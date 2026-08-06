@extends('layouts.master')

@section('title')
    Daily Closing Report
@endsection

@section('breadcrumb')
    @parent
    <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="active">Closing Report</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Daily Closing Report - {{ date('F d, Y', strtotime($dailyCash->date)) }}</h3>
                <div class="pull-right">
                    <a href="{{ route('daily-cash.export-pdf', $dailyCash->id) }}" class="btn btn-primary btn-flat" target="_blank">
                        <i class="fa fa-file-pdf-o"></i> Print PDF
                    </a>
                </div>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-lg-6">
                        <table class="table table-bordered">
                            <tr>
                                <th width="40%">Date:</th>
                                <td>{{ date('F d, Y', strtotime($dailyCash->date)) }}</td>
                            </tr>
                            <tr>
                                <th>Cash on Hand (Opening):</th>
                                <td><strong>Ksh {{ number_format($dailyCash->opening_cash, 2) }}</strong></td>
                            </tr>
                            <tr>
                                <th>Total Sales:</th>
                                <td><strong>Ksh {{ number_format($dailyCash->total_sales, 2) }}</strong></td>
                            </tr>
                            <tr class="bg-success">
                                <th>Net Sales:</th>
                                <td><strong>Ksh {{ number_format($dailyCash->net_sales, 2) }}</strong></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    @if($dailyCash->is_closed)
                                        <span class="label label-success">Closed</span>
                                    @else
                                        <span class="label label-warning">Open</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-lg-6">
                        <table class="table table-bordered">
                            <tr>
                                <th width="40%">Opened By:</th>
                                <td>{{ $dailyCash->openedByUser->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Opened At:</th>
                                <td>{{ $dailyCash->opened_at ? date('Y-m-d H:i:s', strtotime($dailyCash->opened_at)) : 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Closed By:</th>
                                <td>{{ $dailyCash->closedByUser->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Closed At:</th>
                                <td>{{ $dailyCash->closed_at ? date('Y-m-d H:i:s', strtotime($dailyCash->closed_at)) : 'N/A' }}</td>
                            </tr>
                            @if($dailyCash->notes)
                            <tr>
                                <th>Notes:</th>
                                <td>{{ $dailyCash->notes }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        @if(auth()->user()->hasRole('admin') && $dailyCash->is_closed)
                        <form action="{{ route('daily-cash.reopen', $dailyCash->id) }}" method="POST" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to reopen this day? This will allow sales to be created for this day again.');">
                            @csrf
                            <button type="submit" class="btn btn-warning btn-flat">
                                <i class="fa fa-unlock"></i> Reopen Day (Admin Only)
                            </button>
                        </form>
                        @endif
                        <a href="{{ route('dashboard') }}" class="btn btn-default btn-flat">
                            <i class="fa fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

