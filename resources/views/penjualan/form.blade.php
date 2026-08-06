<div class="modal fade" id="modal-form" tabindex="-1" role="dialog" aria-labelledby="modal-form">
    <div class="modal-dialog modal-lg" role="document">
        <form action="{{ route('penjualan.index') }}" method="get" data-toggle="validator" class="form-horizontal" id="filter-form">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Filter Sales List</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group row">
                        <label for="start_date" class="col-lg-2 col-lg-offset-1 control-label">Start Date</label>
                        <div class="col-lg-6">
                            <input type="text" name="start_date" id="start_date" class="form-control datepicker" autofocus
                                value="{{ request('start_date') }}"
                                style="border-radius: 0 !important;" placeholder="Optional">
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="end_date" class="col-lg-2 col-lg-offset-1 control-label">End Date</label>
                        <div class="col-lg-6">
                            <input type="text" name="end_date" id="end_date" class="form-control datepicker"
                                value="{{ request('end_date') }}"
                                style="border-radius: 0 !important;" placeholder="Optional — leave blank for no limit">
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="filter_payment_mode" class="col-lg-2 col-lg-offset-1 control-label">Payment Mode</label>
                        <div class="col-lg-6">
                            <select name="payment_mode" id="filter_payment_mode" class="form-control" style="border-radius: 0 !important;">
                                <option value="">All</option>
                                <option value="Cash" {{ request('payment_mode') == 'Cash' ? 'selected' : '' }}>Cash</option>
                                <option value="Consignment" {{ request('payment_mode') == 'Consignment' ? 'selected' : '' }}>Consignment</option>
                                <option value="Mpesa" {{ request('payment_mode') == 'Mpesa' ? 'selected' : '' }}>Mpesa</option>
                                <option value="Card" {{ request('payment_mode') == 'Card' ? 'selected' : '' }}>Card</option>
                                <option value="Split" {{ request('payment_mode') == 'Split' ? 'selected' : '' }}>Split</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="filter_receipt_number" class="col-lg-2 col-lg-offset-1 control-label">Receipt No</label>
                        <div class="col-lg-6">
                            <input type="text" name="receipt_number" id="filter_receipt_number" class="form-control"
                                value="{{ request('receipt_number') }}"
                                style="border-radius: 0 !important;" placeholder="Partial match">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="filter_item_code" class="col-lg-2 col-lg-offset-1 control-label">Item Code</label>
                        <div class="col-lg-6">
                            <input type="text" name="item_code" id="filter_item_code" class="form-control"
                                value="{{ request('item_code') }}"
                                style="border-radius: 0 !important;" placeholder="Product item code or code">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="filter_receipt_status" class="col-lg-2 col-lg-offset-1 control-label">Receipt Status</label>
                        <div class="col-lg-6">
                            <select name="receipt_status" id="filter_receipt_status" class="form-control" style="border-radius: 0 !important;">
                                <option value="">All</option>
                                <option value="pending" {{ request('receipt_status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="confirmed" {{ request('receipt_status') == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                                <option value="defect" {{ request('receipt_status') == 'defect' ? 'selected' : '' }}>Defect</option>
                                <option value="review" {{ request('receipt_status') == 'review' ? 'selected' : '' }}>Review</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-sm btn-flat btn-success"><i class="fa fa-filter"></i> Apply Filters</button>
                    <button type="button" class="btn btn-sm btn-flat btn-danger" data-dismiss="modal"><i class="fa fa-arrow-circle-left"></i> Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>