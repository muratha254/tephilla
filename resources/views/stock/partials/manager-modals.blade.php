<div class="modal fade" id="sx-adjust-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-pencil-square-o"></i> Adjust Stock</h4>
            </div>
            <div class="modal-body">
                <form method="post" id="sx-adjust-form">
                    @csrf
                    <input type="hidden" name="branch_id" id="sx-adjust-branch" value="{{ $selectedBranchId }}">
                    <div class="form-group">
                        <label class="sx-req">Date *</label>
                        <input type="date" name="occurred_at" id="sx-adjust-date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label class="sx-req">Status *</label>
                        <select name="status" id="sx-adjust-status" class="form-control" required>
                            <option value="">~~Select Type~~</option>
                            @foreach($adjustStatuses as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="sx-req">Migration Control Account * <span class="sx-account-hint">Affects Equity Accounts</span></label>
                        <select name="control_account" class="form-control" required>
                            @foreach($controlAccounts as $key => $label)
                                <option value="{{ $key }}" @if($key === 'migration_control') selected @endif>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="sx-req">Adjust Qty *</label>
                        <input type="number" step="0.0001" min="0.0001" name="quantity" class="form-control" placeholder="Qty to Adjust(Type number, e.g 20,30...)" required>
                    </div>
                    <div class="form-group">
                        <label>Narrative</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Remarks"></textarea>
                    </div>
                    <div class="sx-conv-footer">
                        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="sx-price-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-pencil-square-o"></i> Update Stock Price</h4>
            </div>
            <div class="modal-body">
                <form method="post" id="sx-price-form">
                    @csrf
                    <div class="form-group">
                        <label class="sx-req">Purchase Price *</label>
                        <input type="number" step="0.01" min="0" name="purchase_price" id="sx-price-cost" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="sx-req">Sales Price(Retail) *</label>
                        <input type="number" step="0.01" min="0" name="selling_price" id="sx-price-retail" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="sx-req">Wholesale Price *</label>
                        <input type="number" step="0.01" min="0" name="wholesale_price" id="sx-price-wholesale" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="sx-req">Promotion Price *</label>
                        <input type="number" step="0.01" min="0" name="promo_price" id="sx-price-promo" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="sx-req">Migration Control Account * <span class="sx-account-hint">Affects Equity Accounts</span></label>
                        <select name="control_account" class="form-control" required>
                            @foreach($controlAccounts as $key => $label)
                                <option value="{{ $key }}" @if($key === 'migration_control') selected @endif>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sx-conv-footer">
                        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
