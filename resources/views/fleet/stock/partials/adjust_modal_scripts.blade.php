<script>
(function () {
    var modal = document.getElementById('stock-adjust-modal');
    var form = document.getElementById('stock-adjust-form');
    var quantityInput = document.getElementById('stock-adjust-quantity');
    var purchasedFromInput = document.getElementById('stock-adjust-purchased-from');
    var costInput = document.getElementById('stock-adjust-cost');
    var paymentStatusInput = document.getElementById('stock-adjust-payment-status');
    var descriptionInput = document.getElementById('stock-adjust-description');
    var adjustBaseUrl = @json(url('/stock'));

    if (!modal || !form) {
        return;
    }

    function resetForm(defaultCost) {
        quantityInput.value = '';
        purchasedFromInput.value = '';
        costInput.value = defaultCost || '';
        paymentStatusInput.selectedIndex = 0;
        descriptionInput.value = '';
    }

    function openModal(itemId, defaultCost) {
        form.action = adjustBaseUrl + '/' + itemId + '/adjust';
        resetForm(defaultCost);
        modal.hidden = false;
        document.body.classList.add('fleet-modal-open');
        quantityInput.focus();
    }

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('fleet-modal-open');
    }

    document.querySelectorAll('[data-adjust-stock]').forEach(function (button) {
        button.addEventListener('click', function () {
            openModal(
                button.getAttribute('data-item-id'),
                button.getAttribute('data-item-price') || ''
            );
        });
    });

    modal.querySelectorAll('[data-close-stock-modal]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });
})();
</script>
