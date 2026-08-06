<div class="modal fade" id="modal-form" tabindex="-1" role="dialog" aria-labelledby="modal-form">
    <div class="modal-dialog modal-lg" role="document">
        <form action="" method="post" class="form-horizontal">
            @csrf
            @method('post')

            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"></h4>
                    <span id="add-mode-badge" class="label label-warning" style="display:none; margin-left:8px;">
                        Update mode
                    </span>
                </div>
                <div class="modal-body">
                    <div class="form-group row">
                        <label for="item_code" class="col-lg-2 col-lg-offset-1 control-label">Item Code</label>
                        <div class="col-lg-6">
                            <input type="text" name="item_code" id="item_code" class="form-control" list="item_code_suggestions" autocomplete="off" required>
                            <datalist id="item_code_suggestions"></datalist>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                     <div class="form-group row">
                        <label for="id_kategori" class="col-lg-2 col-lg-offset-1 control-label">Shop</label>
                        <div class="col-lg-6">
                            <select name="shop_id" id="shop_id" class="form-control" required>
                                <option value="">Select Shop</option>
                                @foreach ($shop as $key => $item)
                                <option value="{{ $key }}">{{ $item }}</option>
                                @endforeach
                            </select>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="id_kategori" class="col-lg-2 col-lg-offset-1 control-label">Supplier</label>
                        <div class="col-lg-6">
                            <select name="id_supplier" id="id_supplier" class="form-control" required>
                                <option value="">Select Supplier</option>
                                @foreach ($supplier as $key => $item)
                                <option value="{{ $key }}">{{ $item }}</option>
                                @endforeach
                            </select>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row" style="display: none;">
                        <label for="id_kategori" class="col-lg-2 col-lg-offset-1 control-label">Category</label>
                        <div class="col-lg-6">
                            <select name="id_kategori" id="id_kategori" class="form-control" required>
                                <option value="1" selected>Default Category</option>
                                @foreach ($kategori as $key => $item)
                                <option value="{{ $key }}">{{ $item }}</option>
                                @endforeach
                            </select>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="mop" class="col-lg-2 col-lg-offset-1 control-label">Mode of Payment</label>
                        <div class="col-lg-6">
                            <input type="text" name="mop" id="mop" class="form-control" style="text-transform: uppercase;" readonly required>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row" id="item-code-match-wrap" style="display:none;">
                        <label for="item_code_match_select" class="col-lg-2 col-lg-offset-1 control-label">Matching Item (Shop)</label>
                        <div class="col-lg-6">
                            <select id="item_code_match_select" class="form-control">
                                <option value="">Select matching item</option>
                            </select>
                            <span class="help-block">Same code found in multiple shops. Pick the exact one to auto-fill fields.</span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="nama_produk" class="col-lg-2 col-lg-offset-1 control-label">Commodity</label>
                        <div class="col-lg-6">
                            <input type="text" name="nama_produk" id="nama_produk" class="form-control" required autofocus>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    
                   
                    <div class="form-group row">
                        <label for="harga_beli" class="col-lg-2 col-lg-offset-1 control-label">Purchase Price</label>
                        <div class="col-lg-6">
                            <input type="number" name="harga_beli" id="harga_beli" class="form-control" required>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="harga_jual" class="col-lg-2 col-lg-offset-1 control-label">Selling Price</label>
                        <div class="col-lg-6">
                            <input type="number" name="harga_jual" id="harga_jual" class="form-control" required>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                   
                    <div class="form-group row" id="add-stock-to-add-wrap" style="display:none;">
                        <label for="stock_to_add" class="col-lg-2 col-lg-offset-1 control-label">Quantity to add</label>
                        <div class="col-lg-6">
                            <input type="number" name="stock_to_add" id="stock_to_add" class="form-control" min="1" step="1" disabled placeholder="Units to add to current stock">
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="stok" class="col-lg-2 col-lg-offset-1 control-label" id="label-stok-field">Stock</label>
                        <div class="col-lg-6">
                            <input type="number" name="stok" id="stok" class="form-control" placeholder="Enter stock quantity (optional when editing; 0 or negative allowed on edit)">
                            <span class="help-block with-errors"></span>
                            <p class="help-block text-info" id="hint-stok-existing" style="display:none; margin-bottom:0;">
                                Current stock in this shop (read-only). Enter additional units under “Quantity to add”.
                            </p>
                        </div>
                    </div>
                    <div class="form-group row" id="quickadd-qty-sold-wrap" style="display:none;">
                        <label class="col-lg-2 col-lg-offset-1 control-label">Quantity sold</label>
                        <div class="col-lg-6">
                            <p class="form-control-static" id="quickadd_qty_sold_display" style="font-weight: bold; margin-bottom: 4px;">—</p>
                            <span class="help-block text-muted" style="margin-top: 0;">Units already sold on completed POS sales for this quick-add line (read-only). Stock above is initial units received; remaining on hand = that number minus quantity sold.</span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="diskon" class="col-lg-2 col-lg-offset-1 control-label">Re-Order Level</label>
                        <div class="col-lg-6">
                            <input type="number" name="reorder" id="reorder" class="form-control" value="0">
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="add_stock_date_in" class="col-lg-2 col-lg-offset-1 control-label">Date</label>
                        <div class="col-lg-6">
                            <input type="date" name="date_in" id="add_stock_date_in" class="form-control" required autocomplete="off">
                            <small class="help-block text-muted" style="margin-top:6px;">Type the date, use Tab/arrow keys, or the picker. New “Add Stock” opens with today’s date.</small>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>

                    <input type="hidden" name="is_incomplete" id="is_incomplete" value="">
                    {{-- Set by Add Stock UI when quick_lookup matched this exact produk row (must match server merge target) --}}
                    <input type="hidden" name="existing_id_produk" id="existing_id_produk" value="">

                    <div id="incomplete-auto-match-banner" class="alert alert-info" style="display:none;margin-top:12px;">
                        <i class="fa fa-check-circle"></i>
                        <span id="incomplete-auto-match-text"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-sm btn-flat btn-success" id="btn_modal_save_produk"><i class="fa fa-save"></i> Save</button>
                    <button type="button" class="btn btn-sm btn-flat btn-primary" id="btn_modal_clear_produk" title="Reset all fields (Add Stock: blank form; Edit: reload saved values)"><i class="fa fa-eraser"></i> Clear</button>
                    <button type="button" class="btn btn-sm btn-flat btn-danger" data-dismiss="modal"><i class="fa fa-arrow-circle-left"></i> Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>
<style>
/*
 * Only raise the *dropdown* inside .modal-content (not every Select2 container).
 * A global #modal-form .select2-container--open z-index broke the supplier field: clicks hit the backdrop and closed the modal.
 */
#modal-form .modal-content .select2-dropdown {
    z-index: 2050;
}
#modal-form .modal-content .select2-results__options {
    max-height: 260px;
}
/* Add Stock: reorder is read-only (set elsewhere) */
#modal-form #reorder.reorder-addstock-locked {
    background-color: #eee;
    cursor: not-allowed;
}
</style>
