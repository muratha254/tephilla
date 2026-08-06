<div class="modal fade" id="modal-consignment" tabindex="-1" role="dialog" aria-labelledby="modal-consignment">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Supplier Consignment Details</h4>
            </div>
            <div class="modal-body">
                <div id="consignment-loading" style="text-align: center; padding: 20px;">
                    <i class="fa fa-spinner fa-spin fa-2x"></i> Loading consignment details...
                </div>
                
                <div id="consignment-content" style="display: none;">
                    <!-- Supplier Information -->
                    <div class="box box-info">
                        <div class="box-header with-border">
                            <h3 class="box-title">Supplier Information</h3>
                        </div>
                        <div class="box-body">
                            <table class="table table-bordered" id="consignment-info-table" data-datatable="false" style="margin-bottom: 0;">
                                <tbody>
                                    <tr>
                                        <th width="30%">Name</th>
                                        <td id="consignment-supplier-name">-</td>
                                    </tr>
                                    <tr>
                                        <th>Telephone</th>
                                        <td id="consignment-supplier-phone">-</td>
                                    </tr>
                                    <tr>
                                        <th>Address</th>
                                        <td id="consignment-supplier-address">-</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Selected Consignment -->
                    <div class="box box-warning" id="selected-consignment-box" style="display: none;">
                        <div class="box-header with-border">
                            <h3 class="box-title">Selected Consignment</h3>
                        </div>
                        <div class="box-body">
                            <table class="table table-bordered" style="margin-bottom:0;">
                                <tbody>
                                    <tr>
                                        <th width="25%">Supplier</th>
                                        <td id="selected-consignment-supplier-name">-</td>
                                    </tr>
                                    <tr>
                                        <th>Shop</th>
                                        <td id="selected-consignment-shop">-</td>
                                    </tr>
                                    <tr>
                                        <th>Receipt No</th>
                                        <td id="selected-consignment-invoice">-</td>
                                    </tr>
                                    <tr>
                                        <th>Date</th>
                                        <td id="selected-consignment-date">-</td>
                                    </tr>
                                    <tr>
                                        <th>Commodities</th>
                                        <td id="selected-consignment-product">-</td>
                                    </tr>
                                    <tr>
                                        <th>Stock Out</th>
                                        <td id="selected-consignment-quantity">-</td>
                                    </tr>
                                    <tr>
                                        <th>Buying Price</th>
                                        <td id="selected-consignment-buying-price">-</td>
                                    </tr>
                                    <tr>
                                        <th>Total</th>
                                        <td id="selected-consignment-total">-</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Consignment Summary -->
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">Consignment Summary</h3>
                        </div>
                        <div class="box-body">
                            <table class="table table-bordered" id="consignment-summary-table" data-datatable="false" style="margin-bottom: 0;">
                                <tbody>
                                    <tr id="consignment-total-row" style="display: none;">
                                        <th width="50%">Total Consignment</th>
                                        <td><strong id="consignment-total">Ksh 0.00</strong></td>
                                    </tr>
                                    <tr id="consignment-paid-row" class="success" style="display: none;">
                                        <th>Consignment Paid</th>
                                        <td><strong id="consignment-paid">Ksh 0.00</strong></td>
                                    </tr>
                                    <tr>
                                        <th>Pending Consignment</th>
                                        <td>
                                            <strong>
                                                <span id="consignment-pending-badge" class="label label-success">
                                                    <span id="consignment-pending">Ksh 0.00</span>
                                                </span>
                                            </strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>
                                            <span id="consignment-status-badge" class="label label-default">Not paid</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Paid Items Table -->
                    <div class="box box-success">
                        <div class="box-header with-border">
                            <h3 class="box-title">Consignment Items</h3>
                            <div class="box-tools pull-right">
                                <button type="button" id="btn-export-pdf" class="btn btn-danger btn-sm" style="display: none;">
                                    <i class="fa fa-file-pdf-o"></i> Print PDF
                                </button>
                                <button type="button" id="btn-export-excel" class="btn btn-success btn-sm" style="display: none;">
                                    <i class="fa fa-file-excel-o"></i> Export Excel
                                </button>
                            </div>
                        </div>
                        <div class="box-body">
                            <div id="paid-items-loading" style="text-align: center; padding: 10px;">
                                <i class="fa fa-spinner fa-spin"></i> Loading paid items...
                            </div>
                            <div id="paid-items-table-wrapper" style="display: none;">
                                <table class="table table-bordered table-striped" id="paid-items-table" data-datatable="false">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Receipt No</th>
                                            <th>Date bought</th>
                                            <th>Product</th>
                                            <th>Stock Out</th>
                                            <th>Buying Price</th>
                                            <th>Total Amount</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="paid-items-tbody">
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="7" style="text-align: right;">Grand Total:</th>
                                            <th id="paid-items-total">Ksh 0.00</th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div id="paid-items-empty" style="display: none; text-align: center; padding: 20px;">
                                <p>No items found.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Edit Consignment Item Modal (shared when viewing from Pending or All Consignments) --}}
<div class="modal fade" id="editConsignmentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit Consignment Item</h4>
            </div>
            <div class="modal-body">
                <div id="editConsignmentLoading" style="text-align: center; padding: 20px;">
                    <i class="fa fa-spinner fa-spin fa-2x"></i> Loading...
                </div>
                <div id="editConsignmentFormWrap" style="display: none;">
                    <p class="text-muted" id="editConsignmentProductName"></p>
                    <p class="text-muted small">Invoice: <span id="editConsignmentInvoiceNumber"></span> &middot; Supplier: <span id="editConsignmentSupplierName"></span></p>
                    <input type="hidden" id="editConsignmentItemId">
                    <div class="form-group">
                        <label for="editConsignmentQuantity">Quantity</label>
                        <input type="number" min="1" step="1" class="form-control" id="editConsignmentQuantity" required>
                    </div>
                    <div class="form-group">
                        <label for="editConsignmentAmount">Amount (Ksh)</label>
                        <input type="number" min="0" step="0.01" class="form-control" id="editConsignmentAmount" required>
                    </div>
                    <p class="text-muted small">Amount paid: Ksh <span id="editConsignmentAmountPaid">0.00</span>. Balance will be recalculated from the new amount.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveConsignmentItemBtn"><i class="fa fa-save"></i> Save</button>
            </div>
        </div>
    </div>
</div>

