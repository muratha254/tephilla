<script>
(function () {
    var billingInput = document.getElementById('billing-type-input');
    var billingButtons = document.querySelectorAll('.fleet-billing-option');
    var billingUnitConfig = @json($formOptions['billing_unit_config']);
    var fixedFields = document.getElementById('billing-fixed-fields');
    var variableFields = document.getElementById('billing-variable-fields');
    var baseAmountFixed = document.getElementById('base_amount_fixed');
    var baseAmountVariable = document.getElementById('base_amount_variable');
    var billingQuantity = document.getElementById('billing_quantity');
    var billingRate = document.getElementById('billing_rate');
    var billingQuantityLabel = document.getElementById('billing-quantity-label');
    var billingRateLabel = document.getElementById('billing-rate-label');
    var currencySymbol = @json(kes_symbol());

    function currentBillingType() {
        return billingInput ? billingInput.value : 'Fixed';
    }

    function isFixedBilling() {
        return currentBillingType() === 'Fixed';
    }

    function updateBillingLabels() {
        var config = billingUnitConfig[currentBillingType()];
        if (billingQuantityLabel) {
            billingQuantityLabel.textContent = config ? config.quantity : 'Quantity';
        }
        if (billingRateLabel) {
            billingRateLabel.textContent = config ? config.rate : 'Rate';
        }
    }

    function calculateVariableTotal() {
        var qty = parseFloat(billingQuantity && billingQuantity.value ? billingQuantity.value : '0') || 0;
        var rate = parseFloat(billingRate && billingRate.value ? billingRate.value : '0') || 0;
        var total = Math.round((qty * rate) * 100) / 100;

        if (baseAmountVariable) {
            baseAmountVariable.value = total.toFixed(2);
        }

        return total;
    }

    function syncBillingMode() {
        var fixed = isFixedBilling();

        if (fixedFields) {
            fixedFields.hidden = !fixed;
        }
        if (variableFields) {
            variableFields.hidden = fixed;
        }

        if (baseAmountFixed) {
            baseAmountFixed.disabled = !fixed;
            if (!fixed) {
                baseAmountFixed.removeAttribute('name');
            } else {
                baseAmountFixed.setAttribute('name', 'base_amount');
            }
        }

        if (baseAmountVariable) {
            if (fixed) {
                baseAmountVariable.removeAttribute('name');
            } else {
                baseAmountVariable.setAttribute('name', 'base_amount');
                calculateVariableTotal();
            }
        }

        if (billingQuantity) {
            billingQuantity.required = !fixed;
        }
        if (billingRate) {
            billingRate.required = !fixed;
        }

        updateBillingLabels();
    }

    billingButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            billingButtons.forEach(function (item) { item.classList.remove('active'); });
            this.classList.add('active');
            if (billingInput) {
                billingInput.value = this.getAttribute('data-value');
            }
            syncBillingMode();
        });
    });

    if (billingQuantity) {
        billingQuantity.addEventListener('input', calculateVariableTotal);
    }
    if (billingRate) {
        billingRate.addEventListener('input', calculateVariableTotal);
    }

    syncBillingMode();

    var stopsList = document.getElementById('additional-stops-list');
    var addStopBtn = document.getElementById('add-stop-btn');

    function bindRemoveButtons() {
        stopsList.querySelectorAll('.fleet-trip-stop-remove').forEach(function (btn) {
            btn.onclick = function () {
                this.closest('.fleet-trip-stop-row').remove();
            };
        });
    }

    if (addStopBtn && stopsList) {
        addStopBtn.addEventListener('click', function () {
            var row = document.createElement('div');
            row.className = 'fleet-trip-stop-row';
            row.innerHTML = '<input type="text" name="additional_stops[]" class="fleet-trip-input" placeholder="Add Stop Location">' +
                '<button type="button" class="fleet-trip-stop-remove" aria-label="Remove stop">&times;</button>';
            stopsList.appendChild(row);
            bindRemoveButtons();
        });
        bindRemoveButtons();
    }

    var applyCouponBtn = document.getElementById('apply-coupon-btn');
    if (applyCouponBtn) {
        applyCouponBtn.addEventListener('click', function () {
            var code = document.getElementById('coupon-code');
            if (code && code.value.trim() === '') {
                alert('Please enter a coupon code.');
                return;
            }
            alert('Coupon applied (demo).');
        });
    }

    var containerToggle = document.getElementById('uses-container-toggle');
    var containerFields = document.getElementById('container-fields');
    var containerNumber = document.getElementById('container_number');
    var containerDropPoint = document.getElementById('container_empty_drop_point');

    function syncContainerFields() {
        if (!containerToggle || !containerFields) return;
        var visible = containerToggle.checked;
        containerFields.classList.toggle('is-visible', visible);
        if (!visible) {
            if (containerNumber) containerNumber.value = '';
            if (containerDropPoint) containerDropPoint.value = '';
        }
    }

    if (containerToggle) {
        containerToggle.addEventListener('change', syncContainerFields);
        syncContainerFields();
    }

    var csrfToken = document.querySelector('meta[name="csrf-token"]');

    var tripTypeModal = document.getElementById('trip-add-type-modal');
    var openTripTypeModalBtn = document.getElementById('open-add-trip-type-modal');
    var tripTypeForm = document.getElementById('trip-add-type-form');
    var tripTypeSelect = document.getElementById('trip-type-select');
    var tripTypeErrors = document.getElementById('trip-add-type-errors');
    var tripTypeSubmit = document.getElementById('trip-add-type-submit');

    function openTripTypeModal() {
        if (!tripTypeModal) return;
        tripTypeModal.hidden = false;
        document.body.classList.add('fleet-modal-open');
        if (tripTypeErrors) {
            tripTypeErrors.hidden = true;
            tripTypeErrors.innerHTML = '';
        }
        var nameInput = document.getElementById('quick-trip-type-name');
        if (nameInput) nameInput.focus();
    }

    function closeTripTypeModal() {
        if (!tripTypeModal) return;
        tripTypeModal.hidden = true;
        document.body.classList.remove('fleet-modal-open');
    }

    if (openTripTypeModalBtn) {
        openTripTypeModalBtn.addEventListener('click', openTripTypeModal);
    }

    if (tripTypeModal) {
        tripTypeModal.querySelectorAll('[data-close-trip-type-modal]').forEach(function (el) {
            el.addEventListener('click', closeTripTypeModal);
        });
    }

    if (tripTypeForm && tripTypeSelect) {
        tripTypeForm.addEventListener('submit', function (e) {
            e.preventDefault();

            if (tripTypeSubmit) {
                tripTypeSubmit.disabled = true;
            }

            var formData = new FormData(tripTypeForm);

            fetch(@json(route('trips.types.store-quick')), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken ? csrfToken.getAttribute('content') : '',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    var messages = [];
                    if (result.data && result.data.errors) {
                        Object.keys(result.data.errors).forEach(function (key) {
                            result.data.errors[key].forEach(function (message) {
                                messages.push(message);
                            });
                        });
                    } else if (result.data && result.data.message) {
                        messages.push(result.data.message);
                    } else {
                        messages.push('Could not add trip type.');
                    }

                    if (tripTypeErrors) {
                        tripTypeErrors.innerHTML = '<ul><li>' + messages.join('</li><li>') + '</li></ul>';
                        tripTypeErrors.hidden = false;
                    }
                    return;
                }

                var tripType = result.data.trip_type;
                var option = document.createElement('option');
                option.value = tripType.name;
                option.textContent = tripType.name;
                option.selected = true;
                tripTypeSelect.appendChild(option);
                tripTypeForm.reset();
                closeTripTypeModal();
            })
            .catch(function () {
                if (tripTypeErrors) {
                    tripTypeErrors.innerHTML = '<ul><li>Could not add trip type. Please try again.</li></ul>';
                    tripTypeErrors.hidden = false;
                }
            })
            .finally(function () {
                if (tripTypeSubmit) {
                    tripTypeSubmit.disabled = false;
                }
            });
        });
    }

    var customerModal = document.getElementById('trip-create-customer-modal');
    var openCustomerModalBtn = document.getElementById('open-create-customer-modal');
    var customerForm = document.getElementById('trip-create-customer-form');
    var customerSelect = document.getElementById('trip-customer-select');
    var customerErrors = document.getElementById('trip-create-customer-errors');
    var customerSubmit = document.getElementById('trip-create-customer-submit');

    function openCustomerModal() {
        if (!customerModal) return;
        customerModal.hidden = false;
        document.body.classList.add('fleet-modal-open');
        if (customerErrors) {
            customerErrors.hidden = true;
            customerErrors.innerHTML = '';
        }
    }

    function closeCustomerModal() {
        if (!customerModal) return;
        customerModal.hidden = true;
        document.body.classList.remove('fleet-modal-open');
    }

    if (openCustomerModalBtn) {
        openCustomerModalBtn.addEventListener('click', openCustomerModal);
    }

    if (customerModal) {
        customerModal.querySelectorAll('[data-close-trip-customer-modal]').forEach(function (el) {
            el.addEventListener('click', closeCustomerModal);
        });
    }

    if (customerForm && customerSelect) {
        customerForm.addEventListener('submit', function (e) {
            e.preventDefault();

            if (customerSubmit) {
                customerSubmit.disabled = true;
            }

            var formData = new FormData(customerForm);

            fetch(@json(route('customers.store-quick')), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken ? csrfToken.getAttribute('content') : '',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    var messages = [];
                    if (result.data && result.data.errors) {
                        Object.keys(result.data.errors).forEach(function (key) {
                            result.data.errors[key].forEach(function (message) {
                                messages.push(message);
                            });
                        });
                    } else if (result.data && result.data.message) {
                        messages.push(result.data.message);
                    } else {
                        messages.push('Could not create customer.');
                    }

                    if (customerErrors) {
                        customerErrors.innerHTML = '<ul><li>' + messages.join('</li><li>') + '</li></ul>';
                        customerErrors.hidden = false;
                    }
                    return;
                }

                var customer = result.data.customer;
                var option = document.createElement('option');
                option.value = customer.id;
                option.textContent = customer.name;
                option.selected = true;
                customerSelect.appendChild(option);
                customerForm.reset();
                closeCustomerModal();
            })
            .catch(function () {
                if (customerErrors) {
                    customerErrors.innerHTML = '<ul><li>Could not create customer. Please try again.</li></ul>';
                    customerErrors.hidden = false;
                }
            })
            .finally(function () {
                if (customerSubmit) {
                    customerSubmit.disabled = false;
                }
            });
        });
    }
})();
</script>
<script src="{{ asset('AdminLTE-2/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.js') }}"></script>
<script>
$(function () {
    var $startDate = $('#trip-start-date');
    var $endDate = $('#trip-end-date');

    $startDate.datepicker({
        format: 'dd/mm/yyyy',
        autoclose: true,
        todayHighlight: true,
        orientation: 'bottom auto',
        endDate: $endDate.val() || false
    });

    $endDate.datepicker({
        format: 'dd/mm/yyyy',
        autoclose: true,
        todayHighlight: true,
        orientation: 'bottom auto',
        startDate: $startDate.val() || false
    });

    $startDate.on('changeDate', function (e) {
        if (!e.date) return;
        $endDate.datepicker('setStartDate', e.date);
        var endPick = $endDate.datepicker('getDate');
        if (endPick && endPick < e.date) {
            $endDate.datepicker('setDate', e.date);
        }
    });

    $endDate.on('changeDate', function (e) {
        if (!e.date) return;
        $startDate.datepicker('setEndDate', e.date);
    });

    $('[data-trip-date-trigger]').on('click', function () {
        $('#' + this.getAttribute('data-trip-date-trigger')).datepicker('show');
    });

    $('.fleet-date-input').on('click', function () {
        $(this).datepicker('show');
    });
});
</script>
