<div class="modal fade" id="modal-filter" tabindex="-1" role="dialog" aria-labelledby="modal-filter">
    <div class="modal-dialog modal-lg" role="document">
        <form action="{{ route('produk.index') }}" method="get" class="form-horizontal" id="filter-form">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Filter Stock List</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group row">
                        <label for="filter_shop_id" class="col-lg-2 col-lg-offset-1 control-label">Shop</label>
                        <div class="col-lg-6">
                            <select name="shop_id" id="filter_shop_id" class="form-control" style="border-radius: 0 !important;">
                                <option value="">All</option>
                                @foreach ($shop as $key => $item)
                                    <option value="{{ $key }}" {{ request('shop_id') == (string)$key ? 'selected' : '' }}>{{ $item }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="filter_supplier_id" class="col-lg-2 col-lg-offset-1 control-label">Supplier</label>
                        <div class="col-lg-6">
                            <select name="supplier_id" id="filter_supplier_id" class="form-control" style="border-radius: 0 !important;">
                                <option value="">All</option>
                                @foreach ($supplier as $key => $item)
                                    <option value="{{ $key }}" {{ request('supplier_id') == (string)$key ? 'selected' : '' }}>{{ $item }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="filter_category_id" class="col-lg-2 col-lg-offset-1 control-label">Category</label>
                        <div class="col-lg-6">
                            <select name="category_id" id="filter_category_id" class="form-control" style="border-radius: 0 !important;">
                                <option value="">All</option>
                                @foreach ($kategori as $key => $item)
                                    <option value="{{ $key }}" {{ request('category_id') == (string)$key ? 'selected' : '' }}>{{ $item }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="filter_stock_status" class="col-lg-2 col-lg-offset-1 control-label">Stock Status</label>
                        <div class="col-lg-6">
                            <select name="stock_status" id="filter_stock_status" class="form-control" style="border-radius: 0 !important;">
                                <option value="">All</option>
                                <option value="out_of_stock" {{ request('stock_status') == 'out_of_stock' ? 'selected' : '' }}>Out of stock (0 remaining)</option>
                                <option value="low_stock" {{ request('stock_status') == 'low_stock' ? 'selected' : '' }}>Low stock (at or below re-order level)</option>
                                <option value="in_stock" {{ request('stock_status') == 'in_stock' ? 'selected' : '' }}>In stock (above re-order level)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="filter_start_date" class="col-lg-2 col-lg-offset-1 control-label">Date created from</label>
                        <div class="col-lg-6">
                            <input type="text" name="start_date" id="filter_start_date" class="form-control datepicker" autocomplete="off"
                                value="{{ request('start_date') }}"
                                style="border-radius: 0 !important;" placeholder="Optional">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="filter_end_date" class="col-lg-2 col-lg-offset-1 control-label">Date created to</label>
                        <div class="col-lg-6">
                            <input type="text" name="end_date" id="filter_end_date" class="form-control datepicker" autocomplete="off"
                                value="{{ request('end_date') }}"
                                style="border-radius: 0 !important;" placeholder="Optional">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="{{ route('produk.index') }}" class="btn btn-sm btn-flat btn-default">Clear filters</a>
                    <button type="submit" class="btn btn-sm btn-flat btn-success"><i class="fa fa-filter"></i> Apply filters</button>
                    <button type="button" class="btn btn-sm btn-flat btn-danger" data-dismiss="modal"><i class="fa fa-arrow-circle-left"></i> Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>
