<div class="modal fade" id="modal-form" tabindex="-1" role="dialog" aria-labelledby="modal-form">
    <div class="modal-dialog modal-lg" role="document">
        <form action="" method="post" class="form-horizontal">
            @csrf
            @method('post')

            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"></h4>
                </div>
                <div class="modal-body">
                    <div class="form-group row">
                        <label for="name" class="col-lg-3 col-lg-offset-1 control-label">Name <span class="text-danger">*</span></label>
                        <div class="col-lg-6">
                            <input type="text" name="name" id="name" class="form-control" required autofocus>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="id_number" class="col-lg-3 col-lg-offset-1 control-label">ID Number <span class="text-danger">*</span></label>
                        <div class="col-lg-6">
                            <input type="text" name="id_number" id="id_number" class="form-control" required>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="employer_number" class="col-lg-3 col-lg-offset-1 control-label">Employer Number</label>
                        <div class="col-lg-6">
                            <input type="text" name="employer_number" id="employer_number" class="form-control">
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="kra_pin" class="col-lg-3 col-lg-offset-1 control-label">KRA PIN</label>
                        <div class="col-lg-6">
                            <input type="text" name="kra_pin" id="kra_pin" class="form-control">
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="nssf_number" class="col-lg-3 col-lg-offset-1 control-label">NSSF Number</label>
                        <div class="col-lg-6">
                            <input type="text" name="nssf_number" id="nssf_number" class="form-control">
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="email" class="col-lg-3 col-lg-offset-1 control-label">Email</label>
                        <div class="col-lg-6">
                            <input type="email" name="email" id="email" class="form-control">
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="phone" class="col-lg-3 col-lg-offset-1 control-label">Phone</label>
                        <div class="col-lg-6">
                            <input type="text" name="phone" id="phone" class="form-control">
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="address" class="col-lg-3 col-lg-offset-1 control-label">Address</label>
                        <div class="col-lg-6">
                            <textarea name="address" id="address" class="form-control" rows="2"></textarea>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="date_of_birth" class="col-lg-3 col-lg-offset-1 control-label">Date of Birth</label>
                        <div class="col-lg-6">
                            <input type="date" name="date_of_birth" id="date_of_birth" class="form-control">
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="date_of_employment" class="col-lg-3 col-lg-offset-1 control-label">Date of Employment</label>
                        <div class="col-lg-6">
                            <input type="date" name="date_of_employment" id="date_of_employment" class="form-control">
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="status" class="col-lg-3 col-lg-offset-1 control-label">Status <span class="text-danger">*</span></label>
                        <div class="col-lg-6">
                            <select name="status" id="status" class="form-control" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="terminated">Terminated</option>
                            </select>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="gross_salary" class="col-lg-3 col-lg-offset-1 control-label">Gross Salary (KES) <span class="text-danger">*</span></label>
                        <div class="col-lg-6">
                            <input type="number" name="gross_salary" id="gross_salary" class="form-control" step="0.01" min="0" required>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="advance_salary" class="col-lg-3 col-lg-offset-1 control-label">Advance Salary (KES)</label>
                        <div class="col-lg-6">
                            <input type="number" name="advance_salary" id="advance_salary" class="form-control" step="0.01" min="0" value="0">
                            <span class="help-block">Amount to be deducted from salary</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-sm btn-flat btn-success"><i class="fa fa-save"></i> Save</button>
                    <button type="button" class="btn btn-sm btn-flat btn-danger" data-dismiss="modal"><i class="fa fa-arrow-circle-left"></i> Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>


