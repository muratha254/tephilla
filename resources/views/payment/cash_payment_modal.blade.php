<div class="modal fade" id="cashPaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <form id="cashPaymentForm">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title">Process Cash Payment</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Supplier:</label>
                        <p class="form-control-static" id="cashSupplierName"></p>
                        <input type="hidden" id="cashSupplierId">
                    </div>
                    <div class="form-group">
                        <label>Total Outstanding:</label>
                        <p class="form-control-static" id="cashPendingAmount"></p>
                    </div>
                    <div class="form-group">
                        <label>Selected Outstanding:</label>
                        <p class="form-control-static" id="cashTotalOutstanding">Ksh 0.00</p>
                    </div>
                    
                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th width="5%">Select</th>
                                    <th>Purchase Date</th>
                                    <th>Items</th>
                                    <th>Total Amount</th>
                                    <th>Paid</th>
                                    <th>Outstanding</th>
                                </tr>
                            </thead>
                            <tbody id="cashPaymentItems">
                            </tbody>
                        </table>
                    </div>

                    <div class="form-group">
                        <label for="cashPaymentAmount">Payment Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control" id="cashPaymentAmount" required>
                    </div>
                    <div class="form-group">
                        <label for="cashPaymentDate">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="cashPaymentDate" required>
                    </div>
                    <div class="form-group">
                        <label for="cashPaymentMethod">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-control" id="cashPaymentMethod" required>
                            <option value="">Select Payment Method</option>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                            <option value="M-Pesa">M-Pesa</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="cashPaymentSubmitBtn">
                        <i class="fa fa-money"></i> Process Payment
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>














