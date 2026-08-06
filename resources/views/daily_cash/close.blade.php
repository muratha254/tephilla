@extends('layouts.master')

@section('title')
    Close Day
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Close Day</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Close Day - {{ date('F d, Y', strtotime($today->date)) }}</h3>
            </div>
            <div class="box-body">
                <form action="{{ route('daily-cash.close') }}" method="POST" class="form-horizontal">
                    @csrf
                    
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="box box-primary">
                                <div class="box-header">
                                    <h3 class="box-title">Summary</h3>
                                </div>
                                <div class="box-body">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th width="50%">Date:</th>
                                            <td>{{ date('F d, Y', strtotime($today->date)) }}</td>
                                        </tr>
                                        <tr>
                                            <th>Cash on Hand (Opening):</th>
                                            <td><strong>Ksh {{ number_format($today->opening_cash, 2) }}</strong></td>
                                        </tr>
                                        <tr>
                                            <th>Total Sales:</th>
                                            <td><strong>Ksh {{ number_format($totalSales, 2) }}</strong></td>
                                        </tr>
                                        <tr class="bg-success">
                                            <th>Net Sales:</th>
                                            <td><strong>Ksh {{ number_format($netSales, 2) }}</strong></td>
                                        </tr>
                                        <tr>
                                            <th>Opened By:</th>
                                            <td>{{ $today->openedByUser->name ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Opened At:</th>
                                            <td>{{ $today->opened_at ? date('Y-m-d H:i:s', strtotime($today->opened_at)) : 'N/A' }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="notes" class="control-label">Notes (Optional)</label>
                                <textarea name="notes" id="notes" class="form-control" rows="5" placeholder="Enter any notes about the closing..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <div class="col-lg-12">
                            <div class="alert alert-warning">
                                <i class="fa fa-warning"></i> <strong>Warning:</strong> Once you close the day, you will not be able to create new sales for today. Make sure all transactions are completed.
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <div class="col-lg-12">
                            <button type="submit" class="btn btn-success btn-flat btn-lg" onclick="return confirm('Are you sure you want to close the day? Note: If closed by mistake, an admin can reopen it.')">
                                <i class="fa fa-check"></i> Close Day
                            </button>
                            <a href="{{ route('dashboard') }}" class="btn btn-default btn-flat btn-lg">
                                <i class="fa fa-arrow-left"></i> Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

