<script>
(function () {
    var form = document.getElementById('fleet-fuel-add-form');
    var litersInput = document.getElementById('fuel-quantity');
    var costPerLiterInput = document.getElementById('fuel-cost-per-liter');
    var amountInput = document.getElementById('fuel-amount');
    var vehicleSelect = document.getElementById('fuel-vehicle');
    var fuelTypeSelect = document.getElementById('fuel-type');
    var sourceRadios = document.querySelectorAll('.js-fuel-source');
    var vendorWrap = document.getElementById('fuel-vendor-wrap');
    var vendorSelect = document.getElementById('fuel-vendor');
    var receiptInput = document.querySelector('input[name="receipt"]');
    var receiptName = document.getElementById('fuel-receipt-name');

    if (!form || !litersInput || !amountInput) {
        return;
    }

    function unitPrice() {
        var fromHidden = parseFloat(costPerLiterInput ? costPerLiterInput.value : '');
        if (isFinite(fromHidden) && fromHidden >= 0) {
            return fromHidden;
        }

        var fromForm = parseFloat(form.getAttribute('data-unit-price') || '0');
        return isFinite(fromForm) ? fromForm : 0;
    }

    function updateAmountFromQuantity() {
        var liters = parseFloat(litersInput.value);
        var rate = unitPrice();

        if (!isFinite(liters) || liters <= 0 || !isFinite(rate) || rate < 0) {
            return;
        }

        var total = Math.round(liters * rate * 100) / 100;
        amountInput.value = total.toFixed(2);
    }

    function syncFuelTypeFromVehicle() {
        if (!vehicleSelect || !fuelTypeSelect) {
            return;
        }

        var option = vehicleSelect.options[vehicleSelect.selectedIndex];
        var vehicleFuelType = option ? option.getAttribute('data-fuel-type') : '';

        if (vehicleFuelType && fuelTypeSelect.value === '') {
            fuelTypeSelect.value = vehicleFuelType;
        }
    }

    function toggleVendorWrap() {
        var selected = document.querySelector('.js-fuel-source:checked');
        var isVendor = selected && selected.value === 'vendor';

        if (vendorWrap) {
            vendorWrap.classList.toggle('is-hidden', !isVendor);
        }

        if (vendorSelect) {
            vendorSelect.required = !!isVendor;
        }
    }

    litersInput.addEventListener('input', updateAmountFromQuantity);

    if (vehicleSelect) {
        vehicleSelect.addEventListener('change', syncFuelTypeFromVehicle);
        syncFuelTypeFromVehicle();
    }

    sourceRadios.forEach(function (radio) {
        radio.addEventListener('change', toggleVendorWrap);
    });

    toggleVendorWrap();
    updateAmountFromQuantity();

    if (receiptInput && receiptName) {
        receiptInput.addEventListener('change', function () {
            receiptName.textContent = receiptInput.files[0] ? receiptInput.files[0].name : 'Choose File';
        });
    }
})();
</script>
