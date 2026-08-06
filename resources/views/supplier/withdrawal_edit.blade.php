@extends('layouts.master')

@section('title')
    Edit supplier withdrawal
@endsection

@section('breadcrumb')
    @parent
    <li><a href="{{ route('supplier.withdrawals') }}">Supplier Withdrawals List</a></li>
    <li class="active">Edit withdrawal</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-10 col-lg-offset-1">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-edit"></i> Edit withdrawal line</h3>
                <div class="box-tools">
                    <a href="{{ route('supplier.withdrawals') }}" class="btn btn-default btn-flat"><i class="fa fa-arrow-left"></i> Back to list</a>
                </div>
            </div>
            <form action="{{ route('supplier.withdrawals.update', $withdrawal) }}" method="post">
                @csrf
                @method('PUT')
                <div class="box-body">
                    @if ($errors->any())
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            {{ $errors->first('error') ?: $errors->first() }}
                        </div>
                    @endif

                    <p class="text-muted">
                        Withdrawal batch <strong>{{ $withdrawal->withdrawal_number ?? 'N/A' }}</strong> — product and supplier are fixed; adjust quantity, date, receipt, or notes if this line was entered incorrectly.
                        Stock is updated by the difference in quantity (current buying price is used for the line total).
                    </p>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Supplier</label>
                                <p class="form-control-static">{{ $withdrawal->supplier->nama ?? 'N/A' }}</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Product</label>
                                <p class="form-control-static">
                                    {{ $withdrawal->produk->nama_produk ?? 'N/A' }}
                                    @if(!empty($withdrawal->produk->kode_produk))
                                        <span class="text-muted">({{ $withdrawal->produk->kode_produk }})</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label for="quantity">Quantity <span class="text-red">*</span></label>
                                <input type="number" name="quantity" id="quantity" class="form-control" min="1" step="1" required
                                    value="{{ old('quantity', $withdrawal->quantity) }}">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label for="withdrawal_date">Withdrawal date <span class="text-red">*</span></label>
                                <input type="date" name="withdrawal_date" id="withdrawal_date" class="form-control" required
                                    value="{{ old('withdrawal_date', $withdrawal->withdrawal_date ? $withdrawal->withdrawal_date->format('Y-m-d') : '') }}">
                            </div>
                        </div>
                        @if(!empty($hasReceiptNo))
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label for="receipt_no">Receipt number <span class="text-red">*</span></label>
                                <input type="text" name="receipt_no" id="receipt_no" class="form-control" maxlength="120" required
                                    value="{{ old('receipt_no', $withdrawal->receipt_no ?? '') }}">
                            </div>
                        </div>
                        @endif
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="reason">Reason</label>
                                <input type="text" name="reason" id="reason" class="form-control" maxlength="255"
                                    value="{{ old('reason', $withdrawal->reason) }}">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea name="notes" id="notes" class="form-control" rows="2">{{ old('notes', $withdrawal->notes) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary btn-flat"><i class="fa fa-save"></i> Save changes</button>
                    <a href="{{ route('supplier.withdrawals') }}" class="btn btn-default btn-flat">Cancel</a>
                </div>
            </form>

            <div class="box box-danger" style="margin-top: 20px;">
                <div class="box-header with-border">
                    <h3 class="box-title">Undo entire withdrawal</h3>
                </div>
                <div class="box-body">
                    <p class="text-muted">
                        This removes <strong>every line</strong> in batch <strong>{{ $withdrawal->withdrawal_number ?? 'N/A' }}</strong> (all products), restores stock for each, and deletes the withdrawal records. Use this if the whole withdrawal was recorded by mistake.
                    </p>
                    <form action="{{ route('supplier.withdrawals.undo-batch', $withdrawal) }}" method="post"
                        onsubmit="return confirm({{ json_encode('Undo the entire withdrawal batch '.($withdrawal->withdrawal_number ?? '').'? All lines will be removed and stock restored.') }});">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-flat"><i class="fa fa-undo"></i> Undo entire batch</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
