<div class="modal fade" id="modal-payment-details" tabindex="-1" role="dialog" aria-labelledby="modal-payment-details">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Payment Details</h4>
            </div>
            <div class="modal-body">
                <div id="payment-details-loading" style="text-align: center; padding: 20px;">
                    <i class="fa fa-spinner fa-spin fa-2x"></i> Loading payment details...
                </div>
                
                <div id="payment-details-content" style="display: none;">
                    <!-- Payment Information -->
                    <div class="box box-info">
                        <div class="box-header with-border">
                            <h3 class="box-title">Payment Information</h3>
                        </div>
                        <div class="box-body">
                            <table class="table table-bordered" data-datatable="false" style="margin-bottom: 0;">
                                <tbody>
                                    <tr>
                                        <th width="30%">Reference Number</th>
                                        <td id="payment-reference">-</td>
                                    </tr>
                                    <tr>
                                        <th>Payment Date</th>
                                        <td id="payment-date">-</td>
                                    </tr>
                                    <tr>
                                        <th>Payment Amount</th>
                                        <td><strong id="payment-amount">Ksh 0.00</strong></td>
                                    </tr>
                                    <tr>
                                        <th>Payment Type</th>
                                        <td id="payment-type">-</td>
                                    </tr>
                                    <tr>
                                        <th>Payment Method</th>
                                        <td id="payment-method">-</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Supplier Information -->
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">Supplier Information</h3>
                        </div>
                        <div class="box-body">
                            <table class="table table-bordered" data-datatable="false" style="margin-bottom: 0;">
                                <tbody>
                                    <tr>
                                        <th width="30%">Name</th>
                                        <td id="supplier-name">-</td>
                                    </tr>
                                    <tr>
                                        <th>Phone</th>
                                        <td id="supplier-phone">-</td>
                                    </tr>
                                    <tr>
                                        <th>Address</th>
                                        <td id="supplier-address">-</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Summary -->
                    <div class="box box-success">
                        <div class="box-header with-border">
                            <h3 class="box-title">Summary</h3>
                        </div>
                        <div class="box-body">
                            <table class="table table-bordered" data-datatable="false" style="margin-bottom: 0;">
                                <tbody>
                                    <tr>
                                        <th width="50%">Items Paid For</th>
                                        <td><strong id="items-paid-count">0</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Items Paid For -->
                    <div class="box box-warning">
                        <div class="box-header with-border">
                            <h3 class="box-title">Items Paid For</h3>
                            <div class="box-tools pull-right">
                                <button type="button" id="btn-export-payment-pdf" class="btn btn-danger btn-sm" style="display: none;">
                                    <i class="fa fa-file-pdf-o"></i> Print PDF
                                </button>
                                <button type="button" id="btn-export-payment-excel" class="btn btn-success btn-sm" style="display: none;">
                                    <i class="fa fa-file-excel-o"></i> Export Excel
                                </button>
                            </div>
                        </div>
                        <div class="box-body">
                            <div id="items-loading" style="text-align: center; padding: 10px;">
                                <i class="fa fa-spinner fa-spin"></i> Loading items...
                            </div>
                            <div id="items-table-wrapper" style="display: none;">
                                <table class="table table-bordered table-striped" data-datatable="false">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Product Code</th>
                                            <th>Product Name</th>
                                            <th>Quantity</th>
                                            <th>Invoice Amount</th>
                                            <th>Amount Paid</th>
                                            <th>Balance</th>
                                            <th>Invoice Number</th>
                                        </tr>
                                    </thead>
                                    <tbody id="items-tbody">
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="5" style="text-align: right;">Total Paid:</th>
                                            <th colspan="3" id="items-total">Ksh 0.00</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div id="items-empty" style="display: none; text-align: center; padding: 20px;">
                                <p>No items found.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>









