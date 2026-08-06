<div class="modal fade" id="modal-supplier-merge" tabindex="-1" role="dialog" aria-labelledby="modal-supplier-merge-label">
    <div class="modal-dialog modal-lg" role="document" style="width:95%;max-width:960px;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="modal-supplier-merge-label"><i class="fa fa-compress"></i> Merge suppliers</h4>
            </div>
            <div class="modal-body supplier-merge-modal-body">
                <div class="alert alert-warning">
                    <p><strong>What merge does:</strong> combines duplicate supplier records into one. All products, purchases, consignment lines, invoices, payments, and withdrawals move to the supplier you <strong>keep</strong>. Opening balances are summed.</p>
                    <p class="text-danger" style="margin-bottom:0;"><strong>This cannot be undone.</strong> Choose which name and payment method to keep when duplicates differ (e.g. <em>A &amp; B</em> vs <em>A and B</em>, or Cash vs Consignment).</p>
                </div>

                <h4><i class="fa fa-search"></i> Possible duplicates in system</h4>
                <p class="text-muted small">Same person entered twice — similar name (including <code>&amp;</code> vs <code>and</code>), same phone, or Cash + Consignment pairs. Click <strong>Configure merge</strong> to choose how to combine them.</p>
                <button type="button" class="btn btn-default btn-flat" id="btn-load-duplicate-suppliers">
                    <i class="fa fa-refresh"></i> Scan for duplicates
                </button>
                <span id="supplier-dup-scan-status" class="text-muted small" style="display:block;margin:8px 0;"></span>
                <div id="supplier-dup-groups-wrap" style="max-height:220px;overflow-y:auto;"></div>

                <hr>

                <h4><i class="fa fa-hand-o-right"></i> Merge settings</h4>
                <div class="row">
                    <div class="col-sm-6">
                        <label>Keep supplier (record that survives)</label>
                        <select id="merge-keep-select" class="form-control" style="width:100%;"></select>
                        <p class="help-block small">Usually the one with the most products; you can change this.</p>
                    </div>
                    <div class="col-sm-6">
                        <label>Merge into keeper (one or more duplicates)</label>
                        <select id="merge-into-select" class="form-control" multiple="multiple" style="width:100%;"></select>
                    </div>
                </div>

                <div id="merge-name-choices" class="well well-sm" style="display:none;margin-top:12px;margin-bottom:0;">
                    <label><strong>Choose final supplier name</strong></label>
                    <p class="text-muted small" style="margin-bottom:6px;">Pick the spelling you want after merge (e.g. <em>A &amp; B</em> or <em>A and B</em>).</p>
                    <div id="merge-name-radios"></div>
                    <div style="margin-top:8px;">
                        <label class="small">Or type a custom name:</label>
                        <input type="text" class="form-control input-sm" id="merge-modal-final-nama-custom" placeholder="Custom name (overrides selection above)">
                    </div>
                </div>

                <div id="merge-mop-choices" class="well well-sm" style="display:none;margin-top:12px;margin-bottom:0;">
                    <label><strong>Choose final payment method</strong></label>
                    <p class="text-muted small" style="margin-bottom:6px;">When the same supplier exists as Cash and Consignment, pick which mode of payment to keep.</p>
                    <div id="merge-mop-radios"></div>
                </div>

                <div id="merge-preview-box" class="well well-sm" style="display:none;margin-top:12px;"></div>
                <button type="button" class="btn btn-warning btn-flat" id="btn-supplier-merge-preview" style="margin-top:10px;">
                    <i class="fa fa-eye"></i> Preview what will move
                </button>
                <button type="button" class="btn btn-danger btn-flat" id="btn-supplier-merge-confirm" style="margin-top:10px;">
                    <i class="fa fa-compress"></i> Merge now
                </button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default btn-flat" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
