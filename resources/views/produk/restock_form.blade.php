<div class="modal fade" id="modal-restock" tabindex="-1" role="dialog" aria-labelledby="modal-restock">
    <div class="modal-dialog" role="document">
        <form action="" method="post" class="form-horizontal">
            @csrf
            @method('post')

            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Restock Product</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group row">
                        <label for="product_select" class="col-lg-2 col-lg-offset-1 control-label">Product</label>
                        <div class="col-lg-6">
                            <select name="id_produk" id="product_select" class="form-control" required>
                                <option value="">Select Product</option>
                            </select>
                            <small class="text-muted" id="restock-product-hint"></small>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="additional_stock" class="col-lg-2 col-lg-offset-1 control-label">Additional Stock</label>
                        <div class="col-lg-6">
                            <input type="number" name="additional_stock" id="additional_stock" class="form-control" required min="1">
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="date_in" class="col-lg-2 col-lg-offset-1 control-label">Date</label>
                        <div class="col-lg-6">
                            <input type="date" name="date_in" id="restock_date" class="form-control" required>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-sm btn-flat btn-success"><i class="fa fa-save"></i> Save</button>
                    <button type="button" class="btn btn-sm btn-flat btn-danger" data-dismiss="modal"><i class="fa fa-arrow-circle-left"></i> Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>
