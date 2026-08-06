@extends('layouts.master')

@section('title')
Sales Transactions
@endsection

@push('css')
<style>
    .tampil-bayar {
        font-size: 5em;
        text-align: center;
        height: 100px;
    }

    /* Total Amount label - styled like tampil-bayar but smaller to fit */
    .total-amount-label {
        font-size: 1.8em;
        text-align: center;
        height: 60px;
        line-height: 60px;
        font-weight: bold;
        color: #fff;
        background-color: #3c8dbc;
        border: 2px solid #2e6da4;
        border-radius: 5px;
        padding: 0 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        word-wrap: break-word;
        overflow-wrap: break-word;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .tampil-terbilang {
        padding: 10px;
        background: #f0f0f0;
    }

    /* Make Subtotal field visible and prominent */
    .amount-field {
        font-size: 18px !important;
        font-weight: bold !important;
        text-align: right !important;
        padding: 12px 15px !important;
        width: 100% !important;
        background-color: #f9f9f9 !important;
    }

    #subtotalBeforeTax {
        font-size: 18px !important;
        font-weight: bold !important;
        text-align: right !important;
        padding: 12px 15px !important;
        background-color: #f9f9f9 !important;
    }

    /* Make Total Payable field stand out - most important field */
    .total-payable-field {
        font-size: 20px !important;
        font-weight: bold !important;
        text-align: right !important;
        padding: 14px 18px !important;
        width: 100% !important;
        background-color: #e8f5e9 !important;
        border: 2px solid #4caf50 !important;
        color: #2e7d32 !important;
    }

    #bayarrp {
        font-size: 20px !important;
        font-weight: bold !important;
        text-align: right !important;
        padding: 14px 18px !important;
        background-color: #e8f5e9 !important;
        border: 2px solid #4caf50 !important;
        color: #2e7d32 !important;
    }

    /* Ensure VAT and Discount fields are visible and readable */
    #tax, #discountAmount {
        font-size: 16px !important;
        text-align: right !important;
        padding: 10px 12px !important;
        width: 100% !important;
        background-color: #fff !important;
    }

    /* POS product search — type directly in a text field; suggestions appear while typing */
    #product_search.pos-product-search-input {
        font-size: 16px;
        height: 45px;
        padding: 8px 12px;
        border: 2px solid #3c8dbc;
        border-radius: 4px;
        width: 100%;
    }
    #product_search.pos-product-search-input:focus {
        border-color: #2e6da4;
        outline: none;
        box-shadow: 0 0 0 2px rgba(60, 141, 188, 0.25);
    }
    .pos-search-select-wrap {
        position: relative;
    }
    .pos-product-search-results {
        display: none;
        position: absolute;
        left: 0;
        right: 0;
        top: 100%;
        margin-top: 2px;
        z-index: 10050;
        background: #fff;
        border: 2px solid #3c8dbc;
        border-radius: 4px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        max-height: min(280px, 42vh);
        overflow-y: auto;
    }
    .pos-product-search-results.is-open {
        display: block;
    }
    .pos-product-search-results .pos-search-result-item {
        padding: 10px 12px;
        cursor: pointer;
        border-bottom: 1px solid #eee;
    }
    .pos-product-search-results .pos-search-result-item:last-child {
        border-bottom: none;
    }
    .pos-product-search-results .pos-search-result-item.is-active,
    .pos-product-search-results .pos-search-result-item:hover {
        background-color: #3c8dbc;
        color: #fff;
    }
    .pos-product-search-results .pos-search-result-item.is-active .text-muted,
    .pos-product-search-results .pos-search-result-item:hover .text-muted {
        color: rgba(255, 255, 255, 0.9) !important;
    }
    .pos-product-search-results .pos-search-empty {
        padding: 12px;
        color: #777;
        font-size: 14px;
        text-align: center;
    }

    /* Search in box header (same row as management buttons) — saves cart height */
    .pos-header-toolbar {
        padding: 8px 10px !important;
        margin-bottom: 0 !important;
        border-bottom: 1px solid #f4f4f4;
    }
    .pos-header-toolbar-inner {
        display: flex;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 10px 14px;
    }
    .pos-header-search {
        flex: 1 1 280px;
        min-width: 0;
    }
    .pos-header-actions {
        flex: 0 0 auto;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 6px;
        min-width: 200px;
    }
    .pos-header-actions .pos-receipt-date-panel {
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        justify-content: flex-end;
        gap: 10px 16px;
        width: 100%;
    }
    .pos-header-actions .pos-receipt-date-panel .form-group {
        display: flex;
        flex-direction: row;
        align-items: center;
        flex-wrap: nowrap;
        margin-bottom: 0;
        gap: 6px;
    }
    .pos-header-actions .pos-receipt-date-panel label {
        margin-bottom: 0;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .pos-header-actions .pos-receipt-date-panel .form-control {
        height: 30px;
        padding: 2px 8px;
        font-size: 13px;
        width: auto;
    }
    .pos-header-actions .pos-receipt-date-panel #visibleReceiptNo {
        min-width: 100px;
        max-width: 140px;
    }
    .pos-header-actions .pos-receipt-date-panel #saledate {
        width: 132px;
        min-width: 132px;
    }
    .pos-header-actions .pos-receipt-date-panel .input-group {
        width: auto;
        flex: 0 0 auto;
    }
    .pos-header-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: flex-end;
    }
    .pos-product-search-section {
        margin-bottom: 0 !important;
        padding-bottom: 0 !important;
    }
    .pos-product-search-section .pos-compact-label {
        font-size: 13px;
        font-weight: bold;
        margin-bottom: 2px;
        display: block;
    }
    .pos-product-search-section .help-block {
        display: none;
    }
    .pos-header-search #product_search.pos-product-search-input {
        height: 38px;
        font-size: 15px;
    }
    .pos-header-search .pos-quick-add-btn {
        height: 38px !important;
        padding: 6px 12px !important;
        font-size: 12px !important;
    }
    @media (max-width: 991px) {
        .pos-header-actions {
            width: 100%;
            align-items: stretch;
        }
        .pos-header-actions .pos-receipt-date-panel {
            justify-content: flex-start;
            flex-wrap: wrap;
        }
        .pos-header-buttons {
            justify-content: flex-start;
        }
    }
    .pos-top-row {
        margin-bottom: 0;
    }
    .pos-receipt-date-panel {
        max-width: 100%;
        margin-left: auto;
        text-align: right;
    }
    @media (min-width: 1200px) {
        .pos-receipt-date-panel {
            max-width: 300px;
        }
    }
    .pos-receipt-date-panel .form-group {
        margin-bottom: 6px;
    }
    .pos-receipt-date-panel label {
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 2px;
        display: block;
    }
    .pos-receipt-date-panel .form-control {
        height: 32px;
        padding: 4px 8px;
        font-size: 13px;
    }
    .pos-receipt-date-panel .help-block {
        font-size: 10px;
        margin-top: 2px;
        margin-bottom: 0;
        text-align: left;
    }
    .pos-search-inline {
        display: flex;
        gap: 8px;
        align-items: stretch;
    }
    .pos-search-inline .pos-search-select-wrap {
        flex: 1;
        min-width: 0;
    }
    .pos-quick-add-btn {
        flex-shrink: 0;
        height: 45px !important;
        padding: 8px 14px !important;
        font-size: 13px !important;
        white-space: nowrap;
        align-self: flex-start;
        margin-top: 0;
    }

    .table-penjualan tbody tr:last-child {
        display: none;
    }

    /* Large-cart layout: cart scrolls in-place; checkout stays visible */
    .pos-cart-checkout-row {
        margin-left: -8px;
        margin-right: -8px;
    }
    .pos-cart-checkout-row > [class*='col-'] {
        padding-left: 8px;
        padding-right: 8px;
    }
    .pos-cart-scroll-panel {
        border: 1px solid #ddd;
        border-radius: 4px;
        background: #fafafa;
    }
    .pos-cart-scroll-panel .dataTables_wrapper {
        margin-bottom: 0;
    }
    .pos-cart-scroll-panel .dataTables_scrollHead {
        background: #f4f4f4;
    }
    .pos-cart-scroll-panel .dataTables_scrollHead thead th {
        border-bottom: 2px solid #3c8dbc;
        font-size: 12px;
        padding: 6px 8px;
        white-space: nowrap;
    }
    .pos-cart-scroll-panel .dataTables_scrollBody {
        background: #fff;
    }
    .pos-cart-summary-bar {
        margin-top: 8px;
        padding: 8px 12px;
        font-size: 14px;
        font-weight: 600;
        color: #333;
        background: #ecf0f5;
        border: 1px solid #d2d6de;
        border-radius: 4px;
        text-align: right;
    }
    .pos-cart-summary-bar .pos-cart-total-items-num {
        color: #3c8dbc;
        font-size: 16px;
    }
    .pos-cart-scroll-panel .table-penjualan {
        margin-bottom: 0;
        font-size: 13px;
    }
    .pos-cart-scroll-panel .table-penjualan td,
    .pos-cart-scroll-panel .table-penjualan th {
        padding: 5px 8px;
        vertical-align: middle;
    }
    .pos-cart-scroll-panel .table-penjualan .quantity {
        padding: 2px 6px;
        height: 28px;
        font-size: 13px;
    }
    .pos-cart-scroll-panel .table-penjualan .btn-xs {
        padding: 2px 6px;
    }
    .pos-checkout-sticky {
        position: sticky;
        top: 8px;
        z-index: 1019;
        max-height: calc(100vh - 88px);
        overflow-y: auto;
        overflow-x: hidden;
        padding-right: 4px;
    }
    .pos-checkout-sticky .tampil-bayar {
        font-size: 3.15em;
        height: 76px;
        line-height: 1.05;
        padding: 6px 8px;
    }
    .pos-checkout-sticky .tampil-terbilang {
        padding: 6px 10px;
        font-size: 13px;
    }
    .pos-checkout-sticky .fixed-payment-section {
        position: relative;
        top: auto;
        margin-top: 10px;
        padding: 10px 12px;
    }
    .pos-checkout-sticky .form-penjualan .form-group {
        margin-bottom: 8px;
    }
    .pos-checkout-sticky .form-penjualan .radio {
        margin-top: 4px;
        margin-bottom: 4px;
    }
    @media (max-width: 991px) {
        .pos-checkout-sticky {
            position: relative;
            top: auto;
            max-height: none;
            overflow: visible;
            margin-top: 16px;
        }
        .pos-checkout-sticky .tampil-bayar {
            font-size: 4em;
            height: 90px;
        }
    }

    @media(max-width: 768px) {
        .tampil-bayar {
            font-size: 3em;
            height: 70px;
            padding-top: 5px;
        }
        .total-amount-label {
            font-size: 1.8em;
            height: 60px;
            line-height: 60px;
        }
        .pos-product-search-results {
            max-height: min(240px, 50vh);
        }
    }
    .hiddendatepicker{
       display: none;
 
    }

    /* Fixed Payment Section - Stays visible when scrolling */
    .fixed-payment-section {
        position: sticky;
        top: 0;
        z-index: 1000;
        background-color: #fff;
        padding: 15px;
        margin: 15px 0;
        border: 2px solid #3c8dbc;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }


    /* Currency Type checkboxes (multi-select) */
    .currency-type-inline {
        padding: 8px 15px !important;
        margin-right: 15px !important;
        font-size: 15px !important;
    }

    .currency-type-inline input[type="checkbox"] {
        margin-right: 5px !important;
        margin-top: 2px !important;
        transform: scale(1.15);
    }

    /* Sale line whose product row was deleted — fix or remove before print/checkout */
    .table-penjualan tbody tr.pos-cart-line-broken td {
        background-color: #f2dede !important;
        border-top: 2px solid #d9534f !important;
    }
</style>
<link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
@endpush

@section('breadcrumb')
    @parent
    <li class="active">Sales Transactions</li>
@endsection

@php
    $posTodayIso = \Carbon\Carbon::now()->toDateString();
    $posSaleDateIso = ! empty($penjualan->saledate ?? null)
        ? \Carbon\Carbon::parse($penjualan->saledate)->toDateString()
        : $posTodayIso;
    $posSaleDateDisplay = \Carbon\Carbon::parse($posSaleDateIso)->format('d/m/Y');
@endphp

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            @php
                $user = auth()->user();
                $isManagement = isset($penjualan) && ($penjualan->sale_type ?? 'normal') === 'management';
            @endphp
            <form class="form-produk">
                @csrf
                <input type="hidden" name="id_penjualan" id="id_penjualan" value="{{ $id_penjualan }}">
                <input type="hidden" name="id_produk" id="id_produk">

                <div class="box-header pos-header-toolbar">
                    <div class="pos-header-toolbar-inner">
                        <div class="pos-header-search">
                            <div class="pos-product-search-section">
                                <label for="product_search" class="pos-compact-label">Search Product</label>
                                <div class="pos-search-inline">
                                    <div class="pos-search-select-wrap">
                                        <input type="text" id="product_search" class="form-control pos-product-search-input" autocomplete="off" spellcheck="false" placeholder="Shop code-product or name (e.g. A-12345)" title="Click and type — suggestions appear as you type">
                                        <div id="product_search_results" class="pos-product-search-results" role="listbox" aria-label="Product matches"></div>
                                    </div>
                                    <button onclick="tampilQuickAddProduk()" class="btn btn-primary btn-flat pos-quick-add-btn" type="button" title="Quick Add New Product"><i class="fa fa-plus"></i> Quick Add</button>
                                </div>
                            </div>
                        </div>
                        <div class="pos-header-actions">
                            <div class="pos-receipt-date-panel">
                                <div class="form-group">
                                    <label for="visibleReceiptNo">Receipt</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="visibleReceiptNo" class="form-control" name="visibleReceiptNo" value="{{ $penjualan->receiptno ?? '' }}" readonly>
                                        @if($isManagement)
                                        <span class="input-group-addon" style="background-color: #f39c12; color: white; font-weight: bold; padding: 2px 6px;">
                                            <i class="fa fa-gift"></i>
                                        </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="saledate">Date</label>
                                    <input type="text" id="saledate" class="form-control pos-saledate-picker" name="saledate" value="{{ $posSaleDateDisplay }}" autocomplete="off" placeholder="dd/mm/yyyy" title="Change this to backdate receipts">
                                </div>
                            </div>
                            @if($user)
                            <div class="pos-header-buttons">
                                <a href="{{ route('transaksi.baru.management') }}" class="btn btn-warning btn-sm btn-flat">
                                    <i class="fa fa-gift"></i> Sale by Management
                                </a>
                                <a href="{{ route('transaksi.baru') }}" class="btn btn-primary btn-sm btn-flat">
                                    <i class="fa fa-plus"></i> New Regular Sale
                                </a>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </form>

            <div class="box-body" style="padding-top: 8px;">
                <div class="row pos-cart-checkout-row">
                    <div class="col-lg-7 col-md-12 pos-cart-col">
                        <div class="pos-cart-scroll-panel">
                            <table class="table table-stiped table-bordered table-penjualan table-condensed">
                                <thead>
                                    <th width="5%">#</th>
                                    <th>Shop</th>
                                    <th>Item(s)</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th width="12%">Qty</th>
                                    <th>Subtotal</th>
                                    <th width="11%"><i class="fa fa-cog"></i></th>
                                </thead>
                            </table>
                        </div>
                        <div class="pos-cart-summary-bar" aria-live="polite">
                            Total item(s): <span class="pos-cart-total-items-num" id="pos_cart_total_items">0</span>
                        </div>
                    </div>
                    <div class="col-lg-5 col-md-12 pos-checkout-col">
                        <div class="pos-checkout-sticky">
                            <div class="tampil-bayar bg-primary"></div>
                            <div class="tampil-terbilang"></div>
                            <form action="{{ route('transaksi.simpan') }}" class="form-penjualan" method="post">
                            @csrf
                            <input type="hidden" name="id_penjualan" value="{{ $id_penjualan }}">
                            <input type="hidden" name="total" id="total">
                            <input type="hidden" name="total_item" id="total_item">
                            <input type="hidden" name="bayar" id="bayar">
                            <input type="hidden" name="id_member" id="id_member" value="{{ $memberSelected->id_member }}">
                            <input type="hidden" name="saledate2" id="saledate2" value="{{ $posSaleDateIso }}">
                             <input type="hidden" name="ReceiptNo" id="ReceiptNo" value="{{ $penjualan->receiptno ?? '' }}">
                             @error('ReceiptNo')
                            <div class="alert alert-danger">{{ $message }}</div>
                            @enderror

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="discount_type" class="control-label">Discount Type</label>
                                        <div>
                                            <div class="radio">
                                                <label>
                                                    <input type="radio" name="discount_type" id="discount_type_percentage" value="percentage" checked>
                                                    Percentage (%)
                                                </label>
                                            </div>
                                            <div class="radio">
                                                <label>
                                                    <input type="radio" name="discount_type" id="discount_type_fixed" value="fixed">
                                                    Fixed Amount
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="diskon" class="control-label">Discount</label>
                                        <input type="number" name="diskon" id="diskon" class="form-control"
                                               value="{{ ! empty($memberSelected->id_member) ? $diskon : 0 }}"
                                               step="0.01" min="0">
                                        <input type="hidden" name="discount_type" id="discount_type_hidden">
                                    </div>
                                </div>
                            </div>

                            {{-- Fixed Payment Section --}}
                            <div class="fixed-payment-section">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="subtotalBeforeTax" class="control-label">Subtotal</label>
                                            <input type="text" id="subtotalBeforeTax" class="form-control amount-field" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="tax" class="control-label">VAT (16%)</label>
                                            <input type="text" id="tax" class="form-control" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="discountAmount" class="control-label">Discount</label>
                                            <input type="text" id="discountAmount" class="form-control" readonly value="Ksh 0">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="bayar" class="control-label">Total Payable</label>
                                            <input type="text" id="bayarrp" class="form-control total-payable-field" readonly>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="paymentMode" class="control-label">Payment Mode</label>
                                            <select id="paymentMode" name="paymentMode" class="form-control" required>
                                                <option value="">Select Payment Mode</option>
                                                <option value="Cash">Cash</option>
                                                <option value="Mpesa">Mpesa</option>
                                                <option value="Card">Card</option>
                                                <option value="Management">Management</option>
                                                <option value="Split">Split</option>
                                            </select>
                                        </div>
                                    </div>
                                    @php
                                        $selectedCurrencies = ['KSH'];
                                        if (isset($penjualan->currency_type) && $penjualan->currency_type !== null && $penjualan->currency_type !== '') {
                                            $selectedCurrencies = array_values(array_filter(array_map('trim', explode(',', (string) $penjualan->currency_type))));
                                            if (empty($selectedCurrencies)) {
                                                $selectedCurrencies = ['KSH'];
                                            }
                                        }
                                    @endphp
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label class="control-label">Currency Type <span class="text-danger">*</span></label>
                                            <p class="help-block" style="margin-bottom: 6px;">Select all currencies used for this payment (e.g. KSH + USD).</p>
                                            <div style="margin-top: 8px;">
                                                <label class="currency-type-inline" style="margin-right: 20px; font-weight: normal;">
                                                    <input type="checkbox" class="js-currency-type" value="KSH" {{ in_array('KSH', $selectedCurrencies, true) ? 'checked' : '' }}> KSH
                                                </label>
                                                <label class="currency-type-inline" style="margin-right: 20px; font-weight: normal;">
                                                    <input type="checkbox" class="js-currency-type" value="USD" {{ in_array('USD', $selectedCurrencies, true) ? 'checked' : '' }}> USD
                                                </label>
                                                <label class="currency-type-inline" style="margin-right: 20px; font-weight: normal;">
                                                    <input type="checkbox" class="js-currency-type" value="EUROS" {{ in_array('EUROS', $selectedCurrencies, true) ? 'checked' : '' }}> EUROS
                                                </label>
                                                <label class="currency-type-inline" style="font-weight: normal;">
                                                    <input type="checkbox" class="js-currency-type" value="POUNDS" {{ in_array('POUNDS', $selectedCurrencies, true) ? 'checked' : '' }}> POUNDS
                                                </label>
                                            </div>
                                            <input type="hidden" id="currency_type" name="currency_type" value="">
                                        </div>
                                    </div>
                                </div>
                                {{-- Hidden fields for Received and Return (kept for backend compatibility) --}}
                                <input type="hidden" id="diterima" name="diterima" value="{{ $penjualan->diterima ?? 0 }}">
                                <input type="hidden" id="kembali" name="kembali" value="0">

                                {{-- Split Payment Fields (shown when Split is selected) --}}
                                <div class="row" id="splitPaymentFields" style="display: none;">
                                    <div class="col-md-12">
                                        <div class="alert alert-info">
                                            <strong>Split Payment:</strong> Enter amounts for each payment method. Total must equal the amount due.
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="splitCash" class="control-label">Cash Amount</label>
                                            <input type="number" id="splitCash" name="splitCash" class="form-control split-amount" step="0.01" min="0" value="0">
                                            <span class="help-block text-danger" id="splitCashError"></span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="splitMpesa" class="control-label">Mpesa Amount</label>
                                            <input type="number" id="splitMpesa" name="splitMpesa" class="form-control split-amount" step="0.01" min="0" value="0">
                                            <span class="help-block text-danger" id="splitMpesaError"></span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="splitCard" class="control-label">Card Amount</label>
                                            <input type="number" id="splitCard" name="splitCard" class="form-control split-amount" step="0.01" min="0" value="0">
                                            <span class="help-block text-danger" id="splitCardError"></span>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label class="control-label">Split Total</label>
                                            <input type="text" id="splitTotal" class="form-control" readonly>
                                            <span class="help-block" id="splitTotalMessage"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="box-footer">
                <button type="button" class="btn btn-info btn-sm btn-flat btn-unsuspend"><i class="fa fa-play"></i> Resume Suspended Sale</button>
                <button type="button" class="btn btn-warning btn-sm btn-flat btn-suspend"><i class="fa fa-save"></i> Suspend Sale</button>
                <a href="{{ route('transaksi.initiate_edit_form') }}" class="btn btn-success btn-sm btn-flat" title="Enter a receipt number to initiate edit from POS">
                    <i class="fa fa-edit"></i> Edit Sale
                </a>
                <button type="button" class="btn btn-default btn-sm btn-flat" data-toggle="modal" data-target="#modal-reprint-sold-receipt" title="Reprint a completed sale by receipt number">
                    <i class="fa fa-print"></i> Reprint Sold Receipt
                </button>
                <button type="button" class="btn btn-success btn-sm btn-flat pull-right btn-simpan"><i class="fa fa-floppy-o"></i> Complete & Save Transaction</button>
            </div>
        </div>
    </div>
</div>

@includeIf('penjualan_detail.produk')
@includeIf('penjualan_detail.member')
@includeIf('penjualan_detail.suspended')
@includeIf('penjualan_detail.quick_add')

<!-- Currency Calculator Modal -->
<div class="modal fade" id="modal-currency-calculator" tabindex="-1" role="dialog" aria-labelledby="modalCurrencyCalculatorLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="modalCurrencyCalculatorLabel"><i class="fa fa-calculator"></i> Currency Calculator</h4>
      </div>
      <div class="modal-body">
        <div class="alert alert-info" id="calc-info" style="margin-bottom:10px;">
            Enter amount in base currency (e.g., Ksh), choose target currency and rate. Result shows converted amount.
        </div>
        <div class="form-group">
            <label for="calc-amount">Amount (Base Currency)</label>
            <input type="number" step="0.01" min="0" id="calc-amount" class="form-control" placeholder="Enter amount" value="0">
        </div>
        <div class="form-group">
            <label for="calc-rate">Rate (Base per 1 Target)</label>
            <input type="number" step="0.0001" min="0" id="calc-rate" class="form-control" placeholder="e.g. Ksh per 1 USD" value="0">
            <p class="help-block">Example: If 1 USD = 150 Ksh, enter 150.</p>
        </div>
        <div class="form-group">
            <label for="calc-currency">Target Currency</label>
            <select id="calc-currency" class="form-control">
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
                <option value="GBP">GBP</option>
                <option value="TZS">TZS</option>
                <option value="UGX">UGX</option>
                <option value="ZAR">ZAR</option>
                <option value="RWF">RWF</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div class="form-group">
            <button type="button" class="btn btn-primary" id="btn-calc-convert"><i class="fa fa-refresh"></i> Convert</button>
        </div>
        <div class="well alert" id="calc-result" style="display:none;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Reprint sold receipt — pick from recent sales -->
<div class="modal fade" id="modal-reprint-sold-receipt" tabindex="-1" role="dialog" aria-labelledby="modalReprintSoldLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="modalReprintSoldLabel"><i class="fa fa-print"></i> Reprint sold receipt</h4>
      </div>
      <div class="modal-body">
        <div class="row" style="margin-bottom:10px;">
          <div class="col-sm-3">
            <label class="control-label" for="reprint-date-from">From date</label>
            <input type="date" id="reprint-date-from" class="form-control input-sm">
          </div>
          <div class="col-sm-3">
            <label class="control-label" for="reprint-date-to">To date</label>
            <input type="date" id="reprint-date-to" class="form-control input-sm">
          </div>
          <div class="col-sm-3">
            <label class="control-label" for="reprint-recent-preset">Quick range</label>
            <select id="reprint-recent-preset" class="form-control input-sm">
              <option value="">Custom range</option>
              <option value="1">Today only</option>
              <option value="7" selected>Last 7 days</option>
              <option value="30">Last 30 days</option>
            </select>
          </div>
          <div class="col-sm-3 text-right" style="padding-top:22px;">
            <button type="button" class="btn btn-default btn-sm" id="btn-reprint-recent-refresh"><i class="fa fa-refresh"></i> Refresh list</button>
          </div>
        </div>
        <div id="reprint-recent-loading" class="text-center text-muted" style="padding:24px;"><i class="fa fa-spinner fa-spin"></i> Loading receipts…</div>
        <div id="reprint-recent-empty" class="alert alert-info hide">No completed sales found in this period.</div>
        <div id="reprint-recent-wrap" class="hide" style="max-height:380px;overflow:auto;border:1px solid #eee;border-radius:4px;">
          <table class="table table-condensed table-hover" style="margin-bottom:0;font-size:13px;">
            <thead>
              <tr>
                <th>Receipt</th>
                <th>Type</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Total</th>
                <th>Time</th>
                <th style="width:100px;"></th>
              </tr>
            </thead>
            <tbody id="reprint-recent-tbody"></tbody>
          </table>
        </div>
        <div class="alert alert-danger hide" id="reprint-sold-error"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
    let table, table2;
    let discountType = 'percentage'; // Default discount type
    const productSearchInput = $('#product_search');
    const productSearchResults = $('#product_search_results');
    let posProductSearchState = { items: [], activeIndex: -1, xhr: null, timer: null };
    const reprintRecentUrl = @json(route('transaksi.reprint_recent'));

    function posCartHealthUrl(saleId) {
        return '{{ url('/transaksi') }}/' + encodeURIComponent(saleId) + '/cart-health';
    }

    var POS_TODAY_ISO = @json($posTodayIso);
    var POS_DEFAULT_DISPLAY = @json($posSaleDateDisplay);
    var POS_DEFAULT_ISO = @json($posSaleDateIso);

    function posSaleDateDisplayToIso(display) {
        display = String(display == null ? '' : display).trim();
        if (!display) {
            return '';
        }
        if (/^\d{4}-\d{2}-\d{2}$/.test(display)) {
            return display;
        }
        var m = display.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (!m) {
            return '';
        }
        var day = parseInt(m[1], 10);
        var month = parseInt(m[2], 10);
        var year = parseInt(m[3], 10);
        if (month < 1 || month > 12 || day < 1 || day > 31) {
            return '';
        }
        return year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
    }

    function posSaleDateIsoToDisplay(iso) {
        iso = String(iso == null ? '' : iso).trim();
        if (!/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
            return '';
        }
        var parts = iso.split('-');
        return parts[2] + '/' + parts[1] + '/' + parts[0];
    }

    function posNormalizeSaleDateDisplay(value) {
        var iso = posSaleDateDisplayToIso(value);
        return iso ? posSaleDateIsoToDisplay(iso) : '';
    }

    function syncPosSaleDateToCheckoutForm() {
        var iso = posSaleDateDisplayToIso($('#saledate').val());
        $('#saledate2').val(iso || POS_DEFAULT_ISO || POS_TODAY_ISO);
    }

    function posInitSaleDateField() {
        var initial = POS_DEFAULT_DISPLAY;
        try {
            var stored = sessionStorage.getItem('pos_saledate');
            var storedIso = posSaleDateDisplayToIso(stored);
            if (storedIso) {
                if (storedIso < POS_TODAY_ISO && POS_DEFAULT_ISO === POS_TODAY_ISO) {
                    sessionStorage.removeItem('pos_saledate');
                } else if (storedIso === POS_DEFAULT_ISO || storedIso >= POS_TODAY_ISO) {
                    initial = posSaleDateIsoToDisplay(storedIso);
                }
            }
        } catch (e) {}

        $('#saledate').val(initial);
        syncPosSaleDateToCheckoutForm();

        if ($.fn.datepicker) {
            var $picker = $('#saledate');
            if ($picker.data('datepicker')) {
                $picker.datepicker('destroy');
            }
            $picker.datepicker({
                format: 'dd/mm/yyyy',
                autoclose: true,
                todayHighlight: true,
                orientation: 'bottom auto'
            }).on('changeDate clearDate', function () {
                syncPosSaleDateToCheckoutForm();
                try {
                    sessionStorage.setItem('pos_saledate', posNormalizeSaleDateDisplay($('#saledate').val()) || '');
                } catch (e2) {}
            });
        }
    }

    function posCartHasBrokenLineDom() {
        return $('.table-penjualan tbody tr.pos-cart-line-broken').length > 0;
    }

    function posScrollToFirstBrokenCartLine() {
        var $r = $('.table-penjualan tbody tr.pos-cart-line-broken').first();
        if (!$r.length) {
            return;
        }
        try {
            $r[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        } catch (err) {}
        $r.find('td').css('outline', '2px solid #a94442');
        setTimeout(function () {
            $r.find('td').css('outline', '');
        }, 4000);
    }

    /** Scroll cart so the newest line (last entered) is visible — order stays oldest-first. */
    function posScrollCartToNewestLine(focusDetailId) {
        var $scrollBody = $('.pos-cart-scroll-panel .dataTables_scrollBody');
        if (!$scrollBody.length) {
            return;
        }
        var $row = $();
        if (focusDetailId != null && focusDetailId !== '') {
            $row = $scrollBody.find('.quantity[data-id="' + focusDetailId + '"]').first().closest('tr');
        }
        if (!$row.length) {
            $row = $scrollBody.find('tbody tr').last();
        }
        if (!$row.length) {
            return;
        }
        try {
            var rowTop = $row.position().top;
            var scrollTop = $scrollBody.scrollTop();
            var viewport = $scrollBody.innerHeight();
            var rowH = $row.outerHeight() || 0;
            var target = rowTop + scrollTop - viewport + rowH + 8;
            var maxScroll = Math.max(0, $scrollBody[0].scrollHeight - viewport);
            $scrollBody.scrollTop(Math.min(Math.max(0, target), maxScroll));
        } catch (err) {
            try {
                $row[0].scrollIntoView({ block: 'end', behavior: 'auto' });
            } catch (e2) {}
        }
    }

    function posAlertBrokenCartIssues(r) {
        var msg = 'One or more lines point to a product that no longer exists in stock (red rows in the list). Remove those lines with the trash button, then try again.\n\n';
        if (r && r.issues && r.issues.length) {
            r.issues.forEach(function (it) {
                msg += '• Line #' + it.id_penjualan_detail + ' — missing product id ' + it.id_produk + '\n';
            });
        }
        alert(msg);
    }

    // Function to play beep sound when product is added
    function playBeepSound() {
        try {
            // Create audio context
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            
            // Create oscillator for beep sound
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            // Connect oscillator to gain node and gain node to destination
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            // Set beep properties (frequency: 800Hz, duration: 100ms)
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            
            // Set volume (gain)
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
            
            // Play beep
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.1);
        } catch (e) {
            // Fallback: Use HTML5 Audio if Web Audio API is not supported
            console.log('Web Audio API not supported, using fallback');
            // You can add a fallback audio file here if needed
        }
    }

    function syncCurrencyTypeHidden() {
        var vals = [];
        $('.js-currency-type:checked').each(function () {
            vals.push($(this).val());
        });
        $('#currency_type').val(vals.join(','));
    }

    /** Checkout form has its own hidden id_penjualan; product search uses #id_penjualan — keep them identical before submit. */
    function syncPosSaleIdToCheckoutForm() {
        var v = $('#id_penjualan').val();
        if (v) {
            $('.form-penjualan input[name="id_penjualan"]').val(v);
        }
    }

    $(document).on('change', '.js-currency-type', syncCurrencyTypeHidden);

    $(document).ready(function () {
        syncCurrencyTypeHidden();
    });

    $(function () {
        $('body').addClass('sidebar-collapse');

        // Initialize discountType from the checked radio button
        discountType = $('input[name="discount_type"]:checked').val() || 'percentage';

        // Track if the sale has been saved
        let saleSaved = false;

        // Do not run cleanup on beforeunload (including F5 refresh): the DOM total can be wrong
        // during unload and sendBeacon could race; cart lines live in the DB and are tied to session + pos_last_sale cookie.

        function openPosReprintPrintWindow(url) {
            var w = window.open(url, 'ReprintReceipt', 'height=600,width=500');
            if (!w) {
                alert('Please allow pop-ups to print the receipt.');
            }
            // Receipt view triggers print after load (no duplicate delayed print() from opener).
        }

        function formatReprintDateYmd(dateObj) {
            var y = dateObj.getFullYear();
            var m = String(dateObj.getMonth() + 1).padStart(2, '0');
            var d = String(dateObj.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + d;
        }

        function setReprintPresetDays(days) {
            var end = new Date();
            var start = new Date();
            start.setDate(end.getDate() - (days - 1));
            $('#reprint-date-from').val(formatReprintDateYmd(start));
            $('#reprint-date-to').val(formatReprintDateYmd(end));
        }

        function initReprintDateRange() {
            if (!$('#reprint-date-from').val() || !$('#reprint-date-to').val()) {
                setReprintPresetDays(7);
                $('#reprint-recent-preset').val('7');
            }
        }

        function loadReprintRecentList() {
            var startDate = ($('#reprint-date-from').val() || '').trim();
            var endDate = ($('#reprint-date-to').val() || '').trim();
            var $err = $('#reprint-sold-error');

            if (!startDate || !endDate) {
                $err.removeClass('hide').text('Please choose both a from date and a to date.');
                return;
            }
            if (startDate > endDate) {
                $err.removeClass('hide').text('From date must be on or before to date.');
                return;
            }

            $err.addClass('hide').text('');
            $('#reprint-recent-loading').removeClass('hide');
            $('#reprint-recent-empty').addClass('hide');
            $('#reprint-recent-wrap').addClass('hide');
            $('#reprint-recent-tbody').empty();

            $.getJSON(reprintRecentUrl, { start_date: startDate, end_date: endDate })
                .done(function (res) {
                    $('#reprint-recent-loading').addClass('hide');
                    var list = (res && res.sales) ? res.sales : [];
                    if (!list.length) {
                        $('#reprint-recent-empty').removeClass('hide');
                        return;
                    }
                    $('#reprint-recent-wrap').removeClass('hide');
                    var $tb = $('#reprint-recent-tbody');
                    list.forEach(function (row) {
                        var typeLabel = (row.sale_type === 'management')
                            ? '<span class="label label-warning">Mgmt</span>'
                            : '<span class="label label-default">Sale</span>';
                        var tr = $('<tr></tr>');
                        tr.append($('<td></td>').append($('<strong></strong>').text(row.receiptno || '—')));
                        tr.append($('<td></td>').html(typeLabel));
                        tr.append($('<td class="text-right"></td>').text(row.total_item != null ? row.total_item : '—'));
                        tr.append($('<td class="text-right"></td>').append($('<strong></strong>').text(row.amount_formatted || '')));
                        tr.append($('<td class="text-muted"></td>').text(row.time || ''));
                        var $btn = $('<button type="button" class="btn btn-primary btn-xs btn-flat btn-reprint-row"><i class="fa fa-print"></i> Print</button>');
                        $btn.attr('data-print-url', row.print_url || '');
                        tr.append($('<td></td>').append($btn));
                        $tb.append(tr);
                    });
                })
                .fail(function (xhr) {
                    $('#reprint-recent-loading').addClass('hide');
                    var msg = 'Could not load receipts.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    $err.removeClass('hide').text(msg);
                });
        }

        $('#modal-reprint-sold-receipt').on('shown.bs.modal', function () {
            initReprintDateRange();
            loadReprintRecentList();
        });

        $('#reprint-recent-preset').on('change', function () {
            var days = parseInt($(this).val(), 10);
            if (!days) {
                return;
            }
            setReprintPresetDays(days);
            if ($('#modal-reprint-sold-receipt').hasClass('in')) {
                loadReprintRecentList();
            }
        });

        $('#reprint-date-from, #reprint-date-to').on('change', function () {
            $('#reprint-recent-preset').val('');
            if ($('#modal-reprint-sold-receipt').hasClass('in')) {
                loadReprintRecentList();
            }
        });

        $('#btn-reprint-recent-refresh').on('click', function () {
            loadReprintRecentList();
        });

        $('#reprint-recent-tbody').on('click', '.btn-reprint-row', function () {
            var url = $(this).data('print-url');
            if (url) {
                openPosReprintPrintWindow(url);
            }
        });

        // Also cleanup when clicking links or navigating away via browser back button
        $(document).on('click', 'a[href]:not([href^="#"])', function(e) {
            if (!saleSaved) {
                let originalTotal = parseFloat($('.total').text().replace(/[^0-9.-]+/g, '')) || 0;
                
                if (originalTotal === 0 || originalTotal <= 0) {
                    // Cleanup before navigating
                    $.ajax({
                        url: '{{ route("transaksi.cleanup") }}',
                        method: 'POST',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        async: false // Synchronous to ensure cleanup completes before navigation
                    });
                }
            }
        });

        function posCartTableScrollY() {
            var reserve = 40;
            var minH = 340;
            var $panel = $('.pos-cart-scroll-panel');
            if ($panel.length) {
                var top = $panel.offset().top;
                if (top > 0) {
                    var h = window.innerHeight - top - reserve;
                    return Math.max(minH, h) + 'px';
                }
            }
            return '440px';
        }

        var posCartResizeTimer = null;
        function adjustPosCartScrollHeight() {
            if (!$.fn.dataTable.isDataTable('.table-penjualan')) {
                return;
            }
            var h = posCartTableScrollY();
            var $body = $('.pos-cart-scroll-panel .dataTables_scrollBody');
            if ($body.length) {
                $body.css({ height: h, maxHeight: h });
            }
            try {
                table.columns.adjust();
            } catch (eAdj) {}
        }

        table = $('.table-penjualan').DataTable({
            responsive: false,
            processing: true,
            serverSide: true,
            autoWidth: false,
            scrollY: posCartTableScrollY(),
            scrollCollapse: true,
            scrollX: true,
            ajax: {
                url: '{{ route('transaksi.data', $id_penjualan) }}',
            },
            columns: [
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'kode_produk'},
                {data: 'nama_produk'},
                {data: 'harga_jual'},
                {data: 'stok'},
                {data: 'jumlah'},
                {data: 'subtotal'},
                {data: 'aksi', searchable: false, sortable: false},
            ],
            dom: 'Brt',
            bSort: false,
            paginate: false
        })
        .on('draw.dt', function () {
            loadForm($('#diskon').val());
            adjustPosCartScrollHeight();
        });

        setTimeout(adjustPosCartScrollHeight, 0);
        $(window).on('resize orientationchange', function () {
            clearTimeout(posCartResizeTimer);
            posCartResizeTimer = setTimeout(adjustPosCartScrollHeight, 120);
        });
        table2 = $('.table-produk').DataTable();

        initProductSearch();


        // Add event listener for discount type change
        $('input[name="discount_type"]').on('change', function() {
            discountType = $(this).val();
            // Update the hidden field for form submission
            $('#discount_type_hidden').val(discountType);
            // Recalculate the form with current discount value
            loadForm($('#diskon').val());
        });
        
        // Initialize discount type hidden field
        $('#discount_type_hidden').val($('input[name="discount_type"]:checked').val() || 'percentage');

        // Add event listener for discount value change
        $('#diskon').on('input', function() {
            if ($(this).val() == "") {
                $(this).val(0).select();
            }
            
            // Just recalculate the form with the new discount value
            // The discount is applied at the sale level, not item level
            loadForm($(this).val());
        });

        // Handle payment mode change - show/hide split payment fields
        $('#paymentMode').on('change', function() {
            var paymentMode = $(this).val();
            if (paymentMode === 'Split') {
                $('#splitPaymentFields').show();
                // Reset split amounts
                $('#splitCash').val(0);
                $('#splitMpesa').val(0);
                $('#splitCard').val(0);
                updateSplitTotal();
            } else {
                $('#splitPaymentFields').hide();
                // Clear split amounts
                $('#splitCash').val(0);
                $('#splitMpesa').val(0);
                $('#splitCard').val(0);
            }
        });

        // Handle split payment amount changes
        $(document).on('input', '.split-amount', function() {
            updateSplitTotal();
        });

        function updateSplitTotal() {
            var cashAmount = parseFloat($('#splitCash').val()) || 0;
            var mpesaAmount = parseFloat($('#splitMpesa').val()) || 0;
            var cardAmount = parseFloat($('#splitCard').val()) || 0;
            var splitTotal = cashAmount + mpesaAmount + cardAmount;
            
            $('#splitTotal').val('Ksh ' + splitTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            
            // Get the total amount due
            var totalDue = parseFloat($('#bayar').val()) || parseFloat($('#bayarrp').val().replace(/[^0-9.-]+/g, '')) || 0;
            
            // Validate split total matches total due
            var difference = Math.abs(splitTotal - totalDue);
            var $splitTotalMessage = $('#splitTotalMessage');
            
            if (splitTotal === 0) {
                $splitTotalMessage.text('Enter split payment amounts').removeClass('text-success text-danger').addClass('text-warning');
            } else if (difference < 0.01) {
                $splitTotalMessage.text('✓ Split total matches amount due').removeClass('text-warning text-danger').addClass('text-success');
            } else if (splitTotal > totalDue) {
                $splitTotalMessage.text('⚠ Split total exceeds amount due by Ksh ' + difference.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})).removeClass('text-success text-warning').addClass('text-danger');
            } else {
                $splitTotalMessage.text('⚠ Split total is less than amount due by Ksh ' + difference.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})).removeClass('text-success text-warning').addClass('text-danger');
            }
        }

        // Store original value when field is focused
        $(document).on('focus', '.quantity', function () {
            let $input = $(this);
            let currentValue = parseInt($input.val()) || parseInt($input.data('original-quantity')) || 0;
            if (!$input.data('original-value')) {
                $input.data('original-value', currentValue);
            }
            // Also ensure original-quantity is set
            if (!$input.data('original-quantity')) {
                $input.data('original-quantity', currentValue);
            }
        });

        // Function to update UI calculations (used by both input and blur events)
        function updateQuantityCalculations($input, jumlah, originalValue) {
            // Get price and discount from data attributes
            let price = parseFloat($input.data('price')) || 0;
            let discount = parseFloat($input.data('discount')) || 0;
            
            // Calculate new subtotal immediately for this row
            let newSubtotal = (price * jumlah) - ((discount * jumlah) / 100 * price);
            
            // Get current total from hidden div
            let currentTotal = parseFloat($('.total').text().replace(/[^0-9.-]+/g, '')) || 0;
            
            // Get old subtotal from the row
            let $row = $input.closest('tr');
            let $subtotalCell = $row.find('td').eq(6);
            let oldSubtotalText = $subtotalCell.text();
            let oldSubtotal = parseFloat(oldSubtotalText.replace(/[^0-9.-]+/g, '')) || 0;
            
            // If we don't have the old subtotal yet, calculate it from original quantity
            if (isNaN(oldSubtotal) || oldSubtotal === 0) {
                oldSubtotal = (price * originalValue) - ((discount * originalValue) / 100 * price);
            }
            
            // Calculate new total immediately
            let newTotal = currentTotal - oldSubtotal + newSubtotal;
            
            // Update the subtotal in the table immediately (optimistic update)
            $subtotalCell.text('ksh ' + newSubtotal.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0}));
            
            // Update the hidden total div immediately
            $('.total').text(newTotal);
            
            // Update total_item
            let currentTotalItem = parseFloat($('.total_item').text().replace(/[^0-9.-]+/g, '')) || 0;
            let newTotalItem = currentTotalItem - originalValue + jumlah;
            $('.total_item').text(newTotalItem);
            
            // Update form display immediately
            loadForm($('#diskon').val());
        }

        // Handle input event - update UI in real-time as user types
        $(document).on('input', '.quantity', function () {
            let $input = $(this);
            let inputValue = $input.val().trim();
            let originalValue = parseInt($input.data('original-value')) || parseInt($input.data('original-quantity')) || 1;
            
            // Allow empty field while typing - restore original calculations
            if (inputValue === '' || inputValue === '-') {
                // Restore original calculations
                updateQuantityCalculations($input, originalValue, originalValue);
                return;
            }
            
            let jumlah = parseInt(inputValue);
            
            // If not a valid number, restore original
            if (isNaN(jumlah) || jumlah < 1) {
                updateQuantityCalculations($input, originalValue, originalValue);
                return;
            }
            
            // Allow any quantity - stock validation disabled to allow selling out-of-stock items
            // Only cap at 10000 to prevent unrealistic values
            if (jumlah > 10000) {
                jumlah = 10000; // Cap at 10000
            }
            
            // Update calculations in real-time
            updateQuantityCalculations($input, jumlah, originalValue);
        });

        // Handle blur event - validate and send update when field loses focus
        $(document).on('blur', '.quantity', function () {
            let $input = $(this);
            let id = $input.data('id');
            let maxStock = parseInt($input.data('stock')) || 0;
            let productName = $input.data('product') || 'Product';
            let inputValue = $input.val().trim();
            let originalValue = parseInt($input.data('original-value')) || parseInt($input.data('original-quantity')) || 1;
            
            // If field is empty, restore original value
            if (inputValue === '' || inputValue === '-') {
                $input.val(originalValue);
                return;
            }
            
            let jumlah = parseInt(inputValue);
            
            // If not a valid number, restore original value
            if (isNaN(jumlah) || jumlah < 1) {
                $input.val(originalValue);
                alert('Quantity must be greater than 0');
                return;
            }
            
            // Stock validation disabled - allowing items to be sold even when out of stock
            // Check against available stock (COMMENTED OUT to allow selling out-of-stock items)
            /*
            if (maxStock > 0 && jumlah > maxStock) {
                $input.val(maxStock);
                alert('Insufficient stock for ' + productName + '. Maximum available: ' + maxStock);
                jumlah = maxStock;
            }
            */
            
            if (jumlah > 10000) {
                $input.val(10000);
                alert('The number cannot exceed 10000');
                jumlah = 10000;
            }

            // Update calculations (already done on input, but ensure it's correct)
            updateQuantityCalculations($input, jumlah, originalValue);
            
            // Only send update if value changed
            if (jumlah !== originalValue) {
                // Now sync with server in the background
                $.post(`{{ url('/transaksi') }}/${id}`, {
                        '_token': $('[name=csrf-token]').attr('content'),
                        '_method': 'put',
                        'jumlah': jumlah
                    })
                    .done(response => {
                        // Update original value on success
                        $input.data('original-value', jumlah);
                        $input.data('original-quantity', jumlah);
                        // Reload table to ensure data is in sync (but UI already updated)
                        table.ajax.reload(() => loadForm($('#diskon').val()), false);
                    })
                    .fail(errors => {
                        // Revert to original value on error
                        $input.val(originalValue);
                        // Restore original calculations
                        updateQuantityCalculations($input, originalValue, originalValue);
                        
                        let errorMessage = 'Unable to save data';
                        if (errors.responseJSON && errors.responseJSON.message) {
                            errorMessage = errors.responseJSON.message;
                        }
                        alert(errorMessage);
                        // Reload table to get correct values
                        table.ajax.reload(() => loadForm($('#diskon').val()));
                    });
            }
        });

        // Handle Enter key - same as blur
        $(document).on('keydown', '.quantity', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                $(this).blur(); // Trigger blur event which will validate and save
                e.preventDefault();
            }
        });

        $('.btn-simpan').on('click', function(e) {
            e.preventDefault();
            if (validateForm()) {
                $('.form-penjualan').submit();
            }
        });

        // Resume suspended sale button handler
        $('.btn-unsuspend').on('click', function(e) {
            e.preventDefault();
            tampilSuspended();
        });

        // Suspend sale button handler
        $('.btn-suspend').on('click', function(e) {
            e.preventDefault();
            
            // Validate form but skip payment mode requirement for suspend
            if (!validateForm(true)) {
                return false;
            }

            if (!confirm('Suspend this sale? The sale will be saved and you can continue it later from Suspended Sales. A new sale will be prepared.')) {
                return false;
            }

            // Ask whether to print withheld receipt for this sale
            const saleId = ($('#id_penjualan').val() || '').toString();
            if (!saleId) {
                alert('No active sale found. Please refresh and start a new sale from New Sale.');
                return false;
            }
            const wantsWithheldPrint = confirm('Do you want to print the withheld sale receipt?');
            const receiptUrl = '{{ route("transaksi.withheld_receipt", ":id") }}'.replace(':id', saleId);

            syncCurrencyTypeHidden();
            syncPosSaleDateToCheckoutForm();
            // Force checkout form id_penjualan to match the POS cart id
            $('.form-penjualan input[name="id_penjualan"]').val(saleId);
            // Serialize form data
            let formData = $('.form-penjualan').serialize();
            // Extra safety: guarantee the id_penjualan value is included correctly
            if (!formData.includes('id_penjualan=')) {
                formData += '&id_penjualan=' + encodeURIComponent(saleId);
            }
            
            $.ajax({
                url: '{{ route("transaksi.suspend") }}',
                method: 'POST',
                data: formData,
                success: function(response) {
                    if (!response || !response.success) {
                        alert((response && response.message) ? response.message : 'Unable to suspend sale.');
                        return;
                    }

                    const msg = response.message || 'Sale suspended successfully.';

                    if (wantsWithheldPrint && saleId) {
                        var printWindow = null;
                        try {
                            printWindow = window.open(receiptUrl, 'WithheldSaleReceipt_' + saleId, 'height=500,width=450');
                        } catch (err) {
                            printWindow = null;
                        }
                        if (!printWindow || printWindow.closed) {
                            alert('Pop-up was blocked. Allow pop-ups for this site to print the withheld receipt.\n\n' + msg);
                            window.location.href = '{{ route("transaksi.baru") }}';
                            return;
                        }
                        // Give the receipt page time to load and fire window.print() before this tab navigates away.
                        setTimeout(function () {
                            window.location.href = '{{ route("transaksi.baru") }}';
                        }, 2500);
                    } else {
                        alert(msg);
                        window.location.href = '{{ route("transaksi.baru") }}';
                    }
                },
                error: function(xhr) {
                    let errorMessage = 'Unable to suspend sale';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                        errorMessage = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
                    }
                    alert(errorMessage);
                    return;
                }
            });
        });

        function setCheckoutSaving(isSaving) {
            var $btn = $('.btn-simpan');
            if (!$btn.data('orig-html')) {
                $btn.data('orig-html', $btn.html());
            }
            $btn.prop('disabled', !!isSaving);
            $btn.html(isSaving
                ? '<i class="fa fa-spinner fa-spin"></i> Saving…'
                : ($btn.data('orig-html') || '<i class="fa fa-floppy-o"></i> Complete & Save Transaction'));
        }

        function openPosReceiptAfterSave(saleId) {
            const receiptBase = '{{ route("transaksi.nota_kecil", ":id") }}'.replace(':id', saleId);
            const chainQ = receiptBase.indexOf('?') >= 0 ? '&' : '?';
            const chainUrl = receiptBase + chainQ + 'pos_print_chain=1';
            var wReceipt = window.open(chainUrl, 'PosReceipt_' + saleId + '_' + Date.now(), 'height=500,width=450');
            if (wReceipt) {
                wReceipt.focus();
                return true;
            }
            alert('Sale saved, but the receipt window was blocked. Allow pop-ups for this site, or reprint from Sales → Reprint Sold Receipt.');
            window.location.href = '{{ route("transaksi.baru") }}';
            return false;
        }

        // Update form submit handler to mark sale as saved and validate
        $('.form-penjualan').off('submit').on('submit', function(e) {
            e.preventDefault();
            
            // Validate before submitting
            if (!validateForm()) {
                return false;
            }

            if ($('.btn-simpan').prop('disabled')) {
                return false;
            }

            var $checkoutForm = $(this);
            var saleIdCheckout = $('#id_penjualan').val();

            function postCheckoutAjax() {
                syncPosSaleDateToCheckoutForm();
                syncCurrencyTypeHidden();
                syncPosSaleIdToCheckoutForm();

                var formData = $checkoutForm.serialize();
                var paymentMode = $('#paymentMode').val();

                if (paymentMode === 'Split') {
                    var splitCash = parseFloat($('#splitCash').val()) || 0;
                    var splitMpesa = parseFloat($('#splitMpesa').val()) || 0;
                    var splitCard = parseFloat($('#splitCard').val()) || 0;

                    formData += '&splitCash=' + splitCash;
                    formData += '&splitMpesa=' + splitMpesa;
                    formData += '&splitCard=' + splitCard;
                    formData += '&paymentSplitDetails=' + encodeURIComponent(JSON.stringify({
                        cash: splitCash,
                        mpesa: splitMpesa,
                        card: splitCard
                    }));
                }

                setCheckoutSaving(true);

                $.ajax({
                url: $checkoutForm.attr('action'),
                method: 'post',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (!response || typeof response !== 'object' || response.success !== true || !response.id_penjualan) {
                        var failMsg = (response && response.message)
                            ? response.message
                            : 'Sale was not saved. The receipt was not printed. Check your connection and try again.';
                        if (response && response.errors && typeof response.errors === 'object') {
                            var firstKey = Object.keys(response.errors)[0];
                            var firstMsg = firstKey && response.errors[firstKey];
                            if (firstMsg && (Array.isArray(firstMsg) ? firstMsg[0] : firstMsg)) {
                                failMsg = Array.isArray(firstMsg) ? firstMsg[0] : firstMsg;
                            }
                        }
                        alert(failMsg);
                        return;
                    }

                    saleSaved = true;
                    const saleId = response.id_penjualan;

                    try { sessionStorage.setItem('pos_saledate', posNormalizeSaleDateDisplay($('#saledate').val()) || ''); } catch (e) {}
                    if (response.ledger_notice) {
                        try { console.warn('[Supplier ledger] ' + response.ledger_notice); } catch (eL) {}
                    }
                    if (response.ledger_warning) {
                        setTimeout(function () {
                            alert(response.ledger_warning);
                        }, 500);
                    }

                    openPosReceiptAfterSave(saleId);
                },
                error: function(xhr) {
                    let errorMessage = 'Unable to save sale. The receipt was not printed.';
                    if (xhr.status === 0) {
                        errorMessage = 'Network connection lost. The sale was not saved and the receipt was not printed. Check your connection and try Complete & Save again.';
                    } else if (xhr.responseJSON) {
                        if (xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        if (xhr.responseJSON.errors && typeof xhr.responseJSON.errors === 'object') {
                            var firstKey = Object.keys(xhr.responseJSON.errors)[0];
                            var firstMsg = firstKey && xhr.responseJSON.errors[firstKey];
                            if (firstMsg && (Array.isArray(firstMsg) ? firstMsg[0] : firstMsg)) {
                                errorMessage = Array.isArray(firstMsg) ? firstMsg[0] : firstMsg;
                            }
                        }
                    } else if (xhr.responseText && xhr.getResponseHeader('content-type') && xhr.getResponseHeader('content-type').indexOf('json') === -1) {
                        errorMessage = 'Server returned an error. The sale was not saved. Check your connection and try again.';
                    }
                    alert(errorMessage);
                },
                complete: function () {
                    setCheckoutSaving(false);
                }
            });
            }

            $.getJSON(posCartHealthUrl(saleIdCheckout))
                .done(function (r) {
                    if (!r || !r.ok) {
                        posAlertBrokenCartIssues(r);
                        table.ajax.reload(function () {
                            posScrollToFirstBrokenCartLine();
                            loadForm($('#diskon').val());
                        }, false);
                        return;
                    }
                    postCheckoutAjax();
                })
                .fail(function () {
                    alert('Could not verify cart lines. Check your connection and try again. The sale was not saved.');
                });
        });
    });

    function tampilQuickAddProduk() {
        // Prefill item code from current product code field, if any
        const currentCode = $('#kode_produk').val();
        $('#quick_item_code').val(currentCode);
        $('#quick_nama_produk').val('');
        $('#quick_harga_beli').val('0');
        $('#quick_harga_jual').val('');
        $('#quick_stok').val(1);
        $('#quick_shop_name').val('');
        $('#quick_shop_id').val('');
        $('#quickadd-error').addClass('hide').text('');
        $('#quickadd-info').addClass('hide').text('');
        $('#modal-quick-produk').modal('show');
        $('#quick_item_code').focus();
    }

    function hideProduk() {
        $('#modal-produk').modal('hide');
    }

    function pilihProduk(id, kode) {
        $('#id_produk').val(id);
        $('#kode_produk').val(kode);
        hideProduk();
        tambahProduk();
    }

    function tambahProduk(options) {
        options = options || {};
        // Validate required fields
        let id_penjualan = $('#id_penjualan').val();
        let id_produk = $('#id_produk').val();
        
        if (!id_penjualan || !id_produk) {
            alert('Missing required fields. Please select a product.');
            return;
        }
        
        // Debug info
        let formData = $('.form-produk').serialize();
        console.log('Form data being sent:', formData);
        console.log('id_penjualan:', id_penjualan);
        console.log('id_produk:', id_produk);
        
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        
        $.post('{{ route('transaksi.store') }}', formData)
            .done(response => {
                // Play beep sound when product is added
                playBeepSound();
                var focusDetailId = null;
                if (response && typeof response === 'object' && response.id_penjualan_detail != null) {
                    focusDetailId = response.id_penjualan_detail;
                }
                table.ajax.reload(function () {
                    loadForm($('#diskon').val());
                    setTimeout(function () {
                        posScrollCartToNewestLine(focusDetailId);
                        if (options.focusSearch) {
                            focusPosProductSearchForNextItem();
                            return;
                        }
                        var $qty = null;
                        if (focusDetailId != null) {
                            $qty = $('.pos-cart-scroll-panel .dataTables_scrollBody .quantity[data-id="' + focusDetailId + '"]');
                        }
                        if (!$qty || !$qty.length) {
                            $qty = $('.pos-cart-scroll-panel .dataTables_scrollBody tbody tr:last .quantity');
                        }
                        if ($qty && $qty.length) {
                            try {
                                $qty.trigger('focus');
                                var el = $qty[0];
                                if (el && typeof el.select === 'function') {
                                    el.select();
                                }
                            } catch (e) {}
                        } else {
                            focusPosProductSearchForNextItem();
                        }
                    }, 0);
                }, false);
            })
            .fail(errors => {
                console.error('Full error object:', errors);
                let errorMessage = '';
                if (errors.responseJSON && errors.responseJSON.message) {
                    errorMessage = errors.responseJSON.message;
                } else if (errors.responseText) {
                    errorMessage = errors.responseText;
                } else {
                    errorMessage = 'Unknown error occurred';
                }
                alert('Unable to save data. Error: ' + errorMessage);
                return;
            });
    }

    // Handle Quick Add Product form submission
    $('#form-quick-produk').on('submit', function (e) {
        e.preventDefault();

        let form = $(this);
        let errorBox = $('#quickadd-error');
        errorBox.addClass('hide').text('');

        let data = form.serialize();

        $.ajax({
            url: '{{ route("produk.quick_add") }}',
            method: 'POST',
            data: data,
            success: function (response) {
                // Close modal
                $('#modal-quick-produk').modal('hide');

                // Set the new product as selected and add to sale
                if (response.id_produk && response.kode_produk) {
                    $('#id_produk').val(response.id_produk);
                    $('#kode_produk').val(response.kode_produk);

                    // Call existing function to add the product to the sale
                    tambahProduk();
                }

                alert(response.message || 'Product added successfully');
            },
            error: function (xhr) {
                let message = 'Unable to quick add product';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                errorBox.removeClass('hide').text(message);
            }
        });
    });

    // Function to parse input and extract shop code and product search term
    function parseShopCodeAndProduct(input) {
        if (!input || input.trim() === '') {
            return { shopCode: '', productSearch: '' };
        }
        
        const trimmedInput = input.trim();
        
        // Check if input contains a dash (format: SHOPCODE-PRODUCTCODE)
        if (trimmedInput.includes('-')) {
            const dashIndex = trimmedInput.indexOf('-');
            const shopCode = trimmedInput.substring(0, dashIndex);
            const productSearch = trimmedInput.substring(dashIndex + 1).trim();
            return { shopCode: shopCode, productSearch: productSearch };
        }
        
        // Otherwise, split by whitespace
        const parts = trimmedInput.split(/\s+/);
        
        if (parts.length === 0) {
            return { shopCode: '', productSearch: '' };
        }
        
        // First token is the shop code
        const shopCode = parts[0];
        
        // Remaining tokens are the product search term
        const productSearch = parts.slice(1).join(' ');
        
        return { shopCode: shopCode, productSearch: productSearch };
    }

    function hidePosProductSuggestions() {
        productSearchResults.removeClass('is-open').empty();
        posProductSearchState.activeIndex = -1;
    }

    function formatPosProductPrice(price) {
        var p = parseFloat(price) || 0;
        return p > 0 ? 'Ksh ' + p.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) : 'N/A';
    }

    function renderPosProductSuggestions(items) {
        posProductSearchState.items = items || [];
        posProductSearchState.activeIndex = items && items.length ? 0 : -1;
        productSearchResults.empty();
        if (!items || !items.length) {
            productSearchResults.append('<div class="pos-search-empty">No products found</div>');
            productSearchResults.addClass('is-open');
            return;
        }
        items.forEach(function (item, idx) {
            var label = (item.item_code ? item.item_code + ' — ' : '') + item.nama_produk;
            var price = formatPosProductPrice(item.harga_jual);
            var stock = typeof item.stok !== 'undefined' ? item.stok : '-';
            var $row = $('<div class="pos-search-result-item" role="option"></div>')
                .attr('data-index', idx)
                .html(
                    '<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">' +
                    '<strong style="font-size:16px;flex:1 1 auto;">' + $('<span>').text(label).html() + '</strong>' +
                    '<span style="font-size:16px;font-weight:700;white-space:nowrap;">' + price + '</span></div>' +
                    '<span class="text-muted" style="font-size:12px;">Stock: ' + stock + '</span>'
                );
            if (idx === 0) {
                $row.addClass('is-active');
            }
            productSearchResults.append($row);
        });
        productSearchResults.addClass('is-open');
    }

    function focusPosProductSearchForNextItem() {
        hidePosProductSuggestions();
        productSearchInput.val('');
        setTimeout(function () {
            try {
                productSearchInput[0].focus({ preventScroll: true });
            } catch (e) {
                productSearchInput.focus();
            }
        }, 50);
    }

    function selectPosProductFromSearch(item) {
        if (!item || !item.id_produk) {
            return;
        }
        $('#id_produk').val(item.id_produk);
        if ($('#kode_produk').length) {
            $('#kode_produk').val(item.item_code || '');
        }
        hidePosProductSuggestions();
        tambahProduk({ focusSearch: true });
    }

    function selectPosProductByIndex(index) {
        var item = posProductSearchState.items[index];
        if (item) {
            selectPosProductFromSearch(item);
        }
    }

    function fetchPosProductSuggestions() {
        var input = productSearchInput.val() || '';
        var parsed = parseShopCodeAndProduct(input);
        if (!parsed.shopCode) {
            hidePosProductSuggestions();
            return;
        }
        if (!parsed.productSearch || parsed.productSearch.length < 1) {
            hidePosProductSuggestions();
            return;
        }
        if (posProductSearchState.xhr) {
            posProductSearchState.xhr.abort();
        }
        posProductSearchState.xhr = $.getJSON('{{ route('produk.list') }}', {
            q: parsed.productSearch,
            shop_code: parsed.shopCode,
            page: 1
        }).done(function (data) {
            var products = [];
            if (data && Array.isArray(data)) {
                products = data;
            } else if (data && data.results && Array.isArray(data.results)) {
                products = data.results;
            }
            renderPosProductSuggestions(products);
        }).fail(function () {
            renderPosProductSuggestions([]);
        }).always(function () {
            posProductSearchState.xhr = null;
        });
    }

    function posProductSearchMaybeInsertDash(e) {
        var field = productSearchInput[0];
        if (!field) {
            return;
        }
        var currentValue = productSearchInput.val() || '';
        var cursorPos = field.selectionStart;
        var trimmed = currentValue.trim();
        if (cursorPos !== currentValue.length || !trimmed || trimmed.includes('-') || trimmed.includes(' ')) {
            return;
        }
        var shopCodePattern = /^[A-Za-z0-9]+$/;
        if (!shopCodePattern.test(trimmed)) {
            return;
        }
        if (trimmed.length === 1) {
            var newValue = trimmed + '-';
            productSearchInput.val(newValue);
            try { field.setSelectionRange(newValue.length, newValue.length); } catch (err) {}
            productSearchInput.trigger('input');
            return;
        }
        clearTimeout(posProductSearchState.dashTimer);
        posProductSearchState.dashTimer = setTimeout(function () {
            var value = productSearchInput.val() || '';
            var vTrim = value.trim();
            if (!vTrim || vTrim.includes('-') || vTrim.includes(' ')) {
                return;
            }
            if (!shopCodePattern.test(vTrim)) {
                return;
            }
            var nv = vTrim + '-';
            productSearchInput.val(nv);
            try { field.setSelectionRange(nv.length, nv.length); } catch (err2) {}
            productSearchInput.trigger('input');
        }, 120);
    }

    function initProductSearch() {
        posProductSearchState.dashTimer = null;

        productSearchInput.on('input', function (e) {
            posProductSearchMaybeInsertDash(e);
            clearTimeout(posProductSearchState.timer);
            posProductSearchState.timer = setTimeout(fetchPosProductSuggestions, 250);
        });

        productSearchInput.on('keydown', function (e) {
            var items = posProductSearchState.items;
            var open = productSearchResults.hasClass('is-open');
            if (e.key === 'ArrowDown' && open && items.length) {
                e.preventDefault();
                posProductSearchState.activeIndex = Math.min(posProductSearchState.activeIndex + 1, items.length - 1);
                productSearchResults.find('.pos-search-result-item').removeClass('is-active')
                    .eq(posProductSearchState.activeIndex).addClass('is-active');
                return;
            }
            if (e.key === 'ArrowUp' && open && items.length) {
                e.preventDefault();
                posProductSearchState.activeIndex = Math.max(posProductSearchState.activeIndex - 1, 0);
                productSearchResults.find('.pos-search-result-item').removeClass('is-active')
                    .eq(posProductSearchState.activeIndex).addClass('is-active');
                return;
            }
            if (e.key === 'Enter') {
                if (open && items.length) {
                    e.preventDefault();
                    var idx = posProductSearchState.activeIndex >= 0 ? posProductSearchState.activeIndex : 0;
                    selectPosProductByIndex(idx);
                }
                return;
            }
            if (e.key === 'Escape') {
                hidePosProductSuggestions();
            }
        });

        productSearchResults.on('mousedown', '.pos-search-result-item', function (e) {
            e.preventDefault();
            var idx = parseInt($(this).attr('data-index'), 10);
            selectPosProductByIndex(idx);
        });

        productSearchResults.on('mouseenter', '.pos-search-result-item', function () {
            var idx = parseInt($(this).attr('data-index'), 10);
            posProductSearchState.activeIndex = idx;
            productSearchResults.find('.pos-search-result-item').removeClass('is-active');
            $(this).addClass('is-active');
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('.pos-search-select-wrap').length) {
                hidePosProductSuggestions();
            }
        });

        setTimeout(function () {
            productSearchInput.focus();
        }, 300);
    }

    // Currency calculator logic
    // Currency calculator "Convert" button removed from POS footer.
    // Kept modal markup for now, but we don't bind the handler anymore.

    function tampilMember() {
        $('#modal-member').modal('show');
    }

    function pilihMember(id, kode) {
        $('#id_member').val(id);
        $('#kode_member').val(kode);
        $('#diskon').val('{{ $diskon }}');
        loadForm($('#diskon').val());
        hideMember();
    }

    function hideMember() {
        $('#modal-member').modal('hide');
    }

    function tampilSuspended() {
        $('#modal-suspended').modal('show');
        loadSuspendedSales();
    }

    function hideSuspended() {
        $('#modal-suspended').modal('hide');
    }

    function loadSuspendedSales() {
        // Show loading, hide content
        $('#suspended-loading').show();
        $('#suspended-content').hide();
        $('#suspended-empty').hide();

        $.get('{{ route("transaksi.suspended.data") }}')
            .done(function(response) {
                $('#suspended-loading').hide();
                
                if (response.suspendedSales && response.suspendedSales.length > 0) {
                    let tbody = $('#suspended-tbody');
                    tbody.empty();
                    
                    response.suspendedSales.forEach(function(sale) {
                        let row = `
                            <tr>
                                <td width="5%">${sale.index}</td>
                                <td>${sale.date}</td>
                                <td><span class="label label-info">${sale.receiptno}</span></td>
                                <td>${sale.total_item}</td>
                                <td>Ksh ${sale.total_harga}</td>
                                <td>${sale.diskon}</td>
                                <td>${sale.cashier}</td>
                                <td>
                                    <button onclick="pilihSuspended(${sale.id}, '${sale.receiptno}')" 
                                            class="btn btn-success btn-xs btn-flat" 
                                            title="Continue this sale">
                                        <i class="fa fa-play"></i> Continue
                                    </button>
                                </td>
                            </tr>
                        `;
                        tbody.append(row);
                    });
                    
                    $('#suspended-content').show();
                } else {
                    $('#suspended-empty').show();
                }
            })
            .fail(function(errors) {
                $('#suspended-loading').hide();
                $('#suspended-empty').show();
                $('#suspended-empty').html('<i class="fa fa-exclamation-triangle"></i> Unable to load suspended sales.');
            });
    }

    function pilihSuspended(id, receiptno) {
        if (confirm(`Continue suspended sale ${receiptno}? This will load the sale and you can continue adding items.`)) {
            // Go directly to POS with resume param (avoids session loss on redirect/LAN access)
            window.location.href = '{{ url("/transaksi") }}?resume=' + id;
        }
    }

    function deleteData(url) {
        if (!confirm('Remove this line from the sale?')) {
            return;
        }
        var token = $('[name=csrf-token]').attr('content');

        function postDelete(confirmed) {
            var data = {
                '_token': token,
                '_method': 'delete'
            };
            if (confirmed) {
                data.confirmed = '1';
            }
            $.post(url, data)
                .done(function () {
                    table.ajax.reload(function () {
                        loadForm($('#diskon').val());
                    });
                })
                .fail(function (xhr) {
                    if (xhr.status === 409 && xhr.responseJSON && xhr.responseJSON.requires_confirmation) {
                        var j = xhr.responseJSON;
                        var parts = [];
                        if (j.consignment) {
                            parts.push('supplier consignment (pending payout)');
                        }
                        if (j.cash) {
                            parts.push('cash-generated supplier purchase');
                        }
                        var extra = parts.length
                            ? '\n\nThis completed sale is linked to: ' + parts.join(' and ') + '.\nThose records will be removed or reduced to match.'
                            : '';
                        var msg = (j.message || 'Linked supplier records will be updated.') + extra + '\n\nContinue?';
                        if (confirm(msg)) {
                            postDelete(true);
                        }
                        return;
                    }
                    var msg = 'Unable to delete data';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    alert(msg);
                });
        }

        postDelete(false);
    }

    function validateForm(skipPaymentMode = false) {
        if (posCartHasBrokenLineDom()) {
            alert('One or more lines reference a deleted product (highlighted in red in the list). Remove those lines with the trash icon, then complete or print.');
            posScrollToFirstBrokenCartLine();
            return false;
        }

        // Get the original total from the items (before discount) - this is the sum of all item subtotals
        let originalTotal = parseFloat($('.total').text().replace(/[^0-9.-]+/g, '')) || 0;
        
        // Check if there are any items by checking the total
        if (originalTotal === 0 || originalTotal <= 0) {
            alert('Please add at least one product to complete the sale.');
            return false;
        }
        
        // Check if the DataTable has any data rows (excluding the hidden total row)
        if (table && table.rows().count() > 0) {
            let visibleRows = table.rows({search: 'applied'}).nodes().length;
            // Subtract 1 for the hidden total row that's always present
            if (visibleRows <= 1) {
                alert('Please add at least one product to complete the sale.');
                return false;
            }
        } else {
            // Fallback: check if total_item hidden field has value
            let totalItem = parseInt($('#total_item').val()) || 0;
            if (totalItem === 0) {
                alert('Please add at least one product to complete the sale.');
                return false;
            }
        }
        
        // Validate payment mode (skip if suspending sale)
        if (!skipPaymentMode) {
            let paymentMode = $('#paymentMode').val();
            if (!paymentMode || paymentMode === '') {
                alert('Please select a payment mode before completing the sale.');
                $('#paymentMode').focus();
                return false;
            }

            // Validate currency type — at least one checkbox
            syncCurrencyTypeHidden();
            let currencyType = $('#currency_type').val();
            if (!currencyType || currencyType === '') {
                alert('Please select at least one currency type before completing the sale.');
                $('.js-currency-type').first().focus();
                return false;
            }

            // Validate split payment if Split mode is selected
            if (paymentMode === 'Split') {
                let cashAmount = parseFloat($('#splitCash').val()) || 0;
                let mpesaAmount = parseFloat($('#splitMpesa').val()) || 0;
                let cardAmount = parseFloat($('#splitCard').val()) || 0;
                let splitTotal = cashAmount + mpesaAmount + cardAmount;
                
                if (splitTotal <= 0) {
                    alert('Please enter at least one split payment amount.');
                    $('#splitCash').focus();
                    return false;
                }

                // Get total amount due
                let totalDue = parseFloat($('#bayar').val()) || 0;
                let difference = Math.abs(splitTotal - totalDue);
                
                if (difference >= 0.01) {
                    alert('Split payment total (Ksh ' + splitTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ') must equal the amount due (Ksh ' + totalDue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ').');
                    return false;
                }
            }
        }
        
        return true;
    }

    function loadForm(diskon = 0) {
        // Get the current discount type from the radio buttons (always read fresh)
        let currentDiscountType = $('input[name="discount_type"]:checked').val() || 'percentage';
        
        // Get the original total from the display (this is the sum of all items before sale-level discount)
        let total = parseFloat($('.total').text().replace(/[^0-9.-]+/g, '')) || 0;
        
        // Get the discount value from the input field (always read fresh, prioritize input field)
        let discountInputValue = $('#diskon').val();
        let discountValue = 0;
        
        if (discountInputValue !== null && discountInputValue !== undefined && discountInputValue !== '') {
            discountValue = parseFloat(discountInputValue);
        } else if (diskon !== undefined && diskon !== null && diskon !== '') {
            discountValue = parseFloat(diskon);
        }

        // Validate discount value is a valid number
        if (isNaN(discountValue) || discountValue < 0) {
            discountValue = 0;
        }

        // Now calculate the discount based on the discount type
        let calculatedDiskon = 0;
        if (currentDiscountType === 'percentage') {
            // Percentage discount: calculate percentage of total
            // For example: 10% of 3000 = 300
            calculatedDiskon = Math.round((total * discountValue / 100) * 100) / 100;
        } else {
            // Fixed amount discount: use the discount value directly (capped at total)
            // For example: 10 fixed discount on 3000 = subtract exactly 10
            calculatedDiskon = Math.min(Math.abs(discountValue), total);
        }

        // Ensure discount is not negative and doesn't exceed total
        calculatedDiskon = Math.max(0, Math.min(calculatedDiskon, total));

        // Apply discount to get final total
        let finalTotal = Math.max(0, total - calculatedDiskon);

        // Calculate new VAT and subtotal based on final total
        // VAT is 16% inclusive, so tax = finalTotal * (16/116)
        let tax = Math.round((finalTotal * 16 / 116) * 100) / 100;
        let subtotalBeforeTax = Math.round((finalTotal - tax) * 100) / 100;

        // Debug information
        console.log({
            originalTotal: total,
            discountValue: discountValue,
            calculatedDiskon: calculatedDiskon,
            finalTotal: finalTotal,
            tax: tax,
            subtotalBeforeTax: subtotalBeforeTax
        });

        $('#total').val(finalTotal);
        // Get total_item from the hidden total_item div in the DataTable
        let totalItemValue = parseFloat($('.total_item').text().replace(/[^0-9.-]+/g, '')) || 0;
        $('#total_item').val(totalItemValue);
        $('#pos_cart_total_items').text(Math.round(totalItemValue));

        const amountReceived = finalTotal;
        $('#diterima').val(amountReceived);

        // Update discount amount label (Discount = amount given)
        $('#discountAmount').val('Ksh ' + (calculatedDiskon > 0 ? calculatedDiskon.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0}) : '0'));

        let url = `{{ route('transaksi.loadform', [':diskon', ':total', ':diterima', ':tax', ':subtotal']) }}`;
        url = url.replace(':diskon', calculatedDiskon)
                .replace(':total', finalTotal)
                .replace(':diterima', amountReceived)
                .replace(':tax', tax)
                .replace(':subtotal', subtotalBeforeTax);
        
        $.get(url)
            .done(response => {
                // Update display fields with formatted values
                $('#bayarrp').val('Ksh '+ response.bayarrp);
                $('#bayar').val(response.bayar);
                $('#tax').val('Ksh '+ response.taxrp);
                $('#subtotalBeforeTax').val('Ksh '+ response.subtotalBeforeTaxrp);
                
                $('#kembali').val('Ksh '+ response.kembalirp);

                const diterimaValue = parseFloat($('#diterima').val()) || 0;
                const changeValue = Math.max(0, diterimaValue - response.bayar);

                if (changeValue > 0) {
                    $('.tampil-bayar').text('Return: Ksh '+ response.kembalirp);
                    $('.tampil-terbilang').text(response.kembali_terbilang);
                } else {
                    $('.tampil-bayar').text('Pay: Ksh '+ response.bayarrp);
                    $('.tampil-terbilang').text(response.terbilang);
                }
            })
            .fail(errors => {
                alert('Unable to display data');
                return;
            });
    }
</script>
<script src="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
<script>
    $(document).ready(function () {
        posInitSaleDateField();

        $('#saledate').on('change input blur', function () {
            var normalized = posNormalizeSaleDateDisplay($(this).val());
            if (normalized) {
                $(this).val(normalized);
            }
            syncPosSaleDateToCheckoutForm();
            try {
                sessionStorage.setItem('pos_saledate', posNormalizeSaleDateDisplay($('#saledate').val()) || '');
            } catch (e) {}
        });

        // Sync receipt number from visible to hidden field
        $('#ReceiptNo').val($('#visibleReceiptNo').val());

        // Update hidden receipt number when visible one changes
        $('#visibleReceiptNo').on('change', function() {
            $('#ReceiptNo').val($(this).val());
        });
    });
</script>
@endpush
