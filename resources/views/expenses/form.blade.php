@extends('layouts.fleet')

@php
    $isEdit = $expense->exists;
    $title = $isBill ? 'Bill / Expense' : 'Expense';
@endphp

@section('title', $isEdit ? 'Update Expense' : 'New Expense')

@section('content')
@include('layouts.partials.page-header', [
    'title' => $title,
    'subtitle' => 'Add/Update Expense',
    'backUrl' => route('expenses.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Expenses List', 'url' => route('expenses.index')],
        ['label' => 'Expense'],
    ],
])

<form class="sx-item-form" method="post" action="{{ $isEdit ? route('expenses.update', $expense) : route('expenses.store') }}" enctype="multipart/form-data">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif
    <input type="hidden" name="entry_type" value="{{ old('entry_type', $expense->entry_type ?: ($isBill ? 'bill' : 'direct')) }}">

    <div class="sx-box">
        <div class="sx-box-body" style="min-height:auto;">
            @if($errors->any())
                <p class="sx-valid-hint">Please Enter Valid Data</p>
            @endif

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Expense Date <span class="sx-req">*</span></label>
                        <input type="date" name="expense_date" class="form-control" value="{{ old('expense_date', optional($expense->expense_date)->format('Y-m-d') ?: now()->toDateString()) }}" required>
                    </div>
                    <div class="form-group">
                        <label>Expense Category <span class="sx-req">*</span></label>
                        <div class="input-group">
                            <select name="expense_category_id" id="expense_category_id" class="form-control" required>
                                <option value="">--Select Expense--</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @if((string) old('expense_category_id', $expense->expense_category_id) === (string) $category->id) selected @endif>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-info sx-plus-btn" id="sx-add-category" title="Add category"><i class="fa fa-plus"></i></button>
                            </span>
                        </div>
                    </div>
                    @if($isBill)
                        <div class="form-group">
                            <label>Vendor <span class="sx-req">*</span></label>
                            <select name="vendor_id" class="form-control" required>
                                <option value="">--Select Vendor--</option>
                                @foreach($vendors as $vendor)
                                    <option value="{{ $vendor->id }}" @if((string) old('vendor_id', $expense->vendor_id) === (string) $vendor->id) selected @endif>{{ $vendor->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="form-group">
                        <label>Paying Account @if(!$isBill)<span class="sx-req">*</span>@endif</label>
                        <select name="paying_account" class="form-control" @if(!$isBill) required @endif>
                            <option value="">--Select Account--</option>
                            @foreach($accounts as $value => $label)
                                <option value="{{ $value }}" @if(old('paying_account', $expense->paying_account ?: $expense->payment_method) === $value) selected @endif>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount <span class="sx-req">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="Amount" value="{{ old('amount', $expense->amount) }}" required>
                    </div>
                    <div class="form-group">
                        <label>Voucher No</label>
                        <input type="text" name="voucher_no" class="form-control" placeholder="Voucher No" value="{{ old('voucher_no', $expense->voucher_no) }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Upload File</label>
                        <input type="file" name="attachment" class="form-control">
                        @if($expense->attachment_path)
                            <a href="{{ asset('storage/' . $expense->attachment_path) }}" target="_blank" class="sx-hint">View current file</a>
                        @endif
                    </div>
                    <div class="form-group">
                        <label>VAT <span class="sx-req">*</span></label>
                        <select name="vat_type" class="form-control" required>
                            @foreach($vatTypes as $value => $label)
                                <option value="{{ $value }}" @if(old('vat_type', $expense->vat_type ?: 'exempt') === $value) selected @endif>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Note <span class="sx-req">*</span></label>
                        <textarea name="notes" class="form-control" rows="6" placeholder="Note" required>{{ old('notes', $expense->notes) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="sx-form-actions">
                <button type="submit" class="btn btn-success">Save</button>
                <a href="{{ route('expenses.index') }}" class="btn btn-warning">Close</a>
            </div>
        </div>
    </div>
</form>

<div class="modal fade" id="sx-quick-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Add Expense Category</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" id="sx-quick-name" class="form-control" placeholder="Category name">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="sx-quick-save">Save</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var url = @json(route('expenses.categories.store'));
    document.getElementById('sx-add-category').addEventListener('click', function () {
        document.getElementById('sx-quick-name').value = '';
        $('#sx-quick-modal').modal('show');
        setTimeout(function () { document.getElementById('sx-quick-name').focus(); }, 300);
    });
    document.getElementById('sx-quick-save').addEventListener('click', function () {
        var name = document.getElementById('sx-quick-name').value.trim();
        if (!name) return;
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token
            },
            body: JSON.stringify({ name: name })
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok) throw data;
                return data;
            });
        }).then(function (data) {
            var select = document.getElementById('expense_category_id');
            var option = document.createElement('option');
            option.value = data.id;
            option.textContent = data.label || data.name;
            option.selected = true;
            select.appendChild(option);
            $('#sx-quick-modal').modal('hide');
        }).catch(function () {
            alert('Could not save the category. Check the name and try again.');
        });
    });
})();
</script>
@endpush
