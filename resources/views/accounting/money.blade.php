@extends('layouts.fleet')

@php
    $tabs = [
        'payments' => ['label' => 'Payments', 'icon' => 'fa-camera'],
        'make' => ['label' => 'Make Payment', 'icon' => 'fa-camera'],
        'receive' => ['label' => 'Receive Payment', 'icon' => 'fa-camera'],
        'open' => ['label' => 'Acc. Open Bal.', 'icon' => 'fa-list'],
        'transfer' => ['label' => 'Funds Transfer', 'icon' => 'fa-exchange'],
        'refunds' => ['label' => 'Payment Refunds', 'icon' => 'fa-undo'],
        'cheques' => ['label' => 'Unpaid Cheques', 'icon' => 'fa-filter'],
        'fines' => ['label' => 'Cheque Fines', 'icon' => 'fa-camera'],
    ];
    $formTabs = [];
    $formType = $formTabs[$tab] ?? null;
    $headings = [
        'payments' => 'Payments',
        'make' => 'Make Payment',
        'receive' => 'Receive Payment',
        'open' => 'Accounts Opening Balance',
        'transfer' => 'Funds Transfer',
        'refunds' => 'Payment Refunds',
        'cheques' => 'Unpaid Cheques',
        'fines' => 'Cheque Fines',
    ];
    $chequeTypes = $chequeTypes ?? [];
@endphp

@section('title', 'Manage Money')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Manage Money',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Mailbox'],
    ],
])

<div class="sx-money-tabs">
    @foreach($tabs as $key => $item)
        <a href="{{ route('accounting.money', ['tab' => $key]) }}" class="{{ $tab === $key ? 'is-active' : '' }}">
            <i class="fa {{ $item['icon'] }}"></i> {{ $item['label'] }}
        </a>
    @endforeach
</div>

@if($tab === 'make' && $canManage)
    <div class="sx-box sx-items-card">
        <div class="sx-money-form-title"><i class="fa fa-camera"></i> MAKE PAYMENT (SUPPLIERS)</div>
        <div class="sx-box-body" style="min-height:auto;">
            <form method="post" action="{{ route('accounting.money.store') }}" class="sx-item-form" id="sx-make-pay-form">
                @csrf
                <input type="hidden" name="type" value="payment">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Branch/Station <span class="sx-req">*</span></label>
                            <select name="branch_id" class="form-control" required>
                                @foreach($branches as $option)
                                    <option value="{{ $option->id }}" @if((string) $selectedBranchId === (string) $option->id) selected @endif>{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Date: <span class="sx-req">*</span></label>
                            <input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', now()->toDateString()) }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Voucher No.:</label>
                            <input type="text" name="voucher_no" class="form-control" placeholder="Voucher No" value="{{ old('voucher_no') }}">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Paying Account: <span class="sx-req">*</span></label>
                            <select name="ledger_account_id" class="form-control" required>
                                <option value="">---Select Account---</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" @if((string) old('ledger_account_id') === (string) $account->id) selected @endif>{{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Account To Pay: <span class="sx-req">*</span></label>
                            <select name="supplier_id" id="sx-pay-supplier" class="form-control" required>
                                <option value="">Select Supplier</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier['id'] }}" data-due="{{ $supplier['due'] }}" @if((string) old('supplier_id') === (string) $supplier['id']) selected @endif>{{ $supplier['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Outstanding Balance: <span class="sx-req">*</span></label>
                            <input type="text" id="sx-pay-due" class="form-control" value="Purchase Due" readonly>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Amount: <span class="sx-req">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="Amount" value="{{ old('amount') }}" required>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="form-group">
                            <label>Narrative: <span class="sx-req">*</span></label>
                            <input type="text" name="description" class="form-control" placeholder="Description" value="{{ old('description') }}" required>
                        </div>
                    </div>
                </div>
                <div class="sx-money-form-actions">
                    <button type="reset" class="btn btn-danger"><i class="fa fa-times"></i> Reset</button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Submit</button>
                </div>
            </form>
        </div>
    </div>
@elseif($tab === 'receive' && $canManage)
    <div class="sx-box sx-items-card">
        <div class="sx-money-form-title"><i class="fa fa-camera"></i> RECEIVE PAYMENT (CUSTOMERS)</div>
        <div class="sx-box-body" style="min-height:auto;">
            <form method="post" action="{{ route('accounting.money.store') }}" class="sx-item-form" id="sx-receive-pay-form">
                @csrf
                <input type="hidden" name="type" value="receive">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Branch/Station <span class="sx-req">*</span></label>
                            <select name="branch_id" class="form-control" required>
                                @foreach($branches as $option)
                                    <option value="{{ $option->id }}" @if((string) $selectedBranchId === (string) $option->id) selected @endif>{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Date: <span class="sx-req">*</span></label>
                            <input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', now()->toDateString()) }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Received From: <span class="sx-req">*</span></label>
                            <select name="customer_id" id="sx-recv-customer" class="form-control" required>
                                <option value="">Select Customer</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer['id'] }}" data-due="{{ $customer['due'] }}" @if((string) old('customer_id') === (string) $customer['id']) selected @endif>{{ $customer['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Outstanding Balance: <span class="sx-req">*</span></label>
                            <input type="text" id="sx-recv-due" class="form-control" value="Sales Due" readonly>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Receiving Account: <span class="sx-req">*</span></label>
                            <select name="ledger_account_id" class="form-control" required>
                                <option value="">Select Account</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" @if((string) old('ledger_account_id') === (string) $account->id) selected @endif>{{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Amount: <span class="sx-req">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="Amount" value="{{ old('amount') }}" required>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Narrative:</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Description">{{ old('description') }}</textarea>
                </div>
                <div class="sx-money-form-actions">
                    <button type="reset" class="btn btn-danger"><i class="fa fa-times"></i> Reset</button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Submit</button>
                </div>
            </form>
        </div>
    </div>
@elseif($tab === 'open')
    <div class="sx-box sx-items-card">
        <div class="sx-items-toolbar">
            <h3 class="sx-box-title" style="margin:0;"><i class="fa fa-camera"></i> Accounts Opening Balance</h3>
            @if($canManage)
                <div class="sx-toolbar-actions">
                    <button type="button" class="btn btn-success" data-toggle="modal" data-target="#sx-open-bal-modal">
                        <i class="fa fa-plus"></i> Register Opening Balance
                    </button>
                </div>
            @endif
        </div>
        <div class="sx-box-body sx-items-body">
            @include('accounting.partials.dt-toolbar', ['tableId' => 'open-table'])
            <div class="table-responsive">
                <table id="open-table" class="table table-bordered sx-gold-table" width="100%">
                    <thead>
                        <tr>
                            <th>##</th>
                            <th>Created Date</th>
                            <th>Account Name</th>
                            <th>Posted By</th>
                            <th>Branch</th>
                            <th class="sx-th-debit">Debit</th>
                            <th class="sx-th-credit">Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entries as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td data-order="{{ optional($row->payment_date)->format('Y-m-d') }}">{{ optional($row->payment_date)->format('d-m-Y') }}</td>
                                <td>{{ optional($row->account)->name }}</td>
                                <td>{{ optional($row->user)->name }}</td>
                                <td>{{ optional($row->branch)->name }}</td>
                                <td class="sx-ob-debit" data-order="{{ $row->amount_in }}">{{ number_format($row->amount_in, 2) }}</td>
                                <td class="sx-ob-credit" data-order="{{ $row->amount_out }}">{{ number_format($row->amount_out, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="5"></th>
                            <th class="sx-th-debit">{{ number_format($totalIn, 2) }}</th>
                            <th class="sx-th-credit">{{ number_format($totalOut, 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
@elseif($tab === 'transfer')
    <div class="sx-box sx-items-card">
        <div class="sx-items-toolbar">
            <h3 class="sx-box-title" style="margin:0;"><i class="fa fa-camera"></i> Funds Transfer</h3>
            @if($canManage)
                <div class="sx-toolbar-actions">
                    <button type="button" class="btn btn-success" data-toggle="modal" data-target="#sx-transfer-modal">
                        <i class="fa fa-plus"></i> Record Transfer
                    </button>
                </div>
            @endif
        </div>
        <div class="sx-box-body sx-items-body">
            @include('accounting.partials.dt-toolbar', ['tableId' => 'xfer-table'])
            <div class="table-responsive">
                <table id="xfer-table" class="table table-bordered sx-gold-table" width="100%">
                    <thead>
                        <tr>
                            <th>##</th>
                            <th>Transfer Date</th>
                            <th>Account Name</th>
                            <th>Posted By</th>
                            <th>Branch</th>
                            <th>Description</th>
                            <th class="sx-th-in">In</th>
                            <th class="sx-th-out">Out</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entries as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td data-order="{{ optional($row->payment_date)->format('Y-m-d') }}">{{ optional($row->payment_date)->format('d-m-Y') }}</td>
                                <td>{{ optional($row->account)->name }}</td>
                                <td>{{ optional($row->user)->name }}</td>
                                <td>{{ optional($row->branch)->name }}</td>
                                <td>{{ $row->description }}</td>
                                <td class="sx-money-in" data-order="{{ $row->amount_in }}">{{ number_format($row->amount_in, 2) }}</td>
                                <td class="sx-money-out" data-order="{{ $row->amount_out }}">{{ number_format($row->amount_out, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="6"></th>
                            <th class="sx-th-in">{{ number_format($totalIn, 2) }}</th>
                            <th class="sx-th-out">{{ number_format($totalOut, 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
@elseif($tab === 'cheques' && $canManage)
    <div class="sx-box sx-items-card">
        <div class="sx-money-form-title"><i class="fa fa-filter"></i> Unpaid Cheques</div>
        <div class="sx-box-body" style="min-height:auto;">
            <form method="post" action="{{ route('accounting.money.store') }}" class="sx-item-form" id="sx-cheque-form">
                @csrf
                <input type="hidden" name="type" value="unpaid_cheque">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Branch/Station <span class="sx-req">*</span></label>
                            <select name="branch_id" class="form-control" required>
                                @foreach($branches as $option)
                                    <option value="{{ $option->id }}" @if((string) $selectedBranchId === (string) $option->id) selected @endif>{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Date: <span class="sx-req">*</span></label>
                            <input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', now()->toDateString()) }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Account To Debit: <span class="sx-req">*</span></label>
                            <select name="customer_id" class="form-control" required>
                                <option value="">Select Customer</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer['id'] }}" @if((string) old('customer_id') === (string) $customer['id']) selected @endif>{{ $customer['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Account To Credit: <span class="sx-req">*</span></label>
                            <select name="ledger_account_id" class="form-control" required>
                                <option value="">Select Account</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" @if((string) old('ledger_account_id') === (string) $account->id) selected @endif>{{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Amount: <span class="sx-req">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="Amount" value="{{ old('amount') }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Type: <span class="sx-req">*</span></label>
                            <select name="pay_mode" class="form-control" required>
                                <option value="">Select Type</option>
                                @foreach($chequeTypes as $value => $label)
                                    <option value="{{ $value }}" @if((string) old('pay_mode') === (string) $value) selected @endif>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Narrative: <span class="sx-req">*</span></label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Description" required>{{ old('description') }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="sx-money-form-actions">
                    <button type="reset" class="btn btn-danger"><i class="fa fa-times"></i> Reset</button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Submit</button>
                </div>
            </form>
        </div>
    </div>
@else
    <div class="sx-box sx-items-card">
        <div class="sx-items-toolbar">
            <h3 class="sx-box-title" style="margin:0;"><i class="fa fa-camera"></i> {{ $headings[$tab] ?? 'Payments' }}</h3>
            @if($canManage && in_array($tab, ['refunds', 'fines'], true))
                <button type="button" class="btn sx-btn-aqua" data-toggle="modal" data-target="#sx-money-extra"><i class="fa fa-plus"></i> Add</button>
            @endif
        </div>
        <div class="sx-box-body sx-items-body">
            @include('accounting.partials.dt-toolbar', ['tableId' => 'pay-table'])
            <div class="table-responsive">
                <table id="pay-table" class="table table-bordered sx-gold-table" width="100%">
                    <thead>
                        <tr>
                            <th>##</th>
                            <th>TransBy</th>
                            <th>PaymentDate</th>
                            <th>PayMode</th>
                            <th>Description</th>
                            <th>Branch</th>
                            <th>Account</th>
                            <th>VoucherNo</th>
                            <th>In</th>
                            <th>Out</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entries as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ optional($row->user)->name }}</td>
                                <td data-order="{{ optional($row->payment_date)->format('Y-m-d') }}">{{ optional($row->payment_date)->format('d-m-Y') }}</td>
                                <td>{{ $payModes[$row->pay_mode] ?? $row->pay_mode }}</td>
                                <td>{{ $row->description }}</td>
                                <td>{{ optional($row->branch)->name }}</td>
                                <td>{{ optional($row->account)->name }}</td>
                                <td>{{ $row->voucher_no }}</td>
                                <td class="sx-money-in">{{ number_format($row->amount_in, 2) }}</td>
                                <td class="sx-money-out">{{ number_format($row->amount_out, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="8" class="text-right">TOTAL</th>
                            <th class="sx-money-in">{{ number_format($totalIn, 2) }}</th>
                            <th class="sx-money-out">{{ number_format($totalOut, 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
@endif

@if($canManage && in_array($tab, ['refunds', 'fines'], true))
<div class="modal fade" id="sx-money-extra" tabindex="-1">
    <div class="modal-dialog" role="document">
        <form method="post" action="{{ route('accounting.money.store') }}" class="modal-content sx-conversion-modal">
            @csrf
            <input type="hidden" name="type" value="{{ $tab === 'refunds' ? 'refund' : 'cheque_fine' }}">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Add {{ $headings[$tab] }}</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Account <span class="sx-req">*</span></label>
                    <select name="ledger_account_id" class="form-control" required>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Date <span class="sx-req">*</span></label>
                    <input type="date" name="payment_date" class="form-control" value="{{ now()->toDateString() }}" required>
                </div>
                <div class="form-group">
                    <label>Amount <span class="sx-req">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Pay Mode</label>
                    <select name="pay_mode" class="form-control">
                        @foreach($payModes as $value => $label)
                            <option value="{{ $value }}" @if($value === 'cheque') selected @endif>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-success">Save</button>
            </div>
        </form>
    </div>
</div>
@endif

@if($canManage && $tab === 'open')
<div class="modal fade" id="sx-open-bal-modal" tabindex="-1">
    <div class="modal-dialog" role="document">
        <form method="post" action="{{ route('accounting.money.store') }}" class="modal-content sx-conversion-modal" id="sx-open-bal-form">
            @csrf
            <input type="hidden" name="type" value="open_bal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Register Opening Balance</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Branch/Station <span class="sx-req">*</span></label>
                    <select name="branch_id" class="form-control" required>
                        @foreach($branches as $option)
                            <option value="{{ $option->id }}" @if((string) $selectedBranchId === (string) $option->id) selected @endif>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Date <span class="sx-req">*</span></label>
                    <input type="date" name="payment_date" class="form-control" value="{{ now()->toDateString() }}" required>
                </div>
                <div class="form-group">
                    <label>Account Name <span class="sx-req">*</span></label>
                    <select name="ledger_account_id" class="form-control" required>
                        <option value="">Select Account</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Debit</label>
                            <input type="number" step="0.01" min="0" name="debit" id="sx-ob-debit" class="form-control" value="{{ old('debit') }}" placeholder="0.00">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Credit</label>
                            <input type="number" step="0.01" min="0" name="credit" id="sx-ob-credit" class="form-control" value="{{ old('credit') }}" placeholder="0.00">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" class="form-control" placeholder="Description" value="{{ old('description') }}">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-success">Save</button>
            </div>
        </form>
    </div>
</div>
@endif

@if($canManage && $tab === 'transfer')
<div class="modal fade" id="sx-transfer-modal" tabindex="-1">
    <div class="modal-dialog modal-lg" role="document">
        <form method="post" action="{{ route('accounting.money.store') }}" class="modal-content sx-conversion-modal sx-post-transfer-modal form-horizontal">
            @csrf
            <input type="hidden" name="type" value="transfer">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Post Transfer</h4>
            </div>
            <div class="modal-body">
                <p class="sx-post-transfer-note">Note: For transfer between different branches, Ensure to select the <i>FROM</i> and <i>TO</i> branch first.</p>
                <div class="form-group">
                    <label class="col-sm-3 control-label">Transfer Date:<span class="sx-req">*</span></label>
                    <div class="col-sm-9">
                        <input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', now()->toDateString()) }}" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">From Account: <span class="sx-req">*</span></label>
                    <div class="col-sm-9">
                        <select name="ledger_account_id" class="form-control" required>
                            <option value="">Select From Account</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" @if((string) old('ledger_account_id') === (string) $account->id) selected @endif>{{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">To Account: <span class="sx-req">*</span></label>
                    <div class="col-sm-9">
                        <select name="counterpart_account_id" class="form-control" required>
                            <option value="">Select To Account</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" @if((string) old('counterpart_account_id') === (string) $account->id) selected @endif>{{ $account->name }}</option>
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
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Submit</button>
                <button type="button" class="btn btn-danger" data-dismiss="modal"><i class="fa fa-times"></i> Close</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@if(!$formType && !in_array($tab, ['make', 'receive', 'open', 'transfer', 'cheques'], true))
@include('accounting.partials.datatable-js', [
    'tableId' => 'pay-table',
    'lengthId' => 'pay-table-length',
    'searchId' => 'pay-table-search',
    'exportId' => 'pay-table-export',
    'colvisId' => 'pay-table-colvis',
    'title' => 'Payments',
    'filename' => 'payments',
    'noSort' => [],
    'order' => [[2, 'desc']],
])
@endif

@if($tab === 'transfer')
@include('accounting.partials.datatable-js', [
    'tableId' => 'xfer-table',
    'lengthId' => 'xfer-table-length',
    'searchId' => 'xfer-table-search',
    'exportId' => 'xfer-table-export',
    'colvisId' => 'xfer-table-colvis',
    'title' => 'Funds Transfer',
    'filename' => 'funds-transfers',
    'noSort' => [0],
    'order' => [[1, 'desc']],
])
@endif

@if($tab === 'open')
@include('accounting.partials.datatable-js', [
    'tableId' => 'open-table',
    'lengthId' => 'open-table-length',
    'searchId' => 'open-table-search',
    'exportId' => 'open-table-export',
    'colvisId' => 'open-table-colvis',
    'title' => 'Accounts Opening Balance',
    'filename' => 'account-opening-balances',
    'noSort' => [0],
    'order' => [[1, 'desc']],
])
@push('scripts')
<script>
(function () {
    var debit = document.getElementById('sx-ob-debit');
    var credit = document.getElementById('sx-ob-credit');
    if (!debit || !credit) return;
    debit.addEventListener('input', function () {
        if (parseFloat(debit.value || '0') > 0) credit.value = '';
    });
    credit.addEventListener('input', function () {
        if (parseFloat(credit.value || '0') > 0) debit.value = '';
    });
})();
</script>
@endpush
@endif

@if($tab === 'make' || $tab === 'receive')
@push('scripts')
<script>
(function () {
    var isReceive = {{ $tab === 'receive' ? 'true' : 'false' }};
    var select = document.getElementById(isReceive ? 'sx-recv-customer' : 'sx-pay-supplier');
    var due = document.getElementById(isReceive ? 'sx-recv-due' : 'sx-pay-due');
    var form = document.getElementById(isReceive ? 'sx-receive-pay-form' : 'sx-make-pay-form');
    var emptyLabel = isReceive ? 'Sales Due' : 'Purchase Due';
    function updateDue() {
        if (!select || !due) return;
        var option = select.options[select.selectedIndex];
        if (!option || !option.value) {
            due.value = emptyLabel;
            return;
        }
        var amount = parseFloat(option.getAttribute('data-due') || '0') || 0;
        due.value = 'Ksh ' + amount.toFixed(2);
    }
    if (select) select.addEventListener('change', updateDue);
    if (form) form.addEventListener('reset', function () {
        setTimeout(updateDue, 0);
    });
    updateDue();
})();
</script>
@endpush
@endif
