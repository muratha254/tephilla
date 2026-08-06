<div class="modal fade" id="modal-form" tabindex="-1" role="dialog" aria-labelledby="modal-form">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Select Date Range</h4>
            </div>
            <div class="modal-body">
                <form id="form" method="post" class="form-horizontal">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <div class="form-group row">
                        <label for="start_date" class="col-lg-2 col-lg-offset-1 control-label">Start Date</label>
                        <div class="col-lg-6">
                            <input type="text" name="start_date" id="start_date" class="form-control datepicker" value="{{ $startDate ?? date('Y-m-01') }}" autofocus required>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="end_date" class="col-lg-2 col-lg-offset-1 control-label">End Date</label>
                        <div class="col-lg-6">
                            <input type="text" name="end_date" id="end_date" class="form-control datepicker" value="{{ $endDate ?? date('Y-m-d') }}" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default btn-flat" data-dismiss="modal">Close</button>
                <button type="submit" form="form" class="btn btn-primary btn-flat">Update</button>
            </div>
        </div>
    </div>
</div>












