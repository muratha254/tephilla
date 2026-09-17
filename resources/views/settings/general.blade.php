@extends('layouts.fleet')

@section('title', 'Site Settings')

@php
    $v = function ($key, $fallback = '') use ($site) {
        return old($key, $site[$key] ?? $fallback);
    };
    $yesNo = ['Yes', 'No'];
    $timezones = [
        'Africa/Nairobi', 'Africa/Kampala', 'Africa/Dar_es_Salaam', 'UTC',
        'Africa/Lagos', 'Africa/Johannesburg', 'Europe/London',
    ];
@endphp

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Site Settings',
    'subtitle' => 'Add/Update Site Settings',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Site Settings'],
    ],
])

<div class="sx-box sx-tabs-card">
    <ul class="nav nav-tabs">
        <li class="{{ $tab === 'site' ? 'active' : '' }}"><a href="{{ route('settings.general', ['tab' => 'site']) }}">Site</a></li>
        <li class="{{ $tab === 'sales' ? 'active' : '' }}"><a href="{{ route('settings.general', ['tab' => 'sales']) }}">Sales</a></li>
        <li class="{{ $tab === 'prefixes' ? 'active' : '' }}"><a href="{{ route('settings.general', ['tab' => 'prefixes']) }}">Prefixes</a></li>
    </ul>

    <div class="tab-content" style="padding:18px;">
        @if($tab === 'site')
            <form method="post" action="{{ route('settings.general.update') }}" enctype="multipart/form-data" class="sx-item-form">
                @csrf
                @method('PUT')
                <input type="hidden" name="tab" value="site">

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Site Name <span class="sx-req">*</span></label>
                            <input type="text" name="site_name" class="form-control" value="{{ $v('site_name', fleet_system_name()) }}" required>
                        </div>
                        <div class="form-group">
                            <label>System Version <span class="sx-req">*</span></label>
                            <input type="text" class="form-control" value="{{ $systemVersion }}" disabled>
                        </div>
                        <div class="form-group">
                            <label>Timezone <span class="sx-req">*</span></label>
                            <select name="timezone" class="form-control" required>
                                @foreach($timezones as $tz)
                                    <option value="{{ $tz }}" @if(old('timezone', $company->timezone ?: 'Africa/Nairobi') === $tz) selected @endif>{{ $tz }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date Format <span class="sx-req">*</span></label>
                            <select name="date_format_ui" class="form-control" required>
                                @foreach(['dd-mm-yyyy', 'mm-dd-yyyy', 'yyyy-mm-dd'] as $fmt)
                                    <option value="{{ $fmt }}" @if($v('date_format_ui') === $fmt) selected @endif>{{ $fmt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Time Format <span class="sx-req">*</span></label>
                            <select name="time_format" class="form-control" required>
                                @foreach(['24 Hours', '12 Hours'] as $fmt)
                                    <option value="{{ $fmt }}" @if($v('time_format') === $fmt) selected @endif>{{ $fmt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Currency <span class="sx-req">*</span></label>
                            <select name="currency_code" id="sx-currency-code" class="form-control" required>
                                @if($currencies->isEmpty())
                                    <option value="KES" data-symbol="Ksh" @if(old('currency_code', $company->currency_code) === 'KES') selected @endif>Kenya Shilling (Ksh)</option>
                                    <option value="USD" data-symbol="$" @if(old('currency_code', $company->currency_code) === 'USD') selected @endif>US Dollar ($)</option>
                                    <option value="UGX" data-symbol="USh" @if(old('currency_code', $company->currency_code) === 'UGX') selected @endif>Uganda Shilling (USh)</option>
                                    <option value="TZS" data-symbol="TSh" @if(old('currency_code', $company->currency_code) === 'TZS') selected @endif>Tanzania Shilling (TSh)</option>
                                @else
                                    @foreach($currencies as $currency)
                                        <option value="{{ $currency->code }}" data-symbol="{{ $currency->symbol }}" @if(old('currency_code', $company->currency_code) === $currency->code) selected @endif>
                                            {{ $currency->name }} ({{ $currency->symbol }})
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                            <input type="hidden" name="currency_symbol" id="sx-currency-symbol" value="{{ old('currency_symbol', $company->currency_symbol ?: 'Ksh') }}">
                        </div>
                        <div class="form-group">
                            <label>Currency Symbol Placement <span class="sx-req">*</span></label>
                            <select name="currency_placement" class="form-control" required>
                                @foreach(['Before Amount', 'After Amount'] as $opt)
                                    <option value="{{ $opt }}" @if($v('currency_placement') === $opt) selected @endif>{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Letter Head</label>
                            <input type="file" name="letter_head" class="form-control" accept="image/*">
                            @if(!empty($site['letter_head_path']))
                                <div style="margin-top:6px;"><img src="{{ asset('storage/' . $site['letter_head_path']) }}" style="max-height:50px;"></div>
                                <label class="checkbox-inline"><input type="checkbox" name="remove_letter_head" value="1"> Remove</label>
                            @endif
                        </div>
                        <div class="form-group">
                            <label>Letter Footer</label>
                            <input type="file" name="letter_footer" class="form-control" accept="image/*">
                            @if(!empty($site['letter_footer_path']))
                                <div style="margin-top:6px;"><img src="{{ asset('storage/' . $site['letter_footer_path']) }}" style="max-height:50px;"></div>
                                <label class="checkbox-inline"><input type="checkbox" name="remove_letter_footer" value="1"> Remove</label>
                            @endif
                        </div>
                        <div class="form-group">
                            <label>Login Wallpaper</label>
                            <input type="file" name="login_wallpaper" class="form-control" accept="image/*">
                            @if(!empty($site['login_wallpaper_path']))
                                <div style="margin-top:6px;"><img src="{{ asset('storage/' . $site['login_wallpaper_path']) }}" style="max-height:60px;"></div>
                                <label class="checkbox-inline"><input type="checkbox" name="remove_login_wallpaper" value="1"> Remove</label>
                            @endif
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Site Logo</label>
                            <input type="file" name="logo" class="form-control" accept="image/*">
                            <small class="text-danger" style="display:block;">Max Width/Height: 300px * 300px &amp; Size: 300 px</small>
                            @if($company->logo_path)
                                <div style="margin-top:8px;"><img src="{{ asset('storage/' . $company->logo_path) }}" alt="Logo" style="max-height:80px;"></div>
                                <label class="checkbox-inline"><input type="checkbox" name="remove_logo" value="1"> Remove logo</label>
                            @endif
                        </div>
                        <div class="form-group">
                            <label>Logo Width</label>
                            <small class="text-danger" style="display:block;">Include px e.g 100px</small>
                            <input type="text" name="logo_width" class="form-control" value="{{ $v('logo_width', '150px') }}">
                        </div>
                        <div class="form-group">
                            <label>Logo Height</label>
                            <small class="text-danger" style="display:block;">Include px e.g 100px</small>
                            <input type="text" name="logo_height" class="form-control" value="{{ $v('logo_height', '80px') }}">
                        </div>
                        <div class="form-group">
                            <label>E-Signature</label>
                            <input type="file" name="e_signature" class="form-control" accept="image/*">
                            @if(!empty($site['e_signature_path']))
                                <div style="margin-top:8px;"><img src="{{ asset('storage/' . $site['e_signature_path']) }}" style="max-height:60px;"></div>
                                <label class="checkbox-inline"><input type="checkbox" name="remove_e_signature" value="1"> Remove</label>
                            @else
                                <div style="margin-top:8px;width:120px;height:60px;border:1px solid #ddd;background:#fafafa;"></div>
                            @endif
                        </div>
                    </div>
                </div>

                @if($canUpdate)
                    <div class="sx-form-actions text-center">
                        <button type="submit" class="btn btn-success">Update</button>
                        <a href="{{ route('dashboard') }}" class="btn btn-warning">Close</a>
                    </div>
                @endif
            </form>
        @elseif($tab === 'sales')
            <form method="post" action="{{ route('settings.general.update') }}" class="sx-item-form">
                @csrf
                @method('PUT')
                <input type="hidden" name="tab" value="sales">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Default Tax</label>
                            <input type="text" name="sales_default_tax" class="form-control" value="{{ $v('sales_default_tax') }}" placeholder="e.g. VAT 16%">
                        </div>
                        <div class="form-group">
                            <label>Allow Discount <span class="sx-req">*</span></label>
                            <select name="sales_allow_discount" class="form-control" required>
                                @foreach($yesNo as $opt)<option value="{{ $opt }}" @if($v('sales_allow_discount') === $opt) selected @endif>{{ $opt }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Allow Negative Stock <span class="sx-req">*</span></label>
                            <select name="sales_allow_negative_stock" class="form-control" required>
                                @foreach($yesNo as $opt)<option value="{{ $opt }}" @if($v('sales_allow_negative_stock') === $opt) selected @endif>{{ $opt }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Round Off Totals <span class="sx-req">*</span></label>
                            <select name="sales_round_off" class="form-control" required>
                                @foreach($yesNo as $opt)<option value="{{ $opt }}" @if($v('sales_round_off') === $opt) selected @endif>{{ $opt }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Default Payment Method <span class="sx-req">*</span></label>
                            <select name="sales_default_payment" class="form-control" required>
                                @foreach(['Cash', 'Mpesa', 'Card', 'Bank Transfer', 'Credit'] as $opt)
                                    <option value="{{ $opt }}" @if($v('sales_default_payment') === $opt) selected @endif>{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Invoice Terms</label>
                            <textarea name="sales_invoice_terms" class="form-control" rows="4">{{ $v('sales_invoice_terms') }}</textarea>
                        </div>
                    </div>
                </div>
                @if($canUpdate)
                    <div class="sx-form-actions text-center">
                        <button type="submit" class="btn btn-success">Update</button>
                        <a href="{{ route('dashboard') }}" class="btn btn-warning">Close</a>
                    </div>
                @endif
            </form>
        @else
            <form method="post" action="{{ route('settings.general.update') }}" class="sx-item-form">
                @csrf
                @method('PUT')
                <input type="hidden" name="tab" value="prefixes">
                <div class="row">
                    <div class="col-md-4"><div class="form-group"><label>Invoice Prefix <span class="sx-req">*</span></label><input type="text" name="invoice_prefix" class="form-control" value="{{ $v('invoice_prefix', 'INV-') }}" required></div></div>
                    <div class="col-md-4"><div class="form-group"><label>Receipt Prefix <span class="sx-req">*</span></label><input type="text" name="receipt_prefix" class="form-control" value="{{ $v('receipt_prefix', 'RCP-') }}" required></div></div>
                    <div class="col-md-4"><div class="form-group"><label>Quotation Prefix <span class="sx-req">*</span></label><input type="text" name="quotation_prefix" class="form-control" value="{{ $v('quotation_prefix', 'QT-') }}" required></div></div>
                    <div class="col-md-4"><div class="form-group"><label>PO Prefix <span class="sx-req">*</span></label><input type="text" name="po_prefix" class="form-control" value="{{ $v('po_prefix', 'PO-') }}" required></div></div>
                    <div class="col-md-4"><div class="form-group"><label>GRN Prefix <span class="sx-req">*</span></label><input type="text" name="grn_prefix" class="form-control" value="{{ $v('grn_prefix', 'GRN-') }}" required></div></div>
                    <div class="col-md-4"><div class="form-group"><label>Expense Prefix <span class="sx-req">*</span></label><input type="text" name="expense_prefix" class="form-control" value="{{ $v('expense_prefix', 'EXP-') }}" required></div></div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Receipt Paper Size <span class="sx-req">*</span></label>
                            <select name="receipt_paper_size" class="form-control" required>
                                @foreach(['80mm', '58mm', 'a4'] as $size)
                                    <option value="{{ $size }}" @if($v('receipt_paper_size') === $size) selected @endif>{{ strtoupper($size) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="form-group">
                            <label>Powered By</label>
                            <input type="text" name="powered_by" class="form-control" value="{{ $v('powered_by') }}">
                        </div>
                    </div>
                    <div class="col-md-6"><div class="form-group"><label>Powered By Website</label><input type="text" name="powered_by_website" class="form-control" value="{{ $v('powered_by_website') }}"></div></div>
                    <div class="col-md-6"><div class="form-group"><label>Powered By Email</label><input type="email" name="powered_by_email" class="form-control" value="{{ $v('powered_by_email') }}"></div></div>
                </div>
                @if($canUpdate)
                    <div class="sx-form-actions text-center">
                        <button type="submit" class="btn btn-success">Update</button>
                        <a href="{{ route('dashboard') }}" class="btn btn-warning">Close</a>
                    </div>
                @endif
            </form>
        @endif
    </div>
</div>
@endsection

@if($tab === 'site')
@push('scripts')
<script>
(function ($) {
    function syncSymbol() {
        var symbol = $('#sx-currency-code option:selected').data('symbol') || '';
        $('#sx-currency-symbol').val(symbol);
    }
    $('#sx-currency-code').on('change', syncSymbol);
    syncSymbol();
})(jQuery);
</script>
@endpush
@endif
