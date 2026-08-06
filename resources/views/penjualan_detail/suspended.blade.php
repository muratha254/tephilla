<div class="modal fade" id="modal-suspended" tabindex="-1" role="dialog" aria-labelledby="modal-suspended">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Suspended Sales</h4>
            </div>
            <div class="modal-body">
                <div id="suspended-loading" style="text-align: center; padding: 20px;">
                    <i class="fa fa-spinner fa-spin"></i> Loading suspended sales...
                </div>
                <div id="suspended-content" style="display: none;">
                    <table class="table table-striped table-bordered table-suspended table-hover">
                        <thead>
                            <th width="5%">#</th>
                            <th>Date</th>
                            <th>Receipt No</th>
                            <th>Quantity</th>
                            <th>Total Price</th>
                            <th>Discount</th>
                            <th>Cashier</th>
                            <th width="15%"><i class="fa fa-cog"></i></th>
                        </thead>
                        <tbody id="suspended-tbody">
                            <!-- Content will be loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
                <div id="suspended-empty" style="display: none; text-align: center; padding: 20px;">
                    <i class="fa fa-info-circle"></i> No suspended sales found.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default btn-flat" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

























