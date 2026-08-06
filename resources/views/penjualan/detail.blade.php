<div class="modal fade" id="modal-detail" tabindex="-1" role="dialog" aria-labelledby="modal-detail">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Sales Details</h4>
            </div>
            <div class="modal-body">
                {{-- Payment Method Information --}}
                <div id="payment-info-section" style="margin-bottom: 20px; padding: 15px; background-color: #f9f9f9; border-radius: 5px; border: 1px solid #ddd;">
                    <h5 style="margin-top: 0;"><strong>Payment Information</strong></h5>
                    <div id="payment-info-content">
                        <p><strong>Mode of Payment:</strong> <span id="payment-method-display">Loading...</span></p>
                        <div id="split-payment-details" style="display: none; margin-top: 10px;">
                            <p><strong>Split Payment Breakdown:</strong></p>
                            <ul id="split-payment-list" style="margin-bottom: 0;">
                            </ul>
                        </div>
                    </div>
                </div>

                <table class="table table-striped table-bordered table-detail table-hover">
                    <thead>
                        <th width="5%">#</th>
                        <th>Code</th>
                        <th>Shop Code</th>
                        <th>Product Name</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>