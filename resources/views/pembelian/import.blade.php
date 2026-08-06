@extends('layouts.master')

@section('title')
    Import Purchases
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Import Purchases</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Import Purchases from Excel</h3>
            </div>
            <div class="box-body">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <h4><i class="icon fa fa-check"></i> Success!</h4>
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('errors') && count(session('errors')) > 0)
                    <div class="alert alert-warning alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <h4><i class="icon fa fa-warning"></i> Import Errors ({{ count(session('errors')) }})</h4>
                        <ul style="max-height: 300px; overflow-y: auto;">
                            @foreach(session('errors') as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(session('warnings') && count(session('warnings')) > 0)
                    <div class="alert alert-info alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <h4><i class="icon fa fa-info-circle"></i> Import Warnings ({{ count(session('warnings')) }})</h4>
                        <ul style="max-height: 200px; overflow-y: auto;">
                            @foreach(session('warnings') as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <h4><i class="icon fa fa-ban"></i> Error!</h4>
                        <ul>
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="alert alert-info">
                    <h4><i class="icon fa fa-info-circle"></i> Instructions</h4>
                    <p>Upload an Excel file (.xlsx or .xls) with the following columns:</p>
                    <ul>
                        <li><strong>Buying pri</strong> - Buying price per unit</li>
                        <li><strong>Selling Pri</strong> - Selling price per unit</li>
                        <li><strong>Commo</strong> - Product/Commodity name</li>
                        <li><strong>Supplier r</strong> - Supplier name</li>
                        <li><strong>Means of</strong> - Payment method (optional, not used for purchase import)</li>
                        <li><strong>STOCK IN</strong> - Quantity purchased</li>
                        <li><strong>Shops</strong> - Shop name</li>
                        <li><strong>DATEsubmitted</strong> - Purchase date</li>
                    </ul>
                    <p><strong>Note:</strong> Purchases will be grouped by date and supplier. Items with the same purchase date and supplier will be combined into one purchase record.</p>
                </div>

                <form action="{{ route('pembelian.import.process') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label for="file">Select Excel File</label>
                        <input type="file" name="file" id="file" class="form-control" accept=".xlsx,.xls" required>
                        <small class="help-block">Maximum file size: 10MB</small>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-upload"></i> Import Purchases
                        </button>
                        <a href="{{ route('pembelian.index') }}" class="btn btn-default">
                            <i class="fa fa-arrow-left"></i> Back to Purchase List
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection


