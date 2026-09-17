@extends('layouts.fleet')

@section('title', 'Settings')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-settings.css') }}?v=6">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">Settings</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li>Settings</li>
    </ul>
</div>

@if (session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
<div class="alert alert-danger">
    <ul class="fleet-form-errors">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="fleet-panel fleet-settings-card">
    <form action="{{ route('settings.general.update') }}" method="POST" enctype="multipart/form-data" class="fleet-settings-form" id="fleet-settings-form">
        @csrf
        @method('PUT')

        <div class="fleet-settings-layout">
            <aside class="fleet-settings-tabs">
                <button type="button" class="fleet-settings-tab active" data-tab="company">Company Info</button>
                <button type="button" class="fleet-settings-tab" data-tab="documents">Document Templates</button>
                <button type="button" class="fleet-settings-tab" data-tab="general">General</button>
                <button type="button" class="fleet-settings-tab" data-tab="display">Display Settings</button>
                <button type="button" class="fleet-settings-tab" data-tab="invoice">Invoice & Reports</button>
                <button type="button" class="fleet-settings-tab" data-tab="map">MAP Settings</button>
                <button type="button" class="fleet-settings-tab" data-tab="mobile">Mobile APP</button>
            </aside>

            <div class="fleet-settings-content">
                <div class="fleet-settings-panel active" data-panel="company">
                    <div class="fleet-settings-system-note">
                        <strong>System name (fixed):</strong> {{ fleet_system_name() }}
                        <span>This appears in the sidebar, login screen, and browser title — not on customer documents.</span>
                    </div>
                    <p class="fleet-settings-panel-note">Configure the business details printed on receipts, invoices, quotations, and exported PDF reports.</p>
                    <div class="fleet-settings-grid">
                        <div class="fleet-settings-field">
                            <label for="company_name">Business / Company Name</label>
                            <input type="text" id="company_name" name="company_name" class="fleet-settings-input" value="{{ old('company_name', $settings->company_name) }}" required placeholder="e.g. One Translines Pvt Ltd">
                        </div>
                        <div class="fleet-settings-field">
                            <label for="company_tax_pin">Tax / PIN Number</label>
                            <input type="text" id="company_tax_pin" name="company_tax_pin" class="fleet-settings-input" value="{{ old('company_tax_pin', $settings->option('company', 'tax_pin')) }}" placeholder="Optional — shown on documents">
                        </div>
                        <div class="fleet-settings-field">
                            <label for="phone_number">Phone Number</label>
                            <input type="text" id="phone_number" name="phone_number" class="fleet-settings-input" value="{{ old('phone_number', $settings->phone_number) }}">
                        </div>
                        <div class="fleet-settings-field">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="fleet-settings-input" value="{{ old('email', $settings->email) }}">
                        </div>
                        <div class="fleet-settings-field">
                            <label for="company_website">Website</label>
                            <input type="text" id="company_website" name="company_website" class="fleet-settings-input" value="{{ old('company_website', $settings->option('company', 'website')) }}" placeholder="https://example.com">
                        </div>
                        <div class="fleet-settings-field fleet-settings-field-full">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" class="fleet-settings-input fleet-settings-textarea" rows="3">{{ old('address', $settings->address) }}</textarea>
                        </div>
                        <div class="fleet-settings-field fleet-settings-field-full">
                            <label for="logo">Company Logo (for documents)</label>
                            <input type="file" id="logo" name="logo" class="fleet-settings-input" accept="image/png,image/jpeg,image/jpg,image/webp">
                            <small class="fleet-settings-help">Recommended: PNG or JPG, max 2MB. Used on receipts, invoices, quotations, and PDF reports — not in the system menu.</small>
                            @if ($settings->logo_path)
                                <div class="fleet-settings-logo-preview">
                                    <img src="{{ asset('storage/' . $settings->logo_path) }}" alt="Company Logo">
                                </div>
                                <label class="fleet-settings-checkbox">
                                    <input type="checkbox" name="remove_logo" value="1"> Remove current logo
                                </label>
                            @endif
                        </div>
                        <div class="fleet-settings-field fleet-settings-field-full">
                            <label>Login page footer</label>
                            <small class="fleet-settings-help">These lines appear on the login screen under the Sign In button.</small>
                        </div>
                        <div class="fleet-settings-field fleet-settings-field-full">
                            <label for="powered_by">Powered By</label>
                            <input type="text" id="powered_by" name="powered_by" class="fleet-settings-input" value="{{ old('powered_by', optional($posSetting)->powered_by) }}" placeholder="Powered By REOPRIME SOLUTIONS LTD 254722555849">
                        </div>
                        <div class="fleet-settings-field">
                            <label for="powered_by_website">Website</label>
                            <input type="text" id="powered_by_website" name="powered_by_website" class="fleet-settings-input" value="{{ old('powered_by_website', optional($posSetting)->powered_by_website) }}" placeholder="www.reoprime.com">
                        </div>
                        <div class="fleet-settings-field">
                            <label for="powered_by_email">Email</label>
                            <input type="text" id="powered_by_email" name="powered_by_email" class="fleet-settings-input" value="{{ old('powered_by_email', optional($posSetting)->powered_by_email) }}" placeholder="info@gmail.com">
                        </div>
                    </div>
                </div>

                <div class="fleet-settings-panel" data-panel="documents">
                    <p class="fleet-settings-panel-note">Upload wide banner images for headers and footers, or add optional header/footer text for each document type. When a header image is uploaded, it replaces the default company block on PDFs. Use <code>{company_name}</code> in footer text to insert your business name automatically.</p>

                    @php
                        $documentTypes = [
                            'invoice' => 'Invoice',
                            'receipt' => 'Receipt',
                            'quotation' => 'Quotation',
                        ];
                    @endphp

                    @foreach ($documentTypes as $docKey => $docLabel)
                    <div class="fleet-settings-document-section">
                        <h3 class="fleet-settings-document-title">{{ $docLabel }}</h3>
                        <div class="fleet-settings-grid">
                            <div class="fleet-settings-field fleet-settings-field-full">
                                <label for="documents_{{ $docKey }}_header_image">{{ $docLabel }} Header Image</label>
                                <input type="file" id="documents_{{ $docKey }}_header_image" name="documents_{{ $docKey }}_header_image" class="fleet-settings-input" accept="image/png,image/jpeg,image/jpg,image/webp">
                                <small class="fleet-settings-help">Wide banner recommended (e.g. 1200×300px, PNG or JPG, max 5MB). Shown full-width at the top of {{ strtolower($docLabel) }} PDFs.</small>
                                @if ($settings->documentHeaderImagePath($docKey))
                                    <div class="fleet-settings-header-preview">
                                        <img src="{{ $settings->documentHeaderImageUrl($docKey) }}" alt="{{ $docLabel }} header preview">
                                    </div>
                                    <label class="fleet-settings-checkbox">
                                        <input type="checkbox" name="remove_documents_{{ $docKey }}_header_image" value="1"> Remove current header image
                                    </label>
                                @endif
                            </div>
                            <div class="fleet-settings-field fleet-settings-field-full">
                                <label for="documents_{{ $docKey }}_header">{{ $docLabel }} Header Text</label>
                                <textarea id="documents_{{ $docKey }}_header" name="documents_{{ $docKey }}_header" class="fleet-settings-input fleet-settings-textarea" rows="2" placeholder="Optional — shown below the header image, or above company details if no image">{{ old('documents_' . $docKey . '_header', $settings->option('documents', $docKey . '.header_text')) }}</textarea>
                                <small class="fleet-settings-help">Example: Registered transporter — License No. ABC123</small>
                            </div>
                            <div class="fleet-settings-field fleet-settings-field-full">
                                <label for="documents_{{ $docKey }}_footer_image">{{ $docLabel }} Footer Image</label>
                                <input type="file" id="documents_{{ $docKey }}_footer_image" name="documents_{{ $docKey }}_footer_image" class="fleet-settings-input" accept="image/png,image/jpeg,image/jpg,image/webp">
                                <small class="fleet-settings-help">Wide banner recommended (e.g. 1200×200px, PNG or JPG, max 5MB). Shown full-width at the bottom of {{ strtolower($docLabel) }} PDFs.</small>
                                @if ($settings->documentFooterImagePath($docKey))
                                    <div class="fleet-settings-header-preview">
                                        <img src="{{ $settings->documentFooterImageUrl($docKey) }}" alt="{{ $docLabel }} footer preview">
                                    </div>
                                    <label class="fleet-settings-checkbox">
                                        <input type="checkbox" name="remove_documents_{{ $docKey }}_footer_image" value="1"> Remove current footer image
                                    </label>
                                @endif
                            </div>
                            <div class="fleet-settings-field fleet-settings-field-full">
                                <label for="documents_{{ $docKey }}_footer">{{ $docLabel }} Footer Text</label>
                                <textarea id="documents_{{ $docKey }}_footer" name="documents_{{ $docKey }}_footer" class="fleet-settings-input fleet-settings-textarea" rows="3" placeholder="{{ $docKey === 'invoice' ? 'Thank you for your business.' : ($docKey === 'receipt' ? 'Thank you for your payment.' : 'This quotation is valid for 30 days.') }}">{{ old('documents_' . $docKey . '_footer', $settings->documentText($docKey, 'footer')) }}</textarea>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="fleet-settings-panel" data-panel="general">
                    <div class="fleet-settings-grid">
                        <div class="fleet-settings-field">
                            <label for="date_format">Date Format</label>
                            <select id="date_format" name="date_format" class="fleet-settings-input">
                                @foreach ($dateFormats as $value => $label)
                                    <option value="{{ $value }}" {{ old('date_format', $settings->date_format) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fleet-settings-field">
                            <label for="auto_backup">Auto Backup (Daily)</label>
                            <select id="auto_backup" name="auto_backup" class="fleet-settings-input">
                                <option value="Enabled" {{ old('auto_backup', $settings->auto_backup) === 'Enabled' ? 'selected' : '' }}>Enabled</option>
                                <option value="Disabled" {{ old('auto_backup', $settings->auto_backup) === 'Disabled' ? 'selected' : '' }}>Disabled</option>
                            </select>
                        </div>
                        <div class="fleet-settings-field">
                            <label for="menu_position">Menu Position</label>
                            <select id="menu_position" name="menu_position" class="fleet-settings-input">
                                <option value="Vertical (Sidebar)" {{ old('menu_position', $settings->menu_position) === 'Vertical (Sidebar)' ? 'selected' : '' }}>Vertical (Sidebar)</option>
                                <option value="Horizontal (Top)" {{ old('menu_position', $settings->menu_position) === 'Horizontal (Top)' ? 'selected' : '' }}>Horizontal (Top)</option>
                            </select>
                        </div>
                        <div class="fleet-settings-field">
                            <label for="booking_id_prefix">Booking ID Prefix</label>
                            <input type="text" id="booking_id_prefix" name="booking_id_prefix" class="fleet-settings-input" value="{{ old('booking_id_prefix', $settings->booking_id_prefix) }}">
                        </div>
                        <div class="fleet-settings-field">
                            <label for="default_timezone">Default TimeZone</label>
                            <select id="default_timezone" name="default_timezone" class="fleet-settings-input">
                                @foreach ($timezones as $timezone)
                                    <option value="{{ $timezone['value'] }}" {{ old('default_timezone', $settings->default_timezone) === $timezone['value'] ? 'selected' : '' }}>{{ $timezone['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="fleet-settings-theme-section">
                        <label class="fleet-settings-section-label">Theme Presets</label>
                        <div class="fleet-settings-theme-presets">
                            @foreach ($themePresets as $presetKey => $preset)
                            <button type="button" class="fleet-settings-preset-btn" data-preset="{{ $presetKey }}">{{ $preset['label'] }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div class="fleet-settings-grid fleet-settings-color-grid">
                        @php
                            $colorFields = [
                                'admin_primary_color' => 'Admin Primary Color',
                                'admin_secondary_color' => 'Admin Secondary Color',
                                'sidebar_gradient_start' => 'Sidebar Gradient Start',
                                'sidebar_gradient_end' => 'Sidebar Gradient End',
                                'sidebar_text_color' => 'Sidebar Text Color',
                            ];
                        @endphp
                        @foreach ($colorFields as $field => $label)
                        <div class="fleet-settings-field">
                            <label for="{{ $field }}">{{ $label }}</label>
                            <div class="fleet-settings-color-wrap">
                                <input type="color" id="{{ $field }}_picker" class="fleet-settings-color-picker" value="{{ old($field, $settings->{$field}) }}">
                                <input type="text" id="{{ $field }}" name="{{ $field }}" class="fleet-settings-input fleet-settings-color-input" value="{{ old($field, $settings->{$field}) }}" required>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="fleet-settings-panel" data-panel="display">
                    <div class="fleet-settings-grid">
                        <div class="fleet-settings-field">
                            <label for="display_records_per_page">Records Per Page</label>
                            <input type="text" id="display_records_per_page" name="display_records_per_page" class="fleet-settings-input" value="{{ old('display_records_per_page', $settings->option('display', 'records_per_page', '25')) }}">
                        </div>
                        <div class="fleet-settings-field">
                            <label for="display_currency_symbol">Currency Symbol</label>
                            <input type="text" id="display_currency_symbol" name="display_currency_symbol" class="fleet-settings-input" value="{{ old('display_currency_symbol', $settings->option('display', 'currency_symbol', 'KSh')) }}">
                        </div>
                    </div>
                </div>

                <div class="fleet-settings-panel" data-panel="invoice">
                    <div class="fleet-settings-grid">
                        <div class="fleet-settings-field">
                            <label for="invoice_prefix">Invoice Prefix</label>
                            <input type="text" id="invoice_prefix" name="invoice_prefix" class="fleet-settings-input" value="{{ old('invoice_prefix', $settings->option('invoice', 'invoice_prefix', 'INV-')) }}">
                        </div>
                    </div>
                </div>

                <div class="fleet-settings-panel" data-panel="map">
                    <div class="fleet-settings-grid">
                        <div class="fleet-settings-field">
                            <label for="map_default_latitude">Default Latitude</label>
                            <input type="text" id="map_default_latitude" name="map_default_latitude" class="fleet-settings-input" value="{{ old('map_default_latitude', $settings->option('map', 'default_latitude')) }}">
                        </div>
                        <div class="fleet-settings-field">
                            <label for="map_default_longitude">Default Longitude</label>
                            <input type="text" id="map_default_longitude" name="map_default_longitude" class="fleet-settings-input" value="{{ old('map_default_longitude', $settings->option('map', 'default_longitude')) }}">
                        </div>
                        <div class="fleet-settings-field">
                            <label for="map_provider">Map Provider</label>
                            <select id="map_provider" name="map_provider" class="fleet-settings-input">
                                @foreach (['OpenStreetMap', 'Google Maps', 'Mapbox'] as $provider)
                                    <option value="{{ $provider }}" {{ old('map_provider', $settings->option('map', 'map_provider', 'OpenStreetMap')) === $provider ? 'selected' : '' }}>{{ $provider }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="fleet-settings-panel" data-panel="mobile">
                    <div class="fleet-settings-grid">
                        <div class="fleet-settings-field">
                            <label for="mobile_app_name">App Name</label>
                            <input type="text" id="mobile_app_name" name="mobile_app_name" class="fleet-settings-input" value="{{ old('mobile_app_name', $settings->option('mobile', 'app_name')) }}">
                        </div>
                        <div class="fleet-settings-field">
                            <label for="mobile_android_url">Android App URL</label>
                            <input type="text" id="mobile_android_url" name="mobile_android_url" class="fleet-settings-input" value="{{ old('mobile_android_url', $settings->option('mobile', 'android_url')) }}">
                        </div>
                        <div class="fleet-settings-field">
                            <label for="mobile_ios_url">iOS App URL</label>
                            <input type="text" id="mobile_ios_url" name="mobile_ios_url" class="fleet-settings-input" value="{{ old('mobile_ios_url', $settings->option('mobile', 'ios_url')) }}">
                        </div>
                    </div>
                </div>

                <div class="fleet-settings-footer">
                    <button type="submit" class="fleet-btn fleet-btn-primary"><i class="fa fa-save"></i> Save Settings</button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var presets = @json($themePresets);

    document.querySelectorAll('.fleet-settings-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var target = this.getAttribute('data-tab');
            document.querySelectorAll('.fleet-settings-tab').forEach(function (item) {
                item.classList.toggle('active', item === tab);
            });
            document.querySelectorAll('.fleet-settings-panel').forEach(function (panel) {
                panel.classList.toggle('active', panel.getAttribute('data-panel') === target);
            });
        });
    });

    document.querySelectorAll('.fleet-settings-preset-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            var preset = presets[this.getAttribute('data-preset')];
            if (!preset) return;

            ['admin_primary_color', 'admin_secondary_color', 'sidebar_gradient_start', 'sidebar_gradient_end', 'sidebar_text_color'].forEach(function (field) {
                var input = document.getElementById(field);
                var picker = document.getElementById(field + '_picker');
                if (input) input.value = preset[field];
                if (picker) picker.value = preset[field];
            });
        });
    });

    document.querySelectorAll('.fleet-settings-color-picker').forEach(function (picker) {
        picker.addEventListener('input', function () {
            var input = document.getElementById(this.id.replace('_picker', ''));
            if (input) input.value = this.value;
        });
    });

    document.querySelectorAll('.fleet-settings-color-input').forEach(function (input) {
        input.addEventListener('input', function () {
            var picker = document.getElementById(this.id + '_picker');
            if (picker && /^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                picker.value = this.value;
            }
        });
    });
})();
</script>
@endpush
