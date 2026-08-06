@php
    $field = function ($name, $default = '') use ($group) {
        return old($name, optional($group)->{$name} ?? $default);
    };
@endphp

<div class="fleet-form-row">
    <div class="fleet-form-group">
        <label>Group Name <span class="required">*</span></label>
        <input type="text" name="name" class="fleet-input" value="{{ $field('name') }}" placeholder="e.g. North Fleet" required>
    </div>
    <div class="fleet-form-group">
        <label>Status <span class="required">*</span></label>
        <select name="status" class="fleet-input" required>
            <option value="Active" {{ $field('status', 'Active') === 'Active' ? 'selected' : '' }}>Active</option>
            <option value="Inactive" {{ $field('status') === 'Inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>
</div>

<div class="fleet-form-row">
    <div class="fleet-form-group fleet-form-group-full">
        <label>Description</label>
        <textarea name="description" class="fleet-input fleet-textarea" rows="4" placeholder="Optional description for this group">{{ $field('description') }}</textarea>
    </div>
</div>

<div class="fleet-form-actions">
    <button type="submit" class="fleet-btn fleet-btn-primary"><i class="fa fa-save"></i> Save Group</button>
    <a href="{{ route('vehicle-groups.index') }}" class="fleet-btn fleet-btn-light">Cancel</a>
</div>
