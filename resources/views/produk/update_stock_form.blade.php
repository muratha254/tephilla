<div class="modal fade" id="modal-update-stock" tabindex="-1" role="dialog" aria-labelledby="modal-update-stock">
    <div class="modal-dialog" role="document">
        <form action="" method="post" class="form-horizontal" id="form-update-stock">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Update Stock</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group row">
                        <label for="product_name" class="col-lg-3 col-lg-offset-1 control-label">Product</label>
                        <div class="col-lg-7">
                            <input type="text" id="product_name" class="form-control" readonly>
                            <input type="hidden" name="id_produk" id="id_produk">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="current_stock" class="col-lg-3 col-lg-offset-1 control-label">Current Stock</label>
                        <div class="col-lg-7">
                            <input type="text" id="current_stock" class="form-control" readonly>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="additional_stock" class="col-lg-3 col-lg-offset-1 control-label">Additional Stock <span class="text-danger">*</span></label>
                        <div class="col-lg-7">
                            <input type="number" name="additional_stock" id="additional_stock" class="form-control" required min="1" placeholder="Enter quantity to add">
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="date_in_stock" class="col-lg-3 col-lg-offset-1 control-label">Date <span class="text-danger">*</span></label>
                        <div class="col-lg-7">
                            <input type="date" name="date_in" id="date_in_stock" class="form-control" required>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div id="cashPaymentInfo" style="display: none;">
                        <div class="alert alert-info">
                            <strong>Note:</strong> This supplier is on <strong>Cash</strong> payment. The payment will be recorded as outstanding. 
                            You can make the payment from the <strong>Cash Payments</strong> menu.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-sm btn-flat btn-success"><i class="fa fa-save"></i> Update Stock</button>
                    <button type="button" class="btn btn-sm btn-flat btn-danger" data-dismiss="modal"><i class="fa fa-arrow-circle-left"></i> Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>

