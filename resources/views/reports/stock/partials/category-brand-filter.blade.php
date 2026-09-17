<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Category Name</label>
            <select name="category_id" class="form-control">
                <option value="">All Categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @if((string) ($filters['category_id'] ?? '') === (string) $category->id) selected @endif>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Brand Name</label>
            <select name="brand_id" class="form-control">
                <option value="">All Brands</option>
                @foreach($brands as $brand)
                    <option value="{{ $brand->id }}" @if((string) ($filters['brand_id'] ?? '') === (string) $brand->id) selected @endif>{{ $brand->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
