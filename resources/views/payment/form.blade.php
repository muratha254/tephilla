<div class="modal fade" id="modal-form" tabindex="-1" role="dialog" aria-labelledby="modal-form">
    <div class="modal-dialog modal-lg" role="document">
        <div class="form-horizontal">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Search Payments</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group row">
                        <label for="reference_number" class="col-lg-2 col-lg-offset-1 control-label">Reference Number</label>
                        <div class="col-lg-6">
                            <input type="text" name="reference_number" id="reference_number" class="form-control"
                                value="{{ request('reference_number') }}"
                                placeholder="Enter reference number"
                                style="border-radius: 0 !important;">
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="supplier_id" class="col-lg-2 col-lg-offset-1 control-label">Supplier</label>
                        <div class="col-lg-6">
                            <select name="supplier_id" id="supplier_id" class="form-control" style="border-radius: 0 !important;">
                                <option value="">All Suppliers</option>
                                @foreach($suppliers ?? [] as $supplier)
                                    <option value="{{ $supplier->id_supplier }}" {{ request('supplier_id') == $supplier->id_supplier ? 'selected' : '' }}>
                                        {{ $supplier->nama }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="tanggal_awal" class="col-lg-2 col-lg-offset-1 control-label">Start Date</label>
                        <div class="col-lg-6">
                            <input type="text" name="start_date" id="start_date" class="form-control datepicker"
                                value="{{ request('start_date') }}"
                                style="border-radius: 0 !important;">
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="tanggal_akhir" class="col-lg-2 col-lg-offset-1 control-label">End Date</label>
                        <div class="col-lg-6">
                            <input type="text" name="end_date" id="end_date" class="form-control datepicker"
                                value="{{ request('end_date') ?? date('Y-m-d') }}"
                                style="border-radius: 0 !important;">
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-flat btn-primary" id="applyFilters"><i class="fa fa-search"></i> Apply Filters</button>
                    <button type="button" class="btn btn-sm btn-flat btn-default" id="resetFilters"><i class="fa fa-refresh"></i> Reset</button>
                    <button type="button" class="btn btn-sm btn-flat btn-danger" data-dismiss="modal"><i class="fa fa-arrow-circle-left"></i> Close</button>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- visit "codeastro" for more projects! -->