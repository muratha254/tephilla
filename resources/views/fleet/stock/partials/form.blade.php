@php
    $selectedStatus = old('status', $item->status ?? '');
    $isEdit = ! empty($item);
@endphp

<div class="fleet-panel fleet-stock-manage-card">
    <form method="POST" action="{{ $formAction }}" class="fleet-stock-manage-form">
        @csrf
        @if (! empty($formMethod) && strtoupper($formMethod) !== 'POST')
            @method($formMethod)
        @endif

        <div class="fleet-stock-manage-row">
            <label for="stock-part-name">Part Name</label>
            <div class="fleet-stock-manage-field">
                <input type="text" id="stock-part-name" name="name" class="fleet-stock-manage-input" value="{{ old('name', $item->name ?? '') }}" placeholder="Enter name of part" required>
            </div>
        </div>

        <div class="fleet-stock-manage-row">
            <label for="stock-part-description">Part Description</label>
            <div class="fleet-stock-manage-field">
                <textarea id="stock-part-description" name="description" class="fleet-stock-manage-input fleet-stock-manage-textarea" rows="4" placeholder="Part description">{{ old('description', $item->description ?? '') }}</textarea>
            </div>
        </div>

        <div class="fleet-stock-manage-row">
            <label for="stock-opening-qty">No of Opening Stock</label>
            <div class="fleet-stock-manage-field">
                <input type="number" min="0" id="stock-opening-qty" name="quantity" class="fleet-stock-manage-input" value="{{ old('quantity', $item->quantity ?? '') }}" placeholder="Total opening stocks available" required>
            </div>
        </div>

        <div class="fleet-stock-manage-row">
            <label for="stock-part-price">Price</label>
            <div class="fleet-stock-manage-field">
                <input type="number" step="0.01" min="0" id="stock-part-price" name="unit_price" class="fleet-stock-manage-input" value="{{ old('unit_price', $item->unit_price ?? '') }}" placeholder="Part Price" required>
            </div>
        </div>

        <div class="fleet-stock-manage-row">
            <label for="stock-part-status">Part Status</label>
            <div class="fleet-stock-manage-field">
                <select id="stock-part-status" name="status" class="fleet-stock-manage-input" required>
                    <option value="" disabled {{ $selectedStatus === '' ? 'selected' : '' }}>Choose Status</option>
                    <option value="Active" {{ $selectedStatus === 'Active' ? 'selected' : '' }}>Active</option>
                    <option value="Inactive" {{ $selectedStatus === 'Inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
        </div>

        <div class="fleet-stock-manage-actions">
            @if ($isEdit)
                <a href="{{ route('stock.index') }}" class="fleet-btn fleet-btn-default">Cancel</a>
                <button type="submit" class="fleet-btn fleet-btn-primary fleet-stock-manage-submit">Update</button>
            @else
                <button type="submit" class="fleet-btn fleet-btn-primary fleet-stock-manage-submit">Add</button>
            @endif
        </div>
    </form>
</div>
