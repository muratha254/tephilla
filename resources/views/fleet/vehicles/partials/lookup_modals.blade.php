<div class="fleet-modal" id="vehicle-add-type-modal" hidden>
    <div class="fleet-modal-backdrop" data-close-vehicle-type-modal></div>
    <div class="fleet-modal-dialog fleet-vehicle-lookup-modal">
        <div class="fleet-modal-header">
            <h3>Add Vehicle Type</h3>
            <button type="button" class="fleet-modal-close" data-close-vehicle-type-modal aria-label="Close">&times;</button>
        </div>
        <form id="vehicle-add-type-form">
            <div class="fleet-modal-body">
                <div class="fleet-alert fleet-alert-error" id="vehicle-add-type-errors" hidden></div>
                <div class="fleet-form-group">
                    <label>Vehicle Type Name <span class="required">*</span></label>
                    <input type="text" name="name" class="fleet-input" placeholder="e.g. Pickup" required maxlength="50">
                </div>
            </div>
            <div class="fleet-modal-footer">
                <button type="button" class="fleet-btn fleet-btn-light" data-close-vehicle-type-modal>Cancel</button>
                <button type="submit" class="fleet-btn fleet-btn-primary" id="vehicle-add-type-submit">
                    <i class="fa fa-save"></i> Save Vehicle Type
                </button>
            </div>
        </form>
    </div>
</div>

<div class="fleet-modal" id="vehicle-add-manufacturer-modal" hidden>
    <div class="fleet-modal-backdrop" data-close-vehicle-manufacturer-modal></div>
    <div class="fleet-modal-dialog fleet-vehicle-lookup-modal">
        <div class="fleet-modal-header">
            <h3>Add Manufacturer</h3>
            <button type="button" class="fleet-modal-close" data-close-vehicle-manufacturer-modal aria-label="Close">&times;</button>
        </div>
        <form id="vehicle-add-manufacturer-form">
            <div class="fleet-modal-body">
                <div class="fleet-alert fleet-alert-error" id="vehicle-add-manufacturer-errors" hidden></div>
                <div class="fleet-form-group">
                    <label>Manufacturer Name <span class="required">*</span></label>
                    <input type="text" name="name" class="fleet-input" placeholder="e.g. Toyota" required maxlength="150">
                </div>
            </div>
            <div class="fleet-modal-footer">
                <button type="button" class="fleet-btn fleet-btn-light" data-close-vehicle-manufacturer-modal>Cancel</button>
                <button type="submit" class="fleet-btn fleet-btn-primary" id="vehicle-add-manufacturer-submit">
                    <i class="fa fa-save"></i> Save Manufacturer
                </button>
            </div>
        </form>
    </div>
</div>

<div class="fleet-modal" id="vehicle-add-model-modal" hidden>
    <div class="fleet-modal-backdrop" data-close-vehicle-model-modal></div>
    <div class="fleet-modal-dialog fleet-vehicle-lookup-modal">
        <div class="fleet-modal-header">
            <h3>Add Model</h3>
            <button type="button" class="fleet-modal-close" data-close-vehicle-model-modal aria-label="Close">&times;</button>
        </div>
        <form id="vehicle-add-model-form">
            <div class="fleet-modal-body">
                <div class="fleet-alert fleet-alert-error" id="vehicle-add-model-errors" hidden></div>
                <div class="fleet-form-group">
                    <label>Model Name <span class="required">*</span></label>
                    <input type="text" name="name" class="fleet-input" placeholder="e.g. Model X5" required maxlength="150">
                </div>
                <div class="fleet-form-group">
                    <label>Manufacturer</label>
                    <select name="fleet_vehicle_manufacturer_id" id="vehicle-model-manufacturer-select" class="fleet-input">
                        <option value="">No manufacturer linked</option>
                        @foreach ($formOptions['manufacturer_lookups'] as $lookup)
                            <option value="{{ $lookup->id }}">{{ $lookup->name }}</option>
                        @endforeach
                    </select>
                    <p class="fleet-field-help">Optional. Pre-filled from the vehicle form when a manufacturer is selected.</p>
                </div>
            </div>
            <div class="fleet-modal-footer">
                <button type="button" class="fleet-btn fleet-btn-light" data-close-vehicle-model-modal>Cancel</button>
                <button type="submit" class="fleet-btn fleet-btn-primary" id="vehicle-add-model-submit">
                    <i class="fa fa-save"></i> Save Model
                </button>
            </div>
        </form>
    </div>
</div>
