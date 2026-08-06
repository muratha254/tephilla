<script>
(function () {
    var modal = document.getElementById('trip-record-payment-modal');
    var form = document.getElementById('trip-record-payment-form');
    var titleEl = document.getElementById('trip-record-payment-title');
    var tripIdInput = document.getElementById('trip-payment-trip-id');
    var amountInput = document.getElementById('trip-payment-amount');
    var amountHelp = document.getElementById('trip-payment-amount-help');
    var errorsEl = document.getElementById('trip-payment-errors');

    if (!modal || !form) return;

    function openModal(payload) {
        form.action = payload.action;
        if (tripIdInput) tripIdInput.value = payload.tripId || '';
        if (titleEl) titleEl.textContent = 'Record Payment - ' + payload.tripCode;

        document.getElementById('trip-payment-trip-code').textContent = payload.tripCode;
        document.getElementById('trip-payment-customer-name').textContent = payload.customer;
        document.getElementById('trip-payment-trip-total').textContent = payload.totalLabel;
        document.getElementById('trip-payment-trip-paid').textContent = payload.paidLabel;
        document.getElementById('trip-payment-trip-remaining').textContent = payload.remainingLabel;

        if (amountInput) {
            amountInput.max = payload.remaining;
            if (!@json($errors->any()) || !amountInput.value) {
                amountInput.value = payload.remaining > 0 ? payload.remaining.toFixed(2) : '';
            }
        }

        if (amountHelp) {
            amountHelp.textContent = 'Max: ' + payload.remainingLabel;
        }

        modal.hidden = false;
        document.body.classList.add('fleet-modal-open');
        if (amountInput) amountInput.focus();
    }

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('fleet-modal-open');
    }

    function payloadFromButton(button) {
        return {
            action: button.getAttribute('data-action'),
            tripId: button.getAttribute('data-trip-id'),
            tripCode: button.getAttribute('data-trip-code'),
            customer: button.getAttribute('data-customer'),
            totalLabel: button.getAttribute('data-total-label'),
            paidLabel: button.getAttribute('data-paid-label'),
            remainingLabel: button.getAttribute('data-remaining-label'),
            remaining: parseFloat(button.getAttribute('data-remaining') || '0') || 0
        };
    }

    document.querySelectorAll('.fleet-trip-record-payment-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            openModal(payloadFromButton(this));
        });
    });

    modal.querySelectorAll('[data-close-trip-payment-modal]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });

    @if ($errors->any() && old('_payment_trip_id'))
    (function () {
        var tripId = @json(old('_payment_trip_id'));
        var button = document.querySelector('.fleet-trip-record-payment-btn[data-trip-id="' + tripId + '"]');
        if (button) {
            openModal(payloadFromButton(button));
        }
    })();
    @endif
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
