<script>
(function () {
    var checkAll = document.getElementById('employee-check-all-permissions');
    var boxes = document.querySelectorAll('.employee-permission-checkbox');

    if (!checkAll || !boxes.length) return;

    function syncCheckAll() {
        var checkedCount = 0;
        boxes.forEach(function (box) {
            if (box.checked) checkedCount++;
        });
        checkAll.checked = checkedCount === boxes.length;
        checkAll.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
    }

    checkAll.addEventListener('change', function () {
        boxes.forEach(function (box) {
            box.checked = checkAll.checked;
        });
        checkAll.indeterminate = false;
    });

    boxes.forEach(function (box) {
        box.addEventListener('change', syncCheckAll);
    });

    syncCheckAll();
})();
</script>
