<div class="modal fade" id="modal-payment" tabindex="-1" role="dialog" aria-labelledby="modal-payment">
    <div class="modal-dialog" role="document">
        <form action="" method="post" class="form-horizontal">
            @csrf
            @method('post')

            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Record Advance Salary Payment</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="employee_id" id="payment_employee_id">
                    
                    <div class="alert alert-info">
                        <strong>Outstanding Advance Salary:</strong> <span id="outstanding-advance-display">KES 0.00</span>
                    </div>

                    <div class="form-group row">
                        <label for="payment_amount" class="col-lg-3 col-lg-offset-1 control-label">Payment Amount (KES) <span class="text-danger">*</span></label>
                        <div class="col-lg-6">
                            <input type="number" name="amount" id="payment_amount" class="form-control" step="0.01" min="0" required autofocus>
                            <span class="help-block with-errors">Amount being paid back</span>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="payment_date" class="col-lg-3 col-lg-offset-1 control-label">Payment Date <span class="text-danger">*</span></label>
                        <div class="col-lg-6">
                            <input type="date" name="payment_date" id="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="payment_method" class="col-lg-3 col-lg-offset-1 control-label">Payment Method <span class="text-danger">*</span></label>
                        <div class="col-lg-6">
                            <select name="payment_method" id="payment_method" class="form-control" required>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                                <option value="payroll_deduction">Payroll Deduction</option>
                                <option value="other">Other</option>
                            </select>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="payment_notes" class="col-lg-3 col-lg-offset-1 control-label">Notes</label>
                        <div class="col-lg-6">
                            <textarea name="notes" id="payment_notes" class="form-control" rows="3" placeholder="Optional notes about this payment"></textarea>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>

                    <div class="alert alert-success">
                        <i class="fa fa-info-circle"></i> <strong>Note:</strong> This payment will reduce the employee's outstanding advance salary balance.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-flat btn-success" id="btn-save-payment"><i class="fa fa-save"></i> Record Payment</button>
                    <button type="button" class="btn btn-sm btn-flat btn-danger" data-dismiss="modal"><i class="fa fa-arrow-circle-left"></i> Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>
