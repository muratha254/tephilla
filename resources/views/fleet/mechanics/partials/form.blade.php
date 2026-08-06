@php
    $selectedSpecialty = old('specialty', $mechanic->specialty ?? 'Engine');
@endphp

<div class="fleet-panel fleet-mechanic-form-card">
    <div class="fleet-mechanic-form-head">{{ $mechanic ? 'Edit Mechanic' : 'Add New Mechanic' }}</div>
    <div class="fleet-mechanic-form-body">
        <div class="fleet-mechanic-form-grid">
            <div class="fleet-mechanic-field">
                <label>Name</label>
                <input type="text" name="name" class="fleet-mechanic-input" value="{{ old('name', $mechanic->name ?? '') }}" placeholder="Mechanic name" required>
            </div>

            <div class="fleet-mechanic-field">
                <label>Specialty</label>
                <select name="specialty" class="fleet-mechanic-input" required>
                    @foreach ($specialties as $specialty)
                        <option value="{{ $specialty }}" {{ $selectedSpecialty === $specialty ? 'selected' : '' }}>{{ $specialty }}</option>
                    @endforeach
                </select>
            </div>

            <div class="fleet-mechanic-field">
                <label>Email</label>
                <input type="email" name="email" class="fleet-mechanic-input" value="{{ old('email', $mechanic->email ?? '') }}" placeholder="email@example.com">
            </div>

            <div class="fleet-mechanic-field">
                <label>Phone</label>
                <input type="text" name="phone" class="fleet-mechanic-input" value="{{ old('phone', $mechanic->phone ?? '') }}" placeholder="+254712345678">
            </div>
        </div>

        <div class="fleet-mechanic-form-actions">
            <a href="{{ route('mechanics.index') }}" class="fleet-btn fleet-btn-default">Cancel</a>
            <button type="submit" class="fleet-btn fleet-btn-primary">
                <i class="fa fa-save"></i> {{ $mechanic ? 'Update Mechanic' : 'Save Mechanic' }}
            </button>
        </div>
    </div>
</div>
