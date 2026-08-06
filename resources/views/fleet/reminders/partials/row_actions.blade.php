<div class="fleet-reminder-row-actions">
    <a href="{{ route('reminders.edit', $reminder) }}" class="fleet-reminder-edit-btn" title="Edit"><i class="fa fa-pencil"></i></a>
    <form action="{{ route('reminders.destroy', $reminder) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Delete this reminder?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="fleet-reminder-delete-btn" title="Delete"><i class="fa fa-trash"></i> Delete</button>
    </form>
</div>
