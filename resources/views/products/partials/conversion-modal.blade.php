<div class="modal fade" id="sx-conversion-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-list-alt"></i> Item Conversion</h4>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs sx-conv-tabs">
                    <li class="active"><a href="#sx-conv-create" data-toggle="tab"><i class="fa fa-plus"></i> Create Conversion</a></li>
                    <li><a href="#sx-conv-list" data-toggle="tab" id="sx-conv-list-tab"><i class="fa fa-list"></i> Conversion List</a></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane active" id="sx-conv-create">
                        <form method="post" id="sx-conversion-form">
                            @csrf
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Branch</label>
                                        <select name="branch_id" id="sx-conv-branch" class="form-control" required>
                                            @foreach($branches as $row)
                                                <option value="{{ $row->id }}" @if(optional($branch)->id == $row->id) selected @endif>{{ $row->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="sx-req">Conversion Rate * <span class="sx-rate-hint">How many of parent QTY make 1 child</span></label>
                                        <input type="number" step="0.0001" min="0.0001" name="conversion_rate" id="sx-conv-rate" class="form-control" placeholder="Conversion Rate" required>
                                    </div>
                                    <div class="form-group">
                                        <label><span class="sx-parent-label">Parent</span> Sales Price *</label>
                                        <input type="number" step="0.01" min="0" name="selling_price" id="sx-conv-price" class="form-control" value="0.00" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="sx-req">Item Name *</label>
                                        <input type="text" name="name" id="sx-conv-name" class="form-control" placeholder="Item Name" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="sx-req">Unit of Measure *</label>
                                        <select name="unit_id" id="sx-conv-unit" class="form-control" required>
                                            <option value="">Select U.O.M</option>
                                            @foreach($units as $unit)
                                                <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->short_name }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Description</label>
                                        <textarea name="description" id="sx-conv-desc" class="form-control" rows="3" placeholder="Type here..."></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="sx-conv-footer">
                                <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-success">Submit</button>
                            </div>
                        </form>
                    </div>
                    <div class="tab-pane" id="sx-conv-list">
                        <div class="table-responsive" style="margin-top:12px;">
                            <table class="table table-bordered sx-cyan-table" id="sx-conv-list-table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Item Name</th>
                                        <th>Unit</th>
                                        <th>Rate</th>
                                        <th>Sales Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="sx-conv-empty"><td colspan="5" class="sx-none-found">No record found!!!</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
