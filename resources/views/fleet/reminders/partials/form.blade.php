@php
    $selectedVehicle = old('fleet_vehicle_id', $reminder->fleet_vehicle_id ?? '');
    $selectedDate = old('due_date', $reminder?->due_date?->format('Y-m-d') ?? '');
    $selectedService = old('services', $reminder->services ?? '');
    $selectedMessage = old('notes', $reminder->notes ?? '');
@endphp

<div class="fleet-reminder-add-wrap">
    <div class="fleet-panel fleet-reminder-add-card">
        <form method="POST" action="{{ $formAction }}" class="fleet-reminder-add-form">
            @csrf
            @if (! empty($formMethod) && strtoupper($formMethod) !== 'POST')
                @method($formMethod)
            @endif

            <div class="fleet-reminder-field">
                <label>Vehicle <span class="required">*</span></label>
                <select name="fleet_vehicle_id" class="fleet-reminder-input" required>
                    <option value="">Select Vehicle</option>
                    @foreach ($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" {{ (string) $selectedVehicle === (string) $vehicle->id ? 'selected' : '' }}>
                            {{ $vehicle->displayName() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="fleet-reminder-field">
                <label>Reminder Date <span class="required">*</span></label>
                <input
                    type="date"
                    name="due_date"
                    class="fleet-reminder-input fleet-reminder-date-input"
                    value="{{ $selectedDate }}"
                    required
                    placeholder="Choose reminder date"
                >
            </div>

            <div class="fleet-reminder-field">
                <label>Service</label>
                <input
                    type="text"
                    name="services"
                    class="fleet-reminder-input"
                    value="{{ $selectedService }}"
                    placeholder=""
                >
            </div>

            <div class="fleet-reminder-field">
                <label>Message <span class="required">*</span></label>
                <textarea
                    name="notes"
                    class="fleet-reminder-input fleet-reminder-textarea"
                    rows="5"
                    placeholder="Message"
                    required
                >{{ $selectedMessage }}</textarea>
            </div>

            @if ($reminder)
            <div class="fleet-reminder-field">
                <label class="fleet-reminder-inline-check">
                    <input type="checkbox" name="is_completed" value="1" {{ old('is_completed', $reminder->is_completed) ? 'checked' : '' }}>
                    <span>Mark as completed</span>
                </label>
            </div>
            @endif

            <div class="fleet-reminder-add-actions">
                <button type="submit" class="fleet-btn fleet-btn-primary fleet-reminder-submit-btn">
                    {{ $reminder ? 'Update reminder' : 'Add reminder' }}
                </button>
            </div>
        </form>
    </div>
</div>
