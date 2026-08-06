<div class="fleet-maint-card-actions{{ ! empty($compact) ? ' is-compact' : '' }}">
    <a href="{{ route('maintenance.print', $maintenance) }}" class="fleet-maint-card-action print fleet-maint-print-btn" title="Print" target="_blank" rel="noopener">
        <i class="fa fa-print"></i>
    </a>
    <a href="{{ route('maintenance.show', $maintenance) }}" class="fleet-maint-card-action view" title="View">
        <i class="fa fa-eye"></i>
    </a>
    <a href="{{ route('maintenance.edit', $maintenance) }}" class="fleet-maint-card-action edit" title="Edit">
        <i class="fa fa-pencil"></i>
    </a>
    <form action="{{ route('maintenance.destroy', $maintenance) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Delete this maintenance record?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="fleet-maint-card-action delete" title="Delete">
            <i class="fa fa-trash"></i>
        </button>
    </form>
</div>
