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
                        <label for="name" class="col-lg-3 col-lg-offset-1 control-label">Name</label>
                        <div class="col-lg-6">
                            <input type="text" name="name" id="name" class="form-control" required autofocus>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="email" class="col-lg-3 col-lg-offset-1 control-label">Email</label>
                        <div class="col-lg-6">
                            <input type="email" name="email" id="email" class="form-control" required>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="role" class="col-lg-3 col-lg-offset-1 control-label">Role</label>
                        <div class="col-lg-6">
                            <select name="role" id="role" class="form-control" required>
                                <option value="admin">Admin</option>
                                <option value="manager">Manager</option>
                                <option value="cashier" selected>Cashier</option>
                            </select>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 col-lg-offset-1 control-label">Permissions</label>
                        <div class="col-lg-6">
                            <div class="checkbox" style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #ddd;">
                                <label style="font-weight: bold;">
                                    <input type="checkbox" id="check-all-permissions"> <strong>Check All Permissions</strong>
                                </label>
                            </div>
                            <strong>Global</strong>
                            <div class="checkbox">
                                <label><input type="checkbox" name="can_create" value="1"> Create</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="can_read" value="1" checked> Read</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="can_update" value="1"> Update</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="can_delete" value="1"> Delete</label>
                            </div>
                            <hr>
                            <strong>Inventory</strong>
                            <div class="checkbox">
                                <label><input type="checkbox" name="inv_create" value="1"> Create</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="inv_read" value="1"> Read</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="inv_update" value="1"> Update</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="inv_delete" value="1"> Delete</label>
                            </div>
                            <hr>
                            <strong>Sales</strong>
                            <div class="checkbox">
                                <label><input type="checkbox" name="sal_create" value="1"> Create</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="sal_read" value="1"> Read</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="sal_update" value="1"> Update</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="sal_delete" value="1"> Delete</label>
                            </div>
                            <hr>
                            <strong>Expense</strong>
                            <div class="checkbox">
                                <label><input type="checkbox" name="exp_create" value="1"> Create</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="exp_read" value="1"> Read</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="exp_update" value="1"> Update</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="exp_delete" value="1"> Delete</label>
                            </div>
                            <hr>
                            <strong>Reports</strong>
                            <div class="checkbox">
                                <label><input type="checkbox" name="rep_create" value="1"> Create</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="rep_read" value="1"> Read</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="rep_update" value="1"> Update</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="rep_delete" value="1"> Delete</label>
                            </div>
                            <hr>
                            <strong>Consignment</strong>
                            <div class="checkbox">
                                <label><input type="checkbox" name="con_create" value="1"> Create</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="con_read" value="1"> Read</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="con_update" value="1"> Update</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="con_delete" value="1"> Delete</label>
                            </div>
                            <hr>
                            <strong>Payroll</strong>
                            <div class="checkbox">
                                <label><input type="checkbox" name="pay_create" value="1"> Create</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="pay_read" value="1"> Read</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="pay_update" value="1"> Update</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="pay_delete" value="1"> Delete</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="password" class="col-lg-3 col-lg-offset-1 control-label">Password</label>
                        <div class="col-lg-6">
                            <input type="password" name="password" id="password" class="form-control" 
                            required
                            minlength="4">
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="password_confirmation" class="col-lg-3 col-lg-offset-1 control-label">Confirm Password</label>
                        <div class="col-lg-6">
                            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" 
                                required
                                data-match="#password">
                            <span class="help-block with-errors"></span>
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