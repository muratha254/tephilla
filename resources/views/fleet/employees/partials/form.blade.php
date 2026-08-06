@php
    $field = function ($name, $default = '') use ($employee) {
        return old($name, optional($employee)->{$name} ?? $default);
    };

    $selectedPermissions = old('permissions', optional($employee)->permissions ?? []);
@endphp

<div class="fleet-employee-details-section">
    <h3 class="fleet-employee-section-title">Employee Details</h3>
    <div class="fleet-employee-form-grid">
        <div class="fleet-employee-field">
            <label>First Name <span class="required">*</span></label>
            <input type="text" name="first_name" class="fleet-employee-input" value="{{ $field('first_name') }}" placeholder="Enter First Name" required>
        </div>

        <div class="fleet-employee-field">
            <label>Last Name <span class="required">*</span></label>
            <input type="text" name="last_name" class="fleet-employee-input" value="{{ $field('last_name') }}" placeholder="Enter Last Name" required>
        </div>

        <div class="fleet-employee-field">
            <label>Mobile Number <span class="required">*</span></label>
            <input type="text" name="mobile" class="fleet-employee-input" value="{{ $field('mobile') }}" placeholder="Mobile" required>
        </div>

        <div class="fleet-employee-field">
            <label>Email <span class="required">*</span></label>
            <input type="email" name="email" class="fleet-employee-input" value="{{ $field('email') }}" placeholder="Email" required>
        </div>

        <div class="fleet-employee-field">
            <label>Username <span class="required">*</span></label>
            <input type="text" name="username" class="fleet-employee-input" value="{{ $field('username') }}" placeholder="Enter Username" required>
        </div>

        <div class="fleet-employee-field">
            <label>Password @if (! $employee)<span class="required">*</span>@endif</label>
            <input type="password" name="password" class="fleet-employee-input" placeholder="Password" {{ $employee ? '' : 'required' }} minlength="4">
            @if ($employee)
                <p class="fleet-field-help">Leave blank to keep the current password.</p>
            @endif
        </div>
    </div>
</div>

<div class="fleet-employee-permissions-section">
    <div class="fleet-employee-permissions-head">
        <h3 class="fleet-employee-section-title">Permissions &amp; Access Control</h3>
        <label class="fleet-employee-check-all">
            <input type="checkbox" id="employee-check-all-permissions">
            <span>Check All</span>
        </label>
    </div>

    <div class="fleet-employee-permission-groups">
        @foreach ($permissionGroups as $groupKey => $group)
        <div class="fleet-employee-permission-group">
            <h4 class="fleet-employee-permission-group-title">{{ $group['label'] }}</h4>
            <div class="fleet-employee-permission-grid">
                @foreach ($group['permissions'] as $permissionKey => $permissionLabel)
                <label class="fleet-employee-permission-item">
                    <input
                        type="checkbox"
                        name="permissions[]"
                        value="{{ $permissionKey }}"
                        class="employee-permission-checkbox"
                        data-group="{{ $groupKey }}"
                        {{ in_array($permissionKey, $selectedPermissions, true) ? 'checked' : '' }}
                    >
                    <span>{{ $permissionLabel }}</span>
                </label>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
</div>

<div class="fleet-employee-form-footer">
    <button type="submit" class="fleet-btn fleet-btn-primary"><i class="fa fa-save"></i> Save Employee</button>
</div>
