<form method="POST" action="{{ route('reminders.update-status', $reminder) }}" class="fleet-reminder-complete-form">
    @csrf
    @method('PATCH')
    @if (request('search'))
        <input type="hidden" name="search" value="{{ request('search') }}">
    @endif
    @if (request('status'))
        <input type="hidden" name="status" value="{{ request('status') }}">
    @endif
    @if (request('view'))
        <input type="hidden" name="view" value="{{ request('view') }}">
    @endif
    <label class="fleet-reminder-complete-label">
        <span>Mark Complete</span>
        <span class="fleet-reminder-switch">
            <input type="hidden" name="is_completed" value="0">
            <input type="checkbox" name="is_completed" value="1" {{ $reminder->is_completed ? 'checked' : '' }} onchange="this.form.submit()">
            <span class="fleet-reminder-switch-slider"></span>
        </span>
    </label>
</form>
