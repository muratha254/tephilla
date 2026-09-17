<div class="modal fade" id="sx-child-stock-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-pencil-square-o"></i> Create Child Stock</h4>
            </div>
            <div class="modal-body">
                <form method="post" id="sx-child-stock-form">
                    @csrf
                    <input type="hidden" name="batch_id" id="sx-child-batch-id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="sx-req">Item Name *</label>
                                <input type="text" name="name" id="sx-child-name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="sx-req">Qty to Convert*</label>
                                <input type="number" step="0.0001" min="0.0001" name="qty_to_convert" id="sx-child-convert" class="form-control" value="1" required>
                            </div>
                            <div class="form-group">
                                <label class="sx-req">Control Qty * <span class="sx-rate-hint">(Control Qty for future 1 Qty Converted)</span></label>
                                <input type="number" step="0.0001" min="0.0001" name="control_qty" id="sx-child-control" class="form-control sx-readonly" readonly required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="sx-req">Reorder Level * <span class="sx-reorder-hint">Set &gt; 0 to allow automatic conversion and = 0 for manual</span></label>
                                <input type="number" step="0.0001" min="0" name="reorder_level" id="sx-child-reorder" class="form-control" value="0" required>
                            </div>
                            <div class="form-group">
                                <label class="sx-req">Qty to Produce *<span class="sx-rate-hint">(Per 1 Qty Converted)</span></label>
                                <input type="number" step="0.0001" min="0.0001" name="qty_to_produce" id="sx-child-produce" class="form-control" value="1" required>
                            </div>
                            <div class="form-group">
                                <label class="sx-req">Want To Break One Parent Now?*</label>
                                <select name="break_now" id="sx-child-break" class="form-control" required>
                                    <option value="">Select Your Choise</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="sx-conv-footer">
                        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
