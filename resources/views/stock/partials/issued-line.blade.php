@php
    $productId = $line['product_id'] ?? '';
    $unitPrice = $line['unit_price'] ?? '';
    $qty = $line['quantity'] ?? '1';
    $issuedAt = $line['issued_at'] ?? now()->toDateString();
    $notes = $line['notes'] ?? '';
    $subtotal = ($unitPrice !== '' && $qty !== '') ? number_format(((float) $unitPrice) * ((float) $qty), 2, '.', '') : '';
@endphp
<div class="sx-issued-line">
    <div class="sx-issued-line-main">
        <div class="sx-issued-col-item">
            <select name="items[{{ $index }}][product_id]" class="form-control sx-issued-product" required>
                <option value="">--Select Item--</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}" @if((string) $productId === (string) $product->id) selected @endif>{{ $product->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="sx-issued-col-price">
            <input type="number" step="0.01" min="0" name="items[{{ $index }}][unit_price]" class="form-control sx-issued-price" value="{{ $unitPrice }}" placeholder="Unit Price">
        </div>
        <div class="sx-issued-col-qty">
            <input type="number" step="0.01" min="0.01" name="items[{{ $index }}][quantity]" class="form-control sx-issued-qty" value="{{ $qty }}" required>
        </div>
        <div class="sx-issued-col-sub">
            <input type="text" class="form-control sx-issued-sub" value="{{ $subtotal }}" readonly tabindex="-1">
        </div>
        <div class="sx-issued-col-date">
            <input type="date" name="items[{{ $index }}][issued_at]" class="form-control" value="{{ $issuedAt }}" required>
        </div>
        <div class="sx-issued-col-action">
            <button type="button" class="btn btn-danger sx-issued-remove" title="Remove"><i class="fa fa-minus"></i></button>
        </div>
    </div>
    <div class="sx-issued-note">
        <textarea name="items[{{ $index }}][notes]" class="form-control" rows="2" placeholder="Note">{{ $notes }}</textarea>
    </div>
</div>
