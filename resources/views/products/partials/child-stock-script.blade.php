<script>
(function ($) {
    function syncControlQty() {
        $('#sx-child-control').val($('#sx-child-produce').val());
    }

    $(document).on('click', '.sx-open-child-stock', function (e) {
        e.preventDefault();
        var btn = $(this);
        $('#sx-child-stock-form').attr('action', btn.data('url'));
        $('#sx-child-batch-id').val(btn.data('batch'));
        $('#sx-child-name').val(btn.data('name') + '_child');
        $('#sx-child-convert').val(1);
        $('#sx-child-produce').val(btn.data('rate') || 1);
        $('#sx-child-reorder').val(0);
        $('#sx-child-break').val('');
        syncControlQty();
        $('#sx-child-stock-modal').modal('show');
    });

    $('#sx-child-produce').on('input', syncControlQty);
})(jQuery);
</script>
