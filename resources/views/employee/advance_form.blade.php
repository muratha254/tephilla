<div class="modal fade" id="modal-advance" tabindex="-1" role="dialog" aria-labelledby="modal-advance">
    <div class="modal-dialog" role="document">
        <form action="" method="post" class="form-horizontal">
            @csrf
            @method('post')

            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Add Advance Salary</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="employee_id" id="employee_id">
                    <input type="hidden" name="current_advance" id="current_advance">
                    
                    <div class="alert alert-info">
                        <strong>Current Advance Salary:</strong> <span id="current-advance-display">KES 0.00</span>
                    </div>

                    <div class="form-group row">
                        <label for="amount" class="col-lg-3 col-lg-offset-1 control-label">Advance Amount (KES) <span class="text-danger">*</span></label>
                        <div class="col-lg-6">
                            <input type="number" name="amount" id="amount" class="form-control" step="0.01" min="0" required autofocus>
                            <span class="help-block with-errors">Amount to add to current advance salary</span>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="request_date" class="col-lg-3 col-lg-offset-1 control-label">Request Date <span class="text-danger">*</span></label>
                        <div class="col-lg-6">
                            <input type="date" name="request_date" id="request_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="notes" class="col-lg-3 col-lg-offset-1 control-label">Notes</label>
                        <div class="col-lg-6">
                            <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Optional notes about this advance salary request"></textarea>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>

                    <div class="alert alert-warning">
                        <i class="fa fa-info-circle"></i> <strong>Note:</strong> This amount will be deducted from the employee's next payroll automatically.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-flat btn-success" id="btn-save-advance"><i class="fa fa-save"></i> Add Advance Salary</button>
                    <button type="button" class="btn btn-sm btn-flat btn-danger" data-dismiss="modal"><i class="fa fa-arrow-circle-left"></i> Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>



