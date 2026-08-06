@php
    $field = function ($name, $default = '') use ($driver) {
        return old($name, optional($driver)->{$name} ?? $default);
    };

    $dateField = function ($name) use ($driver) {
        $value = old($name);
        if ($value !== null && $value !== '') {
            return $value;
        }

        $date = optional($driver)->{$name};

        return $date ? $date->format('Y-m-d') : '';
    };

    $photoUrl = optional($driver)->photo_path ? asset('storage/' . $driver->photo_path) : null;
@endphp

<div class="fleet-driver-form-grid">
    <div class="fleet-panel fleet-driver-photo-card">
        <div class="fleet-panel-header">Driver Photo</div>
        <div class="fleet-panel-body fleet-driver-photo-panel">
            <div class="fleet-driver-photo-preview" id="driver-photo-preview">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="Driver photo">
                @else
                    <i class="fa fa-user fleet-driver-photo-placeholder"></i>
                @endif
            </div>
            <video id="driver-webcam" class="fleet-driver-webcam" autoplay playsinline hidden></video>
            <canvas id="driver-webcam-canvas" hidden></canvas>
            <div class="fleet-driver-photo-actions">
                <button type="button" class="fleet-btn fleet-btn-capture" id="driver-capture-btn"><i class="fa fa-camera"></i> Capture Photo</button>
                <label class="fleet-btn fleet-btn-upload">
                    <i class="fa fa-upload"></i> Upload Photo
                    <input type="file" name="photo" id="driver-photo-input" accept=".jpg,.jpeg,.png" hidden>
                </label>
            </div>
            <p class="fleet-field-help">Webcam or Upload (JPG, PNG. Max: 2MB)</p>
        </div>
    </div>

    <div class="fleet-panel fleet-driver-form-card">
        <div class="fleet-form-tabs" role="tablist">
            <button type="button" class="fleet-form-tab active" data-tab="personal">Personal Details</button>
            <button type="button" class="fleet-form-tab" data-tab="license">License &amp; Documents</button>
            <button type="button" class="fleet-form-tab" data-tab="employment">Employment</button>
            <button type="button" class="fleet-form-tab" data-tab="compensation">Compensation</button>
        </div>

        <div class="fleet-panel-body">
            <div class="fleet-tab-panel active" data-panel="personal">
                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>Driver Name <span class="required">*</span></label>
                        <input type="text" name="name" class="fleet-input" value="{{ $field('name') }}" placeholder="Full Name" required>
                    </div>
                    <div class="fleet-form-group">
                        <label>Mobile <span class="required">*</span></label>
                        <div class="fleet-input-icon-wrap">
                            <span class="fleet-input-icon"><i class="fa fa-phone"></i></span>
                            <input type="text" name="mobile" class="fleet-vendor-input" value="{{ $field('mobile') }}" placeholder="Mobile Number" required>
                        </div>
                    </div>
                </div>

                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>Email <span class="required">*</span></label>
                        <div class="fleet-input-icon-wrap">
                            <span class="fleet-input-icon"><i class="fa fa-envelope"></i></span>
                            <input type="email" name="email" class="fleet-vendor-input" value="{{ $field('email') }}" placeholder="Email Address" required>
                        </div>
                    </div>
                    <div class="fleet-form-group">
                        <label>Status</label>
                        <select name="status" class="fleet-input">
                            <option value="Active" {{ $field('status', 'Active') === 'Active' ? 'selected' : '' }}>Active</option>
                            <option value="Inactive" {{ $field('status') === 'Inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>Age <span class="required">*</span></label>
                        <input type="number" name="age" class="fleet-input" value="{{ $field('age') }}" placeholder="Age" min="18" max="100" required>
                    </div>
                    <div class="fleet-form-group">
                        <label>Password <span class="required">*</span></label>
                        <input type="password" name="password" id="driver-password" class="fleet-input" placeholder="Login Password" {{ $driver ? '' : 'required' }}>
                        @if ($driver)
                            <p class="fleet-field-help">Leave blank to keep current password.</p>
                        @endif
                    </div>
                </div>

                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>Date Of Joining <span class="required">*</span></label>
                        <div class="fleet-input-icon-wrap">
                            <span class="fleet-input-icon"><i class="fa fa-calendar"></i></span>
                            <input type="date" name="date_of_joining" class="fleet-vendor-input" value="{{ $dateField('date_of_joining') }}" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="fleet-tab-panel" data-panel="license">
                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>License Number</label>
                        <input type="text" name="license_number" class="fleet-input" value="{{ $field('license_number') }}" placeholder="License number">
                    </div>
                    <div class="fleet-form-group">
                        <label>License Expiry</label>
                        <input type="date" name="license_expiry" class="fleet-input" value="{{ $dateField('license_expiry') }}">
                    </div>
                </div>
                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>License Document</label>
                        <input type="file" name="license_doc" class="fleet-input" accept=".pdf,.jpg,.jpeg,.png">
                        @if (optional($driver)->license_doc)
                            <p class="fleet-field-help"><a href="{{ asset('storage/' . $driver->license_doc) }}" target="_blank">View current license document</a></p>
                        @endif
                    </div>
                    <div class="fleet-form-group">
                        <label>ID Number</label>
                        <input type="text" name="id_number" class="fleet-input" value="{{ $field('id_number') }}" placeholder="National ID / Passport">
                    </div>
                </div>
                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>ID Document</label>
                        <input type="file" name="id_doc" class="fleet-input" accept=".pdf,.jpg,.jpeg,.png">
                        @if (optional($driver)->id_doc)
                            <p class="fleet-field-help"><a href="{{ asset('storage/' . $driver->id_doc) }}" target="_blank">View current ID document</a></p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="fleet-tab-panel" data-panel="employment">
                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>Employment Type</label>
                        <select name="employment_type" class="fleet-input">
                            <option value="">Select Type</option>
                            <option value="Full Time" {{ $field('employment_type') === 'Full Time' ? 'selected' : '' }}>Full Time</option>
                            <option value="Part Time" {{ $field('employment_type') === 'Part Time' ? 'selected' : '' }}>Part Time</option>
                            <option value="Contract" {{ $field('employment_type') === 'Contract' ? 'selected' : '' }}>Contract</option>
                        </select>
                    </div>
                    <div class="fleet-form-group">
                        <label>Department</label>
                        <input type="text" name="department" class="fleet-input" value="{{ $field('department') }}" placeholder="Department">
                    </div>
                </div>
                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>Employee ID</label>
                        <input type="text" name="employee_id" class="fleet-input" value="{{ $field('employee_id') }}" placeholder="Employee ID">
                    </div>
                    <div class="fleet-form-group">
                        <label>Contract End Date</label>
                        <input type="date" name="contract_end_date" class="fleet-input" value="{{ $dateField('contract_end_date') }}">
                    </div>
                </div>
            </div>

            <div class="fleet-tab-panel" data-panel="compensation">
                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>Salary</label>
                        <input type="number" step="0.01" min="0" name="salary" class="fleet-input" value="{{ $field('salary') }}" placeholder="0.00">
                    </div>
                    <div class="fleet-form-group">
                        <label>Payment Type</label>
                        <select name="payment_type" class="fleet-input">
                            <option value="">Select Payment Type</option>
                            <option value="Monthly" {{ $field('payment_type') === 'Monthly' ? 'selected' : '' }}>Monthly</option>
                            <option value="Weekly" {{ $field('payment_type') === 'Weekly' ? 'selected' : '' }}>Weekly</option>
                            <option value="Per Trip" {{ $field('payment_type') === 'Per Trip' ? 'selected' : '' }}>Per Trip</option>
                        </select>
                    </div>
                </div>
                <div class="fleet-form-row">
                    <div class="fleet-form-group">
                        <label>Bank Name</label>
                        <input type="text" name="bank_name" class="fleet-input" value="{{ $field('bank_name') }}" placeholder="Bank name">
                    </div>
                    <div class="fleet-form-group">
                        <label>Bank Account</label>
                        <input type="text" name="bank_account" class="fleet-input" value="{{ $field('bank_account') }}" placeholder="Account number">
                    </div>
                </div>
            </div>

            <div class="fleet-driver-form-footer">
                <button type="submit" class="fleet-btn fleet-btn-save-driver"><i class="fa fa-save"></i> Save Driver</button>
            </div>
        </div>
    </div>
</div>
