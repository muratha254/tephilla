<div class="modal fade" id="modal-stock-movement" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-id-card"></i> Product movement — <span id="stock-movement-product-name">—</span></h4>
            </div>
            <div class="modal-body">
                <p class="text-muted" id="stock-movement-summary" style="margin-bottom: 12px;"></p>
                <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                    <table class="table table-bordered table-striped table-condensed" id="stock-movement-table">
                        <thead>
                            <tr>
                                <th style="width: 100px;">Date</th>
                                <th style="width: 70px;">Time</th>
                                <th style="width: 120px;">Type</th>
                                <th>Details</th>
                                <th class="text-right" style="width: 72px;">Change</th>
                                <th class="text-right" style="width: 88px;">Balance</th>
                            </tr>
                        </thead>
                        <tbody id="stock-movement-tbody">
                            <tr><td colspan="6" class="text-center text-muted">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
                <p id="stock-movement-current-stock" class="text-right" style="margin-top: 12px; margin-bottom: 0; font-size: 15px; font-weight: 600; color: #2d7a3e;"></p>
            </div>
            <div class="modal-footer">
                <a href="#" id="btn-stock-movement-export-pdf" class="btn btn-danger" target="_blank" rel="noopener noreferrer" title="Export timeline to PDF">
                    <i class="fa fa-file-pdf-o"></i> Export PDF
                </a>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
