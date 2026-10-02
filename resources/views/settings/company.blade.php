@extends('layouts.fleet')

@section('title', 'Company Profile')

@php
    $yesNo = ['Yes' => 'Yes', 'No' => 'No'];
    $v = function ($key, $fallback = '') use ($profile) {
        return old($key, $profile[$key] ?? $fallback);
    };
@endphp

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Company Profile',
    'subtitle' => 'Add/Update Company Profile',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Company Profile'],
    ],
])

<div class="sx-box sx-tabs-card">
    <ul class="nav nav-tabs">
        <li class="{{ $tab === 'basic' ? 'active' : '' }}"><a href="{{ route('settings.company', ['tab' => 'basic']) }}">Basic Settings</a></li>
        <li class="{{ $tab === 'receipt' ? 'active' : '' }}"><a href="{{ route('settings.company', ['tab' => 'receipt']) }}">Receipt Settings</a></li>
        <li class="{{ $tab === 'till' ? 'active' : '' }}"><a href="{{ route('settings.company', ['tab' => 'till']) }}">Till/Paybill Settings</a></li>
    </ul>

    <div class="tab-content" style="padding:18px;">
        @if($tab === 'basic')
            <form method="post" action="{{ route('settings.company.update') }}" enctype="multipart/form-data" class="sx-item-form">
                @csrf
                @method('PUT')
                <input type="hidden" name="tab" value="basic">

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Customer Setup <span class="sx-req">*</span></label>
                            <select name="customer_setup" class="form-control" required>
                                @foreach($yesNo as $opt)
                                    <option value="{{ $opt }}" @if($v('customer_setup') === $opt) selected @endif>{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Login UI <span class="sx-req">*</span></label>
                            <select name="login_ui" class="form-control" required>
                                @foreach(['Touch Login2', 'Classic Login', 'Touch Login'] as $opt)
                                    <option value="{{ $opt }}" @if($v('login_ui') === $opt) selected @endif>{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Waiting Time <span class="sx-req">*</span> <small class="text-muted">In Minutes</small></label>
                            <input type="number" min="0" name="waiting_time" class="form-control" value="{{ $v('waiting_time', 15) }}" required>
                        </div>
                        <div class="form-group">
                            <label>Company Name <span class="sx-req">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $company->name) }}" required>
                        </div>
                        <div class="form-group">
                            <label>Phone <span class="sx-req">*</span></label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $company->phone) }}" required>
                        </div>
                        <div class="form-group">
                            <label>Alt. Phone</label>
                            <input type="text" name="phone_alt" class="form-control" value="{{ old('phone_alt', $company->phone_alt) }}">
                        </div>
                        <div class="form-group">
                            <label>Email <span class="sx-req">*</span></label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $company->email) }}" required>
                        </div>
                        <div class="form-group">
                            <label>KRA Pin</label>
                            <input type="text" name="tax_pin" class="form-control" value="{{ old('tax_pin', $company->tax_pin) }}">
                        </div>
                        <div class="form-group">
                            <label>VAT Number</label>
                            <input type="text" name="vat_number" class="form-control" value="{{ old('vat_number', $company->vat_number) }}">
                        </div>
                        <div class="form-group">
                            <label>Employer Code</label>
                            <input type="text" name="employer_code" class="form-control" value="{{ old('employer_code', $company->employer_code) }}">
                        </div>
                        <div class="form-group">
                            <label>Website</label>
                            <input type="text" name="website" class="form-control" value="{{ old('website', $company->website) }}">
                        </div>
                        <div class="form-group">
                            <label>Bank Details</label>
                            <textarea name="bank_details" class="form-control" rows="3">{{ old('bank_details', $company->bank_details) }}</textarea>
                        </div>
                        <div class="form-group">
                            <label>Quotation Terms and Conditions</label>
                            <textarea name="quotation_terms" class="form-control" rows="8" placeholder="{{ \App\Models\Quotation::DEFAULT_TERMS }}">{{ old('quotation_terms', $company->quotation_terms) }}</textarea>
                            <small class="text-muted">Used on new quotations. You can still change the wording on each quotation.</small>
                        </div>
                        <div class="form-group">
                            <label>Country</label>
                            <input type="text" name="country" class="form-control" value="{{ old('country', $company->country ?: 'Kenya') }}">
                        </div>
                        <div class="form-group">
                            <label>State <span class="sx-req">*</span></label>
                            <select name="state" class="form-control">
                                <option value="">-Select-</option>
                                @foreach($counties as $county)
                                    <option value="{{ $county->name }}" @if(old('state', $company->state) === $county->name) selected @endif>{{ $county->name }}</option>
                                @endforeach
                                @if(old('state', $company->state) && ! $counties->contains('name', old('state', $company->state)))
                                    <option value="{{ old('state', $company->state) }}" selected>{{ old('state', $company->state) }}</option>
                                @endif
                            </select>
                        </div>
                        <div class="form-group">
                            <label>City <span class="sx-req">*</span></label>
                            <select name="city" class="form-control">
                                <option value="">-Select-</option>
                                @foreach($cities as $city)
                                    <option value="{{ $city->name }}" @if(old('city', $company->city) === $city->name) selected @endif>{{ $city->name }}</option>
                                @endforeach
                                @if(old('city', $company->city) && ! $cities->contains('name', old('city', $company->city)))
                                    <option value="{{ old('city', $company->city) }}" selected>{{ old('city', $company->city) }}</option>
                                @endif
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Postcode</label>
                            <input type="text" name="postcode" class="form-control" value="{{ old('postcode', $company->postcode) }}">
                        </div>
                        <div class="form-group">
                            <label>Address <span class="sx-req">*</span></label>
                            <textarea name="address" class="form-control" rows="2" required>{{ old('address', $company->address) }}</textarea>
                        </div>
                        <div class="form-group">
                            <label>Company Logo</label>
                            @if($company->logo_path)
                                <div style="margin-bottom:8px;"><img src="{{ asset('storage/' . $company->logo_path) }}" alt="Logo" style="max-height:70px;"></div>
                                <label class="checkbox-inline"><input type="checkbox" name="remove_logo" value="1"> Remove logo</label>
                            @endif
                            <input type="file" name="logo" class="form-control" accept="image/*">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Expiry Alert <span class="sx-req">*</span> <small class="text-muted">Days</small></label>
                            <input type="number" min="0" name="expiry_alert_days" class="form-control" value="{{ $v('expiry_alert_days', 5) }}" required>
                        </div>
                        <div class="form-group">
                            <label>Loyalty Points <span class="sx-req">*</span></label>
                            <input type="number" step="0.01" min="0" name="loyalty_points_rate" class="form-control" value="{{ $v('loyalty_points_rate', 0) }}" required>
                            <small class="text-success">Note: Set to Zero(0) if points not applicable.</small>
                        </div>
                        <div class="form-group">
                            <label>Allow Order Sale <span class="sx-req">*</span></label>
                            <select name="allow_order_sale" class="form-control" required>
                                @foreach($yesNo as $opt)<option value="{{ $opt }}" @if($v('allow_order_sale') === $opt) selected @endif>{{ $opt }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Allow Credit Sale <span class="sx-req">*</span></label>
                            <select name="allow_credit_sale" class="form-control" required>
                                @foreach($yesNo as $opt)<option value="{{ $opt }}" @if($v('allow_credit_sale') === $opt) selected @endif>{{ $opt }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Show QTY on POS UI <span class="sx-req">*</span></label>
                            <select name="show_qty_pos" class="form-control" required>
                                @foreach($yesNo as $opt)<option value="{{ $opt }}" @if($v('show_qty_pos') === $opt) selected @endif>{{ $opt }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Show Cost/Pur Price <span class="sx-req">*</span></label>
                            <select name="show_cost_pos" class="form-control" required>
                                @foreach($yesNo as $opt)<option value="{{ $opt }}" @if($v('show_cost_pos') === $opt) selected @endif>{{ $opt }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Show UOM on POS <span class="sx-req">*</span></label>
                            <select name="show_uom_pos" class="form-control" required>
                                @foreach($yesNo as $opt)<option value="{{ $opt }}" @if($v('show_uom_pos') === $opt) selected @endif>{{ $opt }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Show Disc. on POS <span class="sx-req">*</span></label>
                            <select name="show_disc_pos" class="form-control" required>
                                @foreach($yesNo as $opt)<option value="{{ $opt }}" @if($v('show_disc_pos') === $opt) selected @endif>{{ $opt }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Menu Order <span class="sx-req">*</span></label>
                            <select name="menu_order" class="form-control" required>
                                @foreach(['Fast Moving', 'Alphabetical', 'Category'] as $opt)
                                    <option value="{{ $opt }}" @if($v('menu_order') === $opt) selected @endif>{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Quotation Module <span class="sx-req">*</span></label>
                            <select name="quotation_module" class="form-control" required>
                                @foreach($yesNo as $opt)<option value="{{ $opt }}" @if($v('quotation_module') === $opt) selected @endif>{{ $opt }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Delivery Note <span class="sx-req">*</span></label>
                            <select name="delivery_note" class="form-control" required>
                                @foreach($yesNo as $opt)<option value="{{ $opt }}" @if($v('delivery_note') === $opt) selected @endif>{{ $opt }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Task Module <span class="sx-req">*</span></label>
                            <select name="task_module" class="form-control" required>
                                @foreach($yesNo as $opt)<option value="{{ $opt }}" @if($v('task_module') === $opt) selected @endif>{{ $opt }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Enable One Off Sale <span class="sx-req">*</span></label>
                            <select name="enable_one_off_sale" class="form-control" required>
                                @foreach($yesNo as $opt)<option value="{{ $opt }}" @if($v('enable_one_off_sale') === $opt) selected @endif>{{ $opt }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Capture Serial <span class="sx-req">*</span></label>
                            <select name="capture_serial" class="form-control" required>
                                @foreach($yesNo as $opt)<option value="{{ $opt }}" @if($v('capture_serial') === $opt) selected @endif>{{ $opt }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Duplicate Item On POS <span class="sx-req">*</span></label>
                            <select name="duplicate_item_pos" class="form-control" required>
                                @foreach($yesNo as $opt)<option value="{{ $opt }}" @if($v('duplicate_item_pos') === $opt) selected @endif>{{ $opt }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Item Cost Control <span class="sx-req">*</span></label>
                            <select name="item_cost_control" class="form-control" required>
                                @foreach(['Get Average', 'Last Purchase', 'Fixed Cost'] as $opt)
                                    <option value="{{ $opt }}" @if($v('item_cost_control') === $opt) selected @endif>{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Shift Control <span class="sx-req">*</span></label>
                            <select name="shift_control" class="form-control" required>
                                @foreach(['When Shift Invoices are fully settled', 'Allow Open Shifts', 'Force Close Daily'] as $opt)
                                    <option value="{{ $opt }}" @if($v('shift_control') === $opt) selected @endif>{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Default Price <span class="sx-req">*</span></label>
                            <select name="default_price" class="form-control" required>
                                @foreach(['RETAIL PRICE', 'WHOLESALE PRICE', 'COST PRICE'] as $opt)
                                    <option value="{{ $opt }}" @if($v('default_price') === $opt) selected @endif>{{ $opt }}</option>
                                @endforeach
                            </select>
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
        @elseif($tab === 'receipt')
            <form method="post" action="{{ route('settings.company.update') }}" class="sx-item-form">
                @csrf
                @method('PUT')
                <input type="hidden" name="tab" value="receipt">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Receipt Control <span class="sx-req">*</span></label>
                            <select name="receipt_control" class="form-control" required>
                                @foreach(['Print One Copy', 'Print Two Copies', 'Ask Before Print', 'Do Not Print'] as $opt)
                                    <option value="{{ $opt }}" @if($v('receipt_control') === $opt) selected @endif>{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Choose Print <span class="sx-req">*</span></label>
                            <select name="choose_print" class="form-control" required>
                                @foreach(['Invoice Only', 'Receipt Only', 'Invoice and Receipt'] as $opt)
                                    <option value="{{ $opt }}" @if($v('choose_print') === $opt) selected @endif>{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Captain Category <span class="sx-req">*</span></label>
                            <select name="captain_category" class="form-control" required>
                                @foreach(['Captain Without Price', 'Captain With Price', 'Disabled'] as $opt)
                                    <option value="{{ $opt }}" @if($v('captain_category') === $opt) selected @endif>{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                        @foreach([
                            'display_logo' => 'Display Logo',
                            'display_name' => 'Display Name',
                            'display_address' => 'Display Address',
                            'display_phone' => 'Display Phone',
                            'display_postcode' => 'Display Postcode',
                            'display_email' => 'Display Email',
                        ] as $key => $label)
                            <div class="form-group">
                                <label>{{ $label }} <span class="sx-req">*</span></label>
                                <select name="{{ $key }}" class="form-control" required>
                                    @foreach($yesNo as $opt)<option value="{{ $opt }}" @if($v($key) === $opt) selected @endif>{{ $opt }}</option>@endforeach
                                </select>
                            </div>
                        @endforeach
                        <div class="form-group">
                            <label>Printer Type <span class="sx-req">*</span></label>
                            <select name="printer_type" class="form-control" required>
                                @foreach(['Thermal Printer 80mm', 'Thermal Printer 58mm', 'A4 Printer'] as $opt)
                                    <option value="{{ $opt }}" @if($v('printer_type') === $opt) selected @endif>{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Display VAT & KRA PIN <span class="sx-req">*</span></label>
                            <select name="display_vat_kra" class="form-control" required>
                                @foreach($yesNo as $opt)<option value="{{ $opt }}" @if($v('display_vat_kra') === $opt) selected @endif>{{ $opt }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        @foreach([
                            'display_tax_calc' => 'Display TAX Calc.',
                            'display_prev_bal' => 'Display Prev Bal.',
                            'display_standing_bal' => 'Display Standing Bal.',
                        ] as $key => $label)
                            <div class="form-group">
                                <label>{{ $label }} <span class="sx-req">*</span></label>
                                <select name="{{ $key }}" class="form-control" required>
                                    @foreach($yesNo as $opt)<option value="{{ $opt }}" @if($v($key) === $opt) selected @endif>{{ $opt }}</option>@endforeach
                                </select>
                            </div>
                        @endforeach
                        <div class="form-group">
                            <label>Description <span class="sx-req">*</span></label>
                            <input type="text" name="receipt_footer" class="form-control" value="{{ $v('receipt_footer') }}" required>
                        </div>
                        <div class="form-group">
                            <label>Thermal Receipt Style <span class="sx-req">*</span></label>
                            <input type="text" name="thermal_receipt_style" class="form-control" value="{{ $v('thermal_receipt_style', 'dashed') }}" required>
                        </div>
                        <div class="form-group">
                            <label>Thermal Font Size <span class="sx-req">*</span></label>
                            <input type="text" name="thermal_font_size" class="form-control" value="{{ $v('thermal_font_size', '10px') }}" required>
                        </div>
                        <div class="form-group">
                            <label>Thermal Font Family <span class="sx-req">*</span></label>
                            <input type="text" name="thermal_font_family" class="form-control" value="{{ $v('thermal_font_family', 'Tahoma') }}" required>
                            <small class="text-success">Arial, Courier, Helvetica, Tahoma, Verdana</small>
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
            <form method="post" action="{{ route('settings.company.update') }}" class="sx-item-form">
                @csrf
                @method('PUT')
                <input type="hidden" name="tab" value="till">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Till/Paybill</label>
                            <input type="text" name="till_paybill" class="form-control" value="{{ $v('till_paybill') }}" placeholder="TILL NUMBER">
                        </div>
                        <div class="form-group">
                            <label>Acc. No.</label>
                            <small class="text-muted" style="display:block;">Leave blank for till</small>
                            <input type="text" name="till_account_no" class="form-control" value="{{ $v('till_account_no') }}" placeholder="Account NUMBER">
                        </div>
                        <div class="form-group">
                            <label>Till2/Paybill</label>
                            <input type="text" name="till2_paybill" class="form-control" value="{{ $v('till2_paybill') }}" placeholder="TILL NUMBER2">
                        </div>
                        <div class="form-group">
                            <label>Acc. No2</label>
                            <small class="text-muted" style="display:block;">Leave blank for till</small>
                            <input type="text" name="till2_account_no" class="form-control" value="{{ $v('till2_account_no') }}" placeholder="Account NUMBER2">
                        </div>
                        <div class="form-group">
                            <label>Till2/Paybill Display</label>
                            <input type="text" name="till2_display" class="form-control" value="{{ $v('till2_display') }}" placeholder="example.png">
                        </div>
                        <div class="form-group">
                            <label>Branch Desc.</label>
                            <input type="text" name="till_branch_desc" class="form-control" value="{{ $v('till_branch_desc') }}" placeholder="Branch">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Account Name</label>
                            <input type="text" name="bank_account_name" class="form-control" value="{{ $v('bank_account_name') }}" placeholder="ACCOUNT NAME">
                        </div>
                        <div class="form-group">
                            <label>Bank Name</label>
                            <input type="text" name="bank_name" class="form-control" value="{{ $v('bank_name') }}" placeholder="BANK NAME e.g KCB">
                        </div>
                        <div class="form-group">
                            <label>Bank A/C No</label>
                            <input type="text" name="bank_account_no" class="form-control" value="{{ $v('bank_account_no') }}" placeholder="12345678">
                        </div>
                        <div class="form-group">
                            <label>Bank Branch</label>
                            <input type="text" name="bank_branch" class="form-control" value="{{ $v('bank_branch') }}" placeholder="Bank Branch e.g Juja">
                        </div>
                        <div class="form-group">
                            <label>Bank Code</label>
                            <input type="text" name="bank_code" class="form-control" value="{{ $v('bank_code') }}" placeholder="Bank Code">
                        </div>
                        <div class="form-group">
                            <label>Swift Code</label>
                            <input type="text" name="bank_swift" class="form-control" value="{{ $v('bank_swift') }}" placeholder="Swift Code">
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
        @endif
    </div>
</div>
@endsection
