<div class="fleet-modal" id="payment-history-modal" hidden>
    <div class="fleet-modal-backdrop" data-close-payment-history-modal></div>
    <div class="fleet-modal-dialog fleet-payments-modal-dialog fleet-payments-history-dialog">
        <div class="fleet-modal-header">
            <h3 id="payment-history-modal-title">Payment History</h3>
            <button type="button" class="fleet-modal-close" data-close-payment-history-modal aria-label="Close">&times;</button>
        </div>
        <div class="fleet-modal-body">
            <div id="payment-history-loading" class="fleet-payments-modal-loading">Loading payment history...</div>
            <div id="payment-history-content" hidden>
                <div id="payment-history-customer-balance" class="fleet-payments-customer-balance"></div>
                <div class="fleet-table-wrap fleet-payments-modal-table-wrap">
                    <table class="fleet-payments-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Trip / Invoice</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th>Amount</th>
                                <th>Remaining Balance</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="payment-history-table-body"></tbody>
                    </table>
                </div>
            </div>
            <div id="payment-history-empty" class="fleet-empty-row" hidden>No payments recorded for this customer yet.</div>
        </div>
        <div class="fleet-modal-footer">
            <a href="#" class="fleet-btn fleet-btn-outline" id="payment-history-export-btn" target="_blank" rel="noopener" hidden>
                <i class="fa fa-download"></i> Download Report
            </a>
            <button type="button" class="fleet-btn fleet-btn-light" data-close-payment-history-modal>Close</button>
            <button type="button" class="fleet-btn fleet-btn-primary" id="payment-history-record-btn">
                <i class="fa fa-money"></i> Record Payment
            </button>
        </div>
    </div>
</div>
