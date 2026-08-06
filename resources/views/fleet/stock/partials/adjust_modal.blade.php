<div class="fleet-modal" id="stock-adjust-modal" hidden>
    <div class="fleet-modal-backdrop" data-close-stock-modal></div>
    <div class="fleet-modal-dialog fleet-stock-add-modal">
        <div class="fleet-modal-header">
            <h3>Add Stock</h3>
            <button type="button" class="fleet-modal-close" data-close-stock-modal aria-label="Close">&times;</button>
        </div>
        <form method="POST" id="stock-adjust-form">
            @csrf
            <div class="fleet-modal-body fleet-stock-add-modal-body">
                <div class="fleet-stock-add-field">
                    <label for="stock-adjust-quantity">No of Stock</label>
                    <input type="number" min="1" name="quantity" id="stock-adjust-quantity" class="fleet-stock-add-input" placeholder="Enter No of Stock" required>
                </div>

                <div class="fleet-stock-add-field">
                    <label for="stock-adjust-purchased-from">Purchased From</label>
                    <input type="text" name="purchased_from" id="stock-adjust-purchased-from" class="fleet-stock-add-input" placeholder="Purchased From">
                </div>

                <div class="fleet-stock-add-field">
                    <label for="stock-adjust-cost">Cost</label>
                    <input type="number" step="0.01" min="0" name="cost" id="stock-adjust-cost" class="fleet-stock-add-input" placeholder="Enter Stock Cost" required>
                </div>

                <div class="fleet-stock-add-field">
                    <label for="stock-adjust-payment-status">Payment Status</label>
                    <select name="payment_status" id="stock-adjust-payment-status" class="fleet-stock-add-input" required>
                        <option value="" disabled selected>Select Payment Status</option>
                        <option value="Paid">Paid</option>
                        <option value="Pending">Pending</option>
                        <option value="Partial">Partial</option>
                    </select>
                </div>

                <div class="fleet-stock-add-field">
                    <label for="stock-adjust-description">Description</label>
                    <input type="text" name="description" id="stock-adjust-description" class="fleet-stock-add-input" placeholder="Enter Description">
                </div>
            </div>
            <div class="fleet-modal-footer fleet-stock-add-modal-footer">
                <button type="button" class="fleet-btn fleet-btn-default" data-close-stock-modal>Close</button>
                <button type="submit" class="fleet-btn fleet-btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
