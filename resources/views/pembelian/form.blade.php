<div class="modal fade" id="modal-form" tabindex="-1" role="dialog" aria-labelledby="modal-form">
    <div class="modal-dialog modal-lg" role="document">
        <form action="{{ route('pembelian.index') }}" method="get" data-toggle="validator" class="form-horizontal">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Searching By Date</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group row">
                        <label for="tanggal_awal" class="col-lg-2 col-lg-offset-1 control-label">Start Date</label>
                        <div class="col-lg-6">
                            <input type="text" name="start_date" id="start_date" class="form-control datepicker" required autofocus
                                value="{{ request('start_date') }}"
                                style="border-radius: 0 !important;">
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="tanggal_akhir" class="col-lg-2 col-lg-offset-1 control-label">End Date</label>
                        <div class="col-lg-6">
                            <input type="text" name="end_date" id="end_date" class="form-control datepicker" required
                                value="{{ request('end_date') ?? date('Y-m-d') }}"
                                style="border-radius: 0 !important;">
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-sm btn-flat btn-success"><i class="fa fa-save"></i> Search</button>
                    <button type="button" class="btn btn-sm btn-flat btn-danger" data-dismiss="modal"><i class="fa fa-arrow-circle-left"></i> Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>
<!-- visit "codeastro" for more projects! -->