@push('scripts')
<script>
(function () {
    var customersBaseUrl = @json(url('/payments/customers'));
    var oldTripId = @json(old('fleet_trip_id'));
    var oldAmount = @json(old('amount'));
    var autoOpenCustomerId = @json($errors->any() ? (int) ($retryCustomerId ?: $customerFilter) : 0);
    var customerNames = @json($customers->pluck('name', 'id'));

    var recordModal = document.getElementById('record-payment-modal');

    function formatMoney(value) {
        return 'KSh ' + Number(value || 0).toLocaleString('en-KE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function openModal(modal) {
        if (!modal) return;
        modal.hidden = false;
        document.body.classList.add('fleet-modal-open');
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.hidden = true;
        if (!document.querySelector('.fleet-modal:not([hidden])')) {
            document.body.classList.remove('fleet-modal-open');
        }
    }

    function bindModalClose(modal, attr) {
        if (!modal) return;
        modal.querySelectorAll('[' + attr + ']').forEach(function (el) {
            el.addEventListener('click', function () {
                closeModal(modal);
            });
        });
    }

    function renderCustomerBalance(container, customer, prefix) {
        if (!container || !customer) {
            if (container) container.hidden = true;
            return;
        }

        if (prefix) {
            var invoiced = document.getElementById(prefix + '-customer-invoiced');
            var paid = document.getElementById(prefix + '-customer-paid');
            var outstanding = document.getElementById(prefix + '-customer-outstanding');
            if (invoiced) invoiced.textContent = customer.total_invoiced_formatted || formatMoney(customer.total_invoiced);
            if (paid) paid.textContent = customer.total_paid_formatted || formatMoney(customer.total_paid);
            if (outstanding) outstanding.textContent = customer.outstanding_formatted || formatMoney(customer.outstanding_payment);
            container.hidden = false;
            return;
        }

        container.innerHTML =
            '<div><span>Total Invoiced</span><strong>' + (customer.total_invoiced_formatted || formatMoney(customer.total_invoiced)) + '</strong></div>' +
            '<div><span>Amount Paid</span><strong class="is-paid">' + (customer.total_paid_formatted || formatMoney(customer.total_paid)) + '</strong></div>' +
            '<div><span>Outstanding Balance</span><strong class="is-outstanding">' + (customer.outstanding_formatted || formatMoney(customer.outstanding_payment)) + '</strong></div>';
        container.hidden = false;
    }

    function createRecordFormController(prefix) {
        var customerSelect = document.getElementById(prefix + '-customer-select');
        var customerIdInput = document.getElementById(prefix + '-customer-id');
        var customerGroup = document.getElementById(prefix + '-customer-group');
        var customerSummary = document.getElementById(prefix + '-customer-summary');
        var customerName = document.getElementById(prefix + '-customer-name');
        var tripSelect = document.getElementById(prefix + '-trip-select');
        var tripHelp = document.getElementById(prefix + '-trip-help');
        var tripBalance = document.getElementById(prefix + '-trip-balance');
        var amountInput = document.getElementById(prefix + '-amount');
        var amountHelp = document.getElementById(prefix + '-amount-help');
        var customerBalance = document.getElementById(prefix + '-customer-balance');
        var tripTotal = document.getElementById(prefix + '-trip-total');
        var tripPaid = document.getElementById(prefix + '-trip-paid');
        var tripRemaining = document.getElementById(prefix + '-trip-remaining');
        var tripsCache = [];

        function resetTripSelect(message) {
            if (!tripSelect) return;
            tripSelect.innerHTML = '<option value="">' + message + '</option>';
            tripSelect.disabled = true;
            if (tripBalance) tripBalance.hidden = true;
            tripsCache = [];
            if (amountInput) {
                amountInput.removeAttribute('max');
                amountInput.value = '';
            }
            if (amountHelp) amountHelp.textContent = 'Maximum amount depends on the selected trip balance.';
        }

        function updateTripBalance(trip) {
            if (!trip || !tripBalance) {
                if (tripBalance) tripBalance.hidden = true;
                return;
            }

            if (tripTotal) tripTotal.textContent = formatMoney(trip.total_amount);
            if (tripPaid) tripPaid.textContent = formatMoney(trip.paid_amount);
            if (tripRemaining) tripRemaining.textContent = formatMoney(trip.remaining_amount);
            tripBalance.hidden = false;

            if (amountInput) {
                amountInput.max = trip.remaining_amount;
                if (amountHelp) amountHelp.textContent = 'Maximum: ' + trip.remaining_formatted;
            }
        }

        function applyAmountForTrip(trip, preserveAmount) {
            if (!amountInput || !trip) {
                return;
            }

            if (preserveAmount && oldAmount !== null && oldAmount !== '') {
                amountInput.value = oldAmount;
                return;
            }

            amountInput.value = Number(trip.remaining_amount).toFixed(2);
        }

        function selectTrip(tripId, preserveAmount) {
            if (!tripSelect || !tripId) {
                return null;
            }

            var trip = tripsCache.find(function (item) {
                return String(item.id) === String(tripId);
            });

            if (!trip) {
                return null;
            }

            tripSelect.value = String(tripId);
            updateTripBalance(trip);
            applyAmountForTrip(trip, !!preserveAmount);

            return trip;
        }

        function populateTrips(trips, selectedTripId) {
            tripsCache = trips || [];

            if (!tripSelect) return;

            if (!tripsCache.length) {
                resetTripSelect('No trips with outstanding balance');
                if (tripHelp) tripHelp.textContent = 'This customer has no trips with a remaining balance.';
                if (amountInput) amountInput.value = '';
                return;
            }

            tripSelect.innerHTML = '<option value="">Select Trip</option>';
            tripsCache.forEach(function (trip) {
                var option = document.createElement('option');
                option.value = trip.id;
                option.textContent = trip.label + ' — Balance ' + trip.remaining_formatted;
                tripSelect.appendChild(option);
            });

            tripSelect.disabled = false;
            if (tripHelp) tripHelp.textContent = 'Trip and amount are pre-filled from the outstanding balance. You can change either.';

            var tripToSelect = selectedTripId || oldTripId;
            if (!tripToSelect) {
                tripToSelect = tripsCache[0].id;
            }

            var preserveAmount = !!(oldTripId && oldAmount !== null && oldAmount !== '' && String(tripToSelect) === String(oldTripId));
            selectTrip(tripToSelect, preserveAmount);
        }

        function loadCustomerTrips(customerId, selectedTripId) {
            if (!customerId) {
                resetTripSelect('Select customer first');
                renderCustomerBalance(customerBalance, null, prefix);
                return Promise.resolve();
            }

            resetTripSelect('Loading trips...');

            return fetch(customersBaseUrl + '/' + customerId + '/trips', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                renderCustomerBalance(customerBalance, data.customer || null, prefix);
                populateTrips(data.trips || [], selectedTripId);
            })
            .catch(function () {
                resetTripSelect('Could not load trips');
                renderCustomerBalance(customerBalance, null, prefix);
                if (tripHelp) tripHelp.textContent = 'Failed to load trips. Please try again.';
            });
        }

        function setCustomer(customerId, lockCustomer) {
            customerId = String(customerId || '');
            if (customerIdInput) customerIdInput.value = customerId;

            if (customerSelect) {
                customerSelect.value = customerId;
                customerSelect.disabled = !!lockCustomer;
            }

            if (customerGroup) {
                customerGroup.hidden = !!lockCustomer;
            }

            if (customerSummary) {
                customerSummary.hidden = !lockCustomer;
            }

            if (customerName && customerId) {
                customerName.textContent = customerNames[customerId] || customerNames[Number(customerId)] || '-';
            }

            return loadCustomerTrips(customerId);
        }

        if (customerSelect) {
            customerSelect.addEventListener('change', function () {
                oldTripId = '';
                oldAmount = '';
                setCustomer(this.value, false);
            });
        }

        if (tripSelect) {
            tripSelect.addEventListener('change', function () {
                if (!this.value) {
                    updateTripBalance(null);
                    if (amountInput) amountInput.value = '';
                    return;
                }

                selectTrip(this.value, false);
            });
        }

        return {
            setCustomer: setCustomer,
            reset: function () {
                if (customerSelect) {
                    customerSelect.disabled = false;
                    customerSelect.value = '';
                }
                if (customerGroup) customerGroup.hidden = false;
                if (customerSummary) customerSummary.hidden = true;
                oldTripId = '';
                oldAmount = '';
                resetTripSelect('Select customer first');
                renderCustomerBalance(customerBalance, null, prefix);
            }
        };
    }

    var recordForm = createRecordFormController('modal-payment');

    function openRecordPaymentModal(customerId, customerName) {
        if (!recordModal) return;

        var title = document.getElementById('record-payment-modal-title');
        if (title) {
            title.textContent = customerName ? ('Record Payment — ' + customerName) : 'Record Payment';
        }

        openModal(recordModal);

        if (customerId) {
            recordForm.setCustomer(customerId, true);
        } else {
            recordForm.reset();
        }
    }

    bindModalClose(recordModal, 'data-close-record-payment-modal');

    document.querySelectorAll('.js-open-record-payment').forEach(function (button) {
        button.addEventListener('click', function () {
            openRecordPaymentModal(this.getAttribute('data-customer-id'), this.getAttribute('data-customer-name'));
        });
    });

    var openRecordBtn = document.getElementById('open-record-payment-modal');
    if (openRecordBtn) {
        openRecordBtn.addEventListener('click', function () {
            openRecordPaymentModal('', '');
        });
    }

    if (autoOpenCustomerId) {
        var autoName = customerNames[autoOpenCustomerId] || customerNames[Number(autoOpenCustomerId)] || '';
        openRecordPaymentModal(autoOpenCustomerId, autoName);
    }
})();

@if (session('open_payment_receipt') && session('payment_receipt'))
(function () {
    var printUrl = @json(session('payment_receipt')['print_url']);
    if (printUrl) {
        window.open(printUrl, '_blank', 'noopener');
    }
})();
@endif
</script>
@endpush
