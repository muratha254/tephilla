@php
    $field = function ($name, $default = '') use ($vendor) {
        return old($name, optional($vendor)->{$name} ?? $default);
    };

    $dateField = function ($name) use ($vendor) {
        $value = old($name);
        if ($value !== null && $value !== '') {
            return $value;
        }

        $date = optional($vendor)->{$name};

        return $date ? $date->format('Y-m-d') : '';
    };

    $statusValue = old('status', optional($vendor)->is_active === false ? 'Inactive' : 'Active');
@endphp

<div class="fleet-vendor-form-grid">
    <div class="fleet-vendor-field">
        <label>Vendor Name <span class="required">*</span></label>
        <div class="fleet-input-icon-wrap">
            <span class="fleet-input-icon"><i class="fa fa-building-o"></i></span>
            <input type="text" name="company" class="fleet-vendor-input" value="{{ $field('company') }}" placeholder="Enter Vendor Name" required>
        </div>
    </div>

    <div class="fleet-vendor-field">
        <label>Contact Person <span class="required">*</span></label>
        <div class="fleet-input-icon-wrap">
            <span class="fleet-input-icon"><i class="fa fa-user"></i></span>
            <input type="text" name="contact_person" class="fleet-vendor-input" value="{{ $field('contact_person') }}" placeholder="Enter Contact Person" required>
        </div>
    </div>

    <div class="fleet-vendor-field">
        <label>Mobile Number <span class="required">*</span></label>
        <div class="fleet-input-icon-wrap">
            <span class="fleet-input-icon"><i class="fa fa-phone"></i></span>
            <input type="text" name="mobile" class="fleet-vendor-input" value="{{ $field('mobile') }}" placeholder="Enter Mobile Number" required>
        </div>
    </div>

    <div class="fleet-vendor-field">
        <label>Date of Contract <span class="required">*</span></label>
        <div class="fleet-input-icon-wrap">
            <span class="fleet-input-icon"><i class="fa fa-calendar"></i></span>
            <input type="date" name="contract_date" class="fleet-vendor-input" value="{{ $dateField('contract_date') }}" placeholder="Select Date" required>
        </div>
    </div>

    <div class="fleet-vendor-field">
        <label>Status <span class="required">*</span></label>
        <div class="fleet-input-icon-wrap">
            <span class="fleet-input-icon"><i class="fa fa-toggle-on"></i></span>
            <select name="status" class="fleet-vendor-input" required>
                <option value="">Select Status</option>
                <option value="Active" {{ $statusValue === 'Active' ? 'selected' : '' }}>Active</option>
                <option value="Inactive" {{ $statusValue === 'Inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
    </div>

    <div class="fleet-vendor-field">
        <label>Contract Document <span class="required">*</span></label>
        <div class="fleet-file-upload-wrap">
            <input type="text" class="fleet-vendor-input fleet-file-name" id="vendor-contract-name" value="{{ optional($vendor)->contract_doc ? basename($vendor->contract_doc) : 'Choose file' }}" readonly>
            <label class="fleet-file-browse-btn">
                Browse
                <input type="file" name="contract_doc" id="vendor-contract-file" accept=".pdf,.jpg,.jpeg,.png" {{ $vendor ? '' : 'required' }} hidden>
            </label>
        </div>
        <p class="fleet-field-help">Upload PDF or Image (Max 2MB)</p>
        @if (optional($vendor)->contract_doc)
            <p class="fleet-field-help"><a href="{{ asset('storage/' . $vendor->contract_doc) }}" target="_blank">View current document</a></p>
        @endif
    </div>

    <div class="fleet-vendor-field fleet-vendor-field-full">
        <label>Address <span class="required">*</span></label>
        <div class="fleet-input-icon-wrap fleet-input-icon-wrap-textarea">
            <span class="fleet-input-icon"><i class="fa fa-map-marker"></i></span>
            <textarea name="address" class="fleet-vendor-input fleet-vendor-textarea" rows="4" placeholder="Enter Full Address" required>{{ $field('address') }}</textarea>
        </div>
    </div>
</div>

<div class="fleet-vendor-form-footer">
    <button type="submit" class="fleet-btn fleet-btn-primary fleet-btn-save-vendor"><i class="fa fa-save"></i> Save Vendor</button>
</div>
