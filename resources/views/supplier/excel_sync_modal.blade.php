<div class="modal fade" id="modal-excel-sync" tabindex="-1" role="dialog" aria-labelledby="modal-excel-sync-label">
    <div class="modal-dialog modal-lg" role="document" style="width:95%;max-width:1100px;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="modal-excel-sync-label"><i class="fa fa-file-excel-o"></i> Upload Supplier Excel — Compare &amp; Reconcile</h4>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <p><strong>Upload the correct supplier list</strong> (.xlsx or .xls). Expected columns:</p>
                    <ul class="mb-0">
                        <li><strong>Supplier Code</strong> (optional) — maps to system <code>id_supplier</code></li>
                        <li><strong>Supplier Name</strong> (required)</li>
                        <li><strong>Payment Method</strong> — Cash or Consignment (column titles like MOP, Mode of Payment, Payment Method)</li>
                    </ul>
                    <p class="mb-0" style="margin-top:8px;">The system compares the file against stored suppliers, shows a reconciliation report, and lets you apply updates after confirmation (including payment-method mismatches on duplicate suppliers). All changes are written to an audit log.</p>
                </div>

                <div class="form-group">
                    <label for="excel-sync-file">Excel file (.xlsx / .xls, max 10 MB)</label>
                    <input type="file" id="excel-sync-file" class="form-control" accept=".xlsx,.xls">
                </div>
                <button type="button" class="btn btn-primary btn-flat" id="btn-excel-sync-preview">
                    <i class="fa fa-search"></i> Compare with system
                </button>

                <div id="excel-sync-alert" style="display:none;margin-top:15px;"></div>
                <div id="excel-sync-meta" class="well well-sm small" style="display:none;margin-top:15px;"></div>

                <div id="excel-sync-summary" style="display:none;margin-top:15px;">
                    <h4><i class="fa fa-bar-chart"></i> Reconciliation summary</h4>
                    <div class="row text-center" id="excel-sync-summary-cards"></div>
                </div>

                <div id="excel-sync-report" style="display:none;margin-top:15px;">
                    <h4><i class="fa fa-list-alt"></i> Reconciliation report</h4>

                    <div class="panel panel-warning">
                        <div class="panel-heading"><strong>Suppliers to update</strong> <span class="badge" id="excel-sync-count-update">0</span></div>
                        <div class="panel-body" style="padding:0;">
                            <p class="text-muted small" style="padding:10px;margin:0;" id="excel-sync-update-empty">No updates required.</p>
                            <div class="table-responsive" style="max-height:240px;overflow-y:auto;">
                                <table class="table table-bordered table-condensed table-striped" id="excel-sync-update-table" style="display:none;margin-bottom:0;">
                                    <thead>
                                        <tr>
                                            <th style="width:36px;"><input type="checkbox" id="excel-sync-check-all" title="Select all" checked></th>
                                            <th>Row</th>
                                            <th>Code</th>
                                            <th>Supplier</th>
                                            <th>Current payment method</th>
                                            <th>New payment method</th>
                                            <th>Other changes</th>
                                        </tr>
                                    </thead>
                                    <tbody id="excel-sync-matched-body"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="panel panel-danger">
                        <div class="panel-heading"><strong>Payment method mismatches</strong> <span class="badge" id="excel-sync-count-mop">0</span></div>
                        <div class="panel-body" style="padding:0;">
                            <p class="text-muted small" style="padding:10px;margin:0;" id="excel-sync-mop-empty">None.</p>
                            <p class="text-muted small" id="excel-sync-mop-help" style="display:none;padding:0 10px 10px;margin:0;">
                                Duplicate system suppliers that share a name with an Excel row but have a different payment method. Click <strong>Configure merge</strong> on each group to combine Cash/Consignment duplicates, or tick <strong>Apply Excel payment method</strong> and click <strong>Apply changes</strong>.
                            </p>
                            <div id="excel-sync-mop-groups" style="max-height:240px;overflow-y:auto;padding:0 10px 10px;"></div>
                        </div>
                    </div>

                    <div class="panel panel-danger">
                        <div class="panel-heading"><strong>Missing from system</strong> (in Excel, not found) <span class="badge" id="excel-sync-count-missing">0</span></div>
                        <div class="panel-body">
                            <p class="text-muted small" id="excel-sync-missing-empty">None.</p>
                            <ul class="list-unstyled small" id="excel-sync-missing-list"></ul>
                        </div>
                    </div>

                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Extra in system</strong> (not in Excel file) <span class="badge" id="excel-sync-count-extra">0</span></div>
                        <div class="panel-body">
                            <p class="text-muted small" id="excel-sync-extra-empty">None.</p>
                            <div class="table-responsive" style="max-height:180px;overflow-y:auto;">
                                <table class="table table-bordered table-condensed" id="excel-sync-extra-table" style="display:none;margin-bottom:0;">
                                    <thead>
                                        <tr><th>Code</th><th>Supplier</th><th>Payment method</th></tr>
                                    </thead>
                                    <tbody id="excel-sync-extra-body"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="panel panel-info">
                        <div class="panel-heading"><strong>Duplicate suppliers in system</strong> <span class="badge" id="excel-sync-count-dup">0</span></div>
                        <div class="panel-body">
                            <p class="text-muted small">Same or similar supplier name (e.g. <em>A &amp; B</em> vs <em>A and B</em>) or different payment methods (Cash vs Consignment). Click <strong>Configure merge</strong> to choose how to combine them.</p>
                            <div id="excel-sync-dup-groups"></div>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn btn-success btn-flat" id="btn-excel-sync-apply" style="display:none;margin-top:10px;">
                    <i class="fa fa-check"></i> Apply changes
                </button>

                <hr>
                <p class="text-muted small">
                    To merge duplicates (choose name spelling or Cash vs Consignment), use
                    <button type="button" class="btn btn-xs btn-warning btn-flat" id="btn-excel-sync-open-merge" data-dismiss="modal" data-toggle="modal" data-target="#modal-supplier-merge">
                        <i class="fa fa-compress"></i> Merge suppliers
                    </button>
                    or click <strong>Configure merge</strong> on a duplicate group above.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default btn-flat" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
