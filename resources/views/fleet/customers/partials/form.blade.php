@php
    $field = function ($name, $default = '') use ($customer) {
        return old($name, optional($customer)->{$name} ?? $default);
    };

    $sameAsMobile = old('whatsapp_same_as_mobile', optional($customer)->whatsapp_same_as_mobile ?? true);
    $notificationsEnabled = old('whatsapp_notifications', optional($customer)->whatsapp_notifications ?? true);
@endphp

<div class="fleet-customer-form-grid">
    <div class="fleet-panel fleet-customer-details-card">
        <div class="fleet-customer-card-head">
            <i class="fa fa-user"></i> Customer Details
        </div>
        <div class="fleet-customer-card-body">
            <div class="fleet-customer-fields-grid">
                <div class="fleet-customer-field">
                    <label>Name <span class="required">*</span></label>
                    <div class="fleet-input-icon-wrap">
                        <span class="fleet-input-icon"><i class="fa fa-user"></i></span>
                        <input type="text" name="name" class="fleet-vendor-input" value="{{ $field('name') }}" placeholder="Customer Name" required>
                    </div>
                </div>

                <div class="fleet-customer-field">
                    <label>Mobile <span class="required">*</span></label>
                    <div class="fleet-input-icon-wrap">
                        <span class="fleet-input-icon"><i class="fa fa-phone"></i></span>
                        <input type="text" name="mobile" id="customer-mobile" class="fleet-vendor-input" value="{{ $field('mobile') }}" placeholder="Customer Mobile" required>
                    </div>
                </div>

                <div class="fleet-customer-field">
                    <label>WhatsApp Number</label>
                    <div class="fleet-input-icon-wrap">
                        <span class="fleet-input-icon"><i class="fa fa-whatsapp"></i></span>
                        <input type="text" name="whatsapp" id="customer-whatsapp" class="fleet-vendor-input {{ $sameAsMobile ? 'is-disabled' : '' }}" value="{{ $field('whatsapp') }}" placeholder="Enter WhatsApp Number" {{ $sameAsMobile ? 'disabled' : '' }}>
                    </div>
                    <label class="fleet-customer-check">
                        <input type="checkbox" name="whatsapp_same_as_mobile" id="customer-same-mobile" value="1" {{ $sameAsMobile ? 'checked' : '' }}>
                        Same as Mobile
                    </label>
                </div>

                <div class="fleet-customer-field">
                    <label>Email</label>
                    <div class="fleet-input-icon-wrap">
                        <span class="fleet-input-icon"><i class="fa fa-envelope"></i></span>
                        <input type="email" name="email" class="fleet-vendor-input" value="{{ $field('email') }}" placeholder="Customer Email">
                    </div>
                </div>

                <div class="fleet-customer-field fleet-customer-field-full">
                    <label>Password</label>
                    <div class="fleet-input-icon-wrap">
                        <span class="fleet-input-icon"><i class="fa fa-lock"></i></span>
                        <input type="password" name="password" class="fleet-vendor-input" placeholder="Login Password" {{ isset($customer) ? '' : 'required' }}>
                    </div>
                </div>

                <div class="fleet-customer-field fleet-customer-field-full">
                    <label>Address <span class="required">*</span></label>
                    <textarea name="address" class="fleet-vendor-input fleet-customer-textarea" rows="5" placeholder="Address" required>{{ $field('address') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="fleet-customer-side">
        <div class="fleet-panel fleet-customer-settings-card">
            <div class="fleet-customer-card-head">
                <i class="fa fa-cog"></i> Settings
            </div>
            <div class="fleet-customer-card-body">
                <div class="fleet-customer-notifications-title">Notifications</div>
                <div class="fleet-customer-setting-row">
                    <div class="fleet-customer-setting-copy">
                        <strong>Enable WhatsApp Notifications</strong>
                        <p>Enable to receive trip updates via WhatsApp.</p>
                    </div>
                    <label class="fleet-toggle">
                        <input type="checkbox" name="whatsapp_notifications" value="1" {{ $notificationsEnabled ? 'checked' : '' }}>
                        <span class="fleet-toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <div class="fleet-customer-actions">
            <button type="submit" class="fleet-btn fleet-btn-primary fleet-btn-block">
                <i class="fa fa-save"></i> {{ $submitLabel ?? 'Add' }}
            </button>
            <a href="{{ route('customers.index') }}" class="fleet-btn fleet-btn-light fleet-btn-block">
                <i class="fa fa-times"></i> Cancel
            </a>
        </div>
    </div>
</div>
