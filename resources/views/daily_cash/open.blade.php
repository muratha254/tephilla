@extends('layouts.master')

@section('title')
    Open Day - Cash on Hand
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Open Day</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">
                    @if(isset($isReopening) && $isReopening)
                        Reopen Day - Enter Cash on Hand
                    @elseif(isset($isEditing) && $isEditing)
                        Edit Opening Cash (Admin Only)
                    @else
                        Enter Cash on Hand
                    @endif
                </h3>
            </div>
            <div class="box-body">
                <form action="{{ route('daily-cash.open') }}" method="POST" class="form-horizontal">
                    @csrf
                    
                    @if($errors->any())
                    <div class="alert alert-danger">
                        <ul>
                            @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    @if(isset($isReopening) && $isReopening)
                    <div class="alert alert-warning">
                        <i class="fa fa-warning"></i> <strong>Note:</strong> This day was previously closed. You are reopening it. You can update the opening cash amount if needed.
                        @if(isset($existingCash))
                        <br>Previous Opening Cash: <strong>Ksh {{ number_format($existingCash->opening_cash, 2) }}</strong>
                        @endif
                    </div>
                    @elseif(isset($isEditing) && $isEditing)
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> <strong>Note:</strong> You are editing the opening cash for today. This will update the net sales calculation.
                        @if(isset($existingCash))
                        <br>Current Opening Cash: <strong>Ksh {{ number_format($existingCash->opening_cash, 2) }}</strong>
                        <br>Current Total Sales: <strong>Ksh {{ number_format($existingCash->total_sales, 2) }}</strong>
                        <br>Current Net Sales: <strong>Ksh {{ number_format($existingCash->net_sales, 2) }}</strong>
                        @endif
                    </div>
                    @endif

                    <div class="form-group row">
                        <label for="date" class="col-lg-2 control-label">Date</label>
                        <div class="col-lg-8">
                            <input type="date" name="date" id="date" class="form-control" value="{{ date('Y-m-d') }}" readonly>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="opening_cash" class="col-lg-2 control-label">Cash on Hand <span class="text-danger">*</span></label>
                        <div class="col-lg-8">
                            <input type="number" name="opening_cash" id="opening_cash" class="form-control" 
                                step="0.01" min="0" required autofocus 
                                value="{{ isset($existingCash) ? $existingCash->opening_cash : '' }}"
                                placeholder="Enter opening cash amount">
                            <span class="help-block">Enter the amount of cash available at the start of the day.</span>
                        </div>
                    </div>

                    <div class="form-group row">
                        <div class="col-lg-8 col-lg-offset-2">
                            <button type="submit" class="btn btn-primary btn-flat">
                                <i class="fa fa-save"></i> 
                                @if(isset($isReopening) && $isReopening)
                                    Reopen Day
                                @elseif(isset($isEditing) && $isEditing)
                                    Update Opening Cash
                                @else
                                    Open Day
                                @endif
                            </button>
                            <a href="{{ route('dashboard') }}" class="btn btn-default btn-flat">
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

@push('scripts')
<script>
    $(function() {
        $('#opening_cash').focus();
    });
</script>
@endpush

