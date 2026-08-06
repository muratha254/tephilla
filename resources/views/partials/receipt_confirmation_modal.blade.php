{{-- Shared Confirm Receipt modal (Sales List + Receipt Confirmation menu) --}}
<div class="modal fade" id="modal-receipt-confirmation" tabindex="-1" role="dialog">
    <div class="modal-dialog" style="width: 95%; max-width: 1600px;" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Confirm Receipt - <span id="receipt-number-modal"></span></h4>
            </div>
            <div class="modal-body" style="overflow-x: auto;">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr>
                                <th width="40%">Receipt Number</th>
                                <td id="modal-receipt-no"></td>
                            </tr>
                            <tr>
                                <th>Date of Sale</th>
                                <td id="modal-sale-date"></td>
                            </tr>
                            <tr>
                                <th>Total Items</th>
                                <td id="modal-total-items"></td>
                            </tr>
                            <tr>
                                <th>Total Amount</th>
                                <td id="modal-total-amount"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr>
                                <th width="40%">Status</th>
                                <td id="modal-receipt-status"></td>
                            </tr>
                            <tr>
                                <th>Cashier</th>
                                <td id="modal-cashier"></td>
                            </tr>
                            <tr>
                                <th>Member</th>
                                <td id="modal-member"></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="row" style="margin-top: 20px;">
                    <div class="col-md-12">
                        <h4>Items</h4>
                        <div id="waiting-update-alert" class="alert alert-warning" style="display: none;">
                            <i class="fa fa-clock-o"></i> <strong>Quick Add pending.</strong>
                            This receipt has item(s) still in <a href="{{ route('produk.incomplete') }}" target="_blank">Products → Quick Add</a>.
                            Confirmation is disabled until those items are completed or removed.
                        </div>
                        <table class="table table-striped table-bordered" id="modal-items-table">
                            <thead>
                                <tr>
                                    <th width="3%"><input type="checkbox" id="select-all-items" title="Select All"></th>
                                    <th>#</th>
                                    <th>Product Name</th>
                                    <th>Shop</th>
                                    <th>Product Code</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Discount (%)</th>
                                    <th>Subtotal</th>
                                    <th>Item Status</th>
                                    <th width="15%">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="modal-items-body">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="btn-confirm-all-items" onclick="confirmAllItems()">
                    <i class="fa fa-check"></i> Confirm All Items
                </button>
            </div>
        </div>
    </div>
</div>
