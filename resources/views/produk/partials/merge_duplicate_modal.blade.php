<div class="modal fade" id="modal-merge-duplicate" tabindex="-1" role="dialog" aria-labelledby="modal-merge-duplicate-title">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="modal-merge-duplicate-title"><i class="fa fa-compress"></i> Merge duplicate stock</h4>
            </div>
            <div class="modal-body">
                <p class="text-muted" style="margin-top: 0;">
                    Combine two rows for the same physical item (e.g. typo codes <strong>fsd434</strong> vs <strong>SD434</strong>).
                    All past sales, stock history, and purchases move to the product you keep. The other row is deleted.
                </p>
                <div class="well well-sm" id="merge-remove-summary" style="margin-bottom: 14px;"></div>
                <div class="form-group">
                    <label for="merge_keep_select">Keep this product (search code or name)</label>
                    <select id="merge_keep_select" class="form-control" style="width: 100%;"></select>
                    <p class="help-block">Usually keep the row with the correct code and more complete history (e.g. <strong>SD434</strong>, not <strong>fsd434</strong>).</p>
                </div>
                <input type="hidden" id="merge_remove_id" value="">
                <input type="hidden" id="merge_remove_shop_id" value="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default btn-flat" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning btn-flat" id="btn_confirm_merge_duplicate"><i class="fa fa-compress"></i> Merge</button>
            </div>
        </div>
    </div>
</div>
