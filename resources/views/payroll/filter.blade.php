<div class="modal fade" id="modal-filter" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Filter Payroll</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="text" class="form-control datepicker" id="start_date" name="start_date" value="{{ date('Y-m-01') }}">
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="text" class="form-control datepicker" id="end_date" name="end_date" value="{{ date('Y-m-d') }}">
                </div>
                <div class="form-group">
                    <label>Employee</label>
                    <select class="form-control" id="employee_id" name="employee_id">
                        <option value="">All Employees</option>
                        @foreach(\App\Models\Employee::where('status', 'active')->get() as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="applyFilter()">Apply Filter</button>
            </div>
        </div>
    </div>
</div>

<script>
    function applyFilter() {
        table.ajax.reload();
        $('#modal-filter').modal('hide');
    }
</script>




