<div class="fleet-stock-card-actions">
    <a href="{{ route('stock.history', $item) }}" class="fleet-stock-action history" title="History"><i class="fa fa-history"></i></a>
    <button type="button" class="fleet-stock-action add" title="Add Stock" data-adjust-stock data-item-id="{{ $item->id }}" data-item-price="{{ $item->unit_price }}"><i class="fa fa-plus"></i></button>
    <a href="{{ route('stock.edit', $item) }}" class="fleet-stock-action edit" title="Edit"><i class="fa fa-pencil"></i></a>
    <form action="{{ route('stock.destroy', $item) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Delete this stock item?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="fleet-stock-action delete" title="Delete"><i class="fa fa-trash"></i></button>
    </form>
</div>
