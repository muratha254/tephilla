<div class="fleet-fuel-card-actions">
    <a href="{{ route('fuel.edit', $refill) }}" class="fleet-fuel-edit-btn"><i class="fa fa-pencil"></i> Edit</a>
    <form action="{{ route('fuel.destroy', $refill) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Delete this fuel record? Vehicle fuel balance will be adjusted.');">
        @csrf
        @method('DELETE')
        <button type="submit" class="fleet-action-btn delete" title="Delete"><i class="fa fa-trash"></i></button>
    </form>
</div>
