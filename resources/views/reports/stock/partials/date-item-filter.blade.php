<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>From Date</label>
            <input type="date" name="from" class="form-control" value="{{ $from }}" required>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>To Date</label>
            <input type="date" name="to" class="form-control" value="{{ $to }}" required>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Item Name</label>
            <select name="product_id" class="form-control">
                <option value="">All Items</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}" @if((string) ($productId ?? '') === (string) $product->id) selected @endif>{{ $product->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
