<div class="modal fade" id="convertToCashModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Convert to Cash - Select Items</h4>
            </div>
            <div class="modal-body">
                <div id="convertToCashItemsLoading" class="text-center" style="padding: 20px;">
                    <i class="fa fa-spinner fa-spin fa-2x"></i> Loading consignment items...
                </div>
                <div id="convertToCashItemsContent" style="display: none;">
                    <div class="alert alert-info">
                        <strong>Supplier:</strong> <span id="convertToCashSupplierName"></span>
                    </div>
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th width="5%">
                                    <input type="checkbox" id="selectAllCashItems" title="Select All">
                                </th>
                                <th>Item Name</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total Amount</th>
                            </tr>
                        </thead>
                        <tbody id="convertToCashItemsTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirmConvertToCashBtn" disabled>
                    <i class="fa fa-exchange"></i> Convert Selected Items
                </button>
            </div>
        </div>
    </div>
</div>






