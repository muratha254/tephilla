<script>
(function () {
    var mobileInput = document.getElementById('customer-mobile');
    var whatsappInput = document.getElementById('customer-whatsapp');
    var sameCheckbox = document.getElementById('customer-same-mobile');

    if (!mobileInput || !whatsappInput || !sameCheckbox) {
        return;
    }

    function syncWhatsAppField() {
        if (sameCheckbox.checked) {
            whatsappInput.value = mobileInput.value;
            whatsappInput.setAttribute('disabled', 'disabled');
            whatsappInput.classList.add('is-disabled');
        } else {
            whatsappInput.removeAttribute('disabled');
            whatsappInput.classList.remove('is-disabled');
        }
    }

    sameCheckbox.addEventListener('change', syncWhatsAppField);
    mobileInput.addEventListener('input', syncWhatsAppField);
    syncWhatsAppField();

    var form = document.querySelector('.fleet-customer-form');
    if (form) {
        form.addEventListener('submit', function () {
            if (sameCheckbox.checked) {
                whatsappInput.removeAttribute('disabled');
                whatsappInput.value = mobileInput.value;
            }
        });
    }
})();
</script>
