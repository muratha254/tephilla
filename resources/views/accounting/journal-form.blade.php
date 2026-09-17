@extends('layouts.fleet')

@section('title', 'Journal Entry')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Journal Entry',
    'backUrl' => route('accounting.journal'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'List of Charts of Account'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-money-form-title"><i class="fa fa-camera"></i> Record Journal Entry</div>
    <div class="sx-box-body" style="min-height:auto;">
        <form method="post" action="{{ route('accounting.journal.store') }}" class="form-horizontal sx-journal-record">
            @csrf
            <div class="form-group">
                <label class="col-sm-3 control-label">Trans Date:<span class="sx-req">*</span></label>
                <div class="col-sm-9">
                    <input type="date" name="entry_date" class="form-control" value="{{ old('entry_date', now()->toDateString()) }}" required>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3 control-label">Debit: <span class="sx-je-increased">Increased</span><span class="sx-req">*</span></label>
                <div class="col-sm-9">
                    <select name="debit_account_id" class="form-control" required>
                        <option value="">Select DR Account</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" @if((string) old('debit_account_id') === (string) $account->id) selected @endif>{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3 control-label">Credit: <span class="sx-je-reduced">Reduced</span><span class="sx-req">*</span></label>
                <div class="col-sm-9">
                    <select name="credit_account_id" class="form-control" required>
                        <option value="">Select CR Account</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" @if((string) old('credit_account_id') === (string) $account->id) selected @endif>{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3 control-label">Amount: <span class="sx-req">*</span></label>
                <div class="col-sm-9">
                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="{{ old('amount', '0.00') }}" placeholder="0.00" required>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3 control-label">Narration:<span class="sx-req">*</span></label>
                <div class="col-sm-9">
                    <textarea name="description" class="form-control" rows="2" placeholder="Description" required>{{ old('description') }}</textarea>
                </div>
            </div>
            <div class="sx-journal-record-actions">
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Submit</button>
                <a href="{{ route('accounting.journal') }}" class="btn btn-danger"><i class="fa fa-times"></i> Close</a>
            </div>
        </form>
    </div>
</div>
@endsection
