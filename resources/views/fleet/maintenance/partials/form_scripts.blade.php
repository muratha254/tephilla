<script>
(function () {
    var receiptInput = document.getElementById('maintenance-receipt');
    var receiptName = document.getElementById('maintenance-receipt-name');

    if (receiptInput && receiptName) {
        receiptInput.addEventListener('change', function () {
            receiptName.textContent = this.files[0] ? this.files[0].name : 'Choose File';
        });
    }
})();
</script>
