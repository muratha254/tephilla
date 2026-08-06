{{-- Price change: optional retroactive update of past sales / consignment / cash purchases --}}
<div class="modal fade" id="modal-price-retro" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" id="btn-price-retro-close-x" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-history"></i> Price change — past records</h4>
            </div>
            <div class="modal-body">
                <p id="price-retro-summary" class="text-muted"></p>
                <hr>
                <p><strong>Apply to completed activity in this date range</strong> (sale date on the receipt):</p>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="price_retro_date_from">From</label>
                            <input type="date" class="form-control" id="price_retro_date_from" name="price_retro_date_from">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="price_retro_date_to">To</label>
                            <input type="date" class="form-control" id="price_retro_date_to" name="price_retro_date_to">
                        </div>
                    </div>
                </div>
                <div class="checkbox">
                    <label><input type="checkbox" id="price_retro_chk_sales" value="1"> Past <strong>sale lines</strong> (selling price → line totals &amp; sale payable)</label>
                </div>
                <div class="checkbox">
                    <label><input type="checkbox" id="price_retro_chk_consignment" value="1"> <strong>Consignment</strong> unpaid lines (buying price × qty)</label>
                </div>
                <div class="checkbox">
                    <label><input type="checkbox" id="price_retro_chk_cash" value="1"> <strong>Cash supplier</strong> unpaid purchase lines (buying price × qty)</label>
                </div>
                <p class="text-warning small" style="margin-top: 10px;"><i class="fa fa-warning"></i> Consignment and cash updates only affect <strong>unpaid</strong> rows. Paid amounts are not changed.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" id="btn-price-retro-cancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-price-retro-future-only"><i class="fa fa-save"></i> Save price — new sales only</button>
                <button type="button" class="btn btn-success" id="btn-price-retro-apply"><i class="fa fa-check"></i> Save &amp; update past records</button>
            </div>
        </div>
    </div>
</div>

{{-- Supplier change: reassign past sales to new supplier consignment / cash ledger --}}
<div class="modal fade" id="modal-supplier-sales" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" id="btn-supplier-sales-close-x" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-truck"></i> Supplier change — past sales</h4>
            </div>
            <div class="modal-body">
                <p id="supplier-sales-summary" class="text-muted"></p>
                <p id="supplier-sales-detail"></p>
                <p class="text-warning small" style="margin-top: 10px;"><i class="fa fa-warning"></i> Only <strong>unpaid</strong> consignment and cash-generated lines are moved. Paid supplier lines are left unchanged.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" id="btn-supplier-sales-cancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-supplier-sales-skip"><i class="fa fa-save"></i> No — keep sales on previous supplier</button>
                <button type="button" class="btn btn-success" id="btn-supplier-sales-apply"><i class="fa fa-check"></i> Yes — assign all sales to new supplier</button>
            </div>
        </div>
    </div>
</div>
