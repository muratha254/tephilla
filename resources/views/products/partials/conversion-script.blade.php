<script>
(function ($) {
    function resetConversionTabs() {
        $('#sx-conversion-modal .nav-tabs li').removeClass('active').first().addClass('active');
        $('#sx-conv-create').addClass('active');
        $('#sx-conv-list').removeClass('active');
    }

    function loadConversionList(url) {
        var tbody = $('#sx-conv-list-table tbody');
        tbody.html('<tr><td colspan="5">Loading...</td></tr>');
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (rows) {
                if (!rows.length) {
                    tbody.html('<tr class="sx-conv-empty"><td colspan="5" class="sx-none-found">No record found!!!</td></tr>');
                    return;
                }
                tbody.empty();
                rows.forEach(function (row) {
                    tbody.append(
                        '<tr><td>' + row.code + '</td><td>' + row.name + '</td><td>' + (row.unit || '-') +
                        '</td><td>' + row.rate + '</td><td>' + Number(row.selling_price).toFixed(2) + '</td></tr>'
                    );
                });
            })
            .catch(function () {
                tbody.html('<tr><td colspan="5" class="sx-none-found">Could not load conversions.</td></tr>');
            });
    }

    $(document).on('click', '.sx-open-conversion', function (e) {
        e.preventDefault();
        var btn = $(this);
        $('#sx-conversion-form').attr('action', btn.data('url'));
        $('#sx-conv-name').val('');
        $('#sx-conv-rate').val('');
        $('#sx-conv-desc').val('');
        $('#sx-conv-unit').val('');
        $('#sx-conv-price').val(Number(btn.data('price') || 0).toFixed(2));
        $('#sx-conversion-modal').data('list-url', btn.data('list'));
        resetConversionTabs();
        $('#sx-conversion-modal').modal('show');
    });

    $('#sx-conv-list-tab').on('shown.bs.tab', function () {
        var url = $('#sx-conversion-modal').data('list-url');
        if (url) {
            loadConversionList(url);
        }
    });
})(jQuery);
</script>
