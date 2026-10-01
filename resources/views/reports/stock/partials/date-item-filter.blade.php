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
    @if(isset($categories))
    <div class="col-md-6">
        <div class="form-group">
            <label>Category</label>
            <select name="category_id" class="form-control">
                <option value="">All categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @if((string) ($categoryId ?? '') === (string) $category->id) selected @endif>{{ $category->optionLabel() }}</option>
                @endforeach
            </select>
        </div>
    </div>
    @endif
    @if(isset($variants))
    <div class="col-md-6">
        <div class="form-group">
            <label>Colour</label>
            <select name="product_variant_id" class="form-control">
                <option value="">All colours</option>
                @foreach($variants as $variant)
                    <option value="{{ $variant->id }}" @if((string) ($variantId ?? '') === (string) $variant->id) selected @endif>{{ optional($variant->product)->name }} {{ $variant->color }}</option>
                @endforeach
            </select>
        </div>
    </div>
    @endif
    @if(isset($users))
    <div class="col-md-6">
        <div class="form-group">
            <label>User</label>
            <select name="user_id" class="form-control">
                <option value="">All users</option>
                @foreach($users as $reportUser)
                    <option value="{{ $reportUser->id }}" @if((string) ($userId ?? '') === (string) $reportUser->id) selected @endif>{{ $reportUser->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    @endif
</div>
