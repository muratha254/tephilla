<script>
(function () {
    var mergeSelectInitialized = false;

    function initMergeKeepSelect() {
        if (!$('#merge_keep_select').length) {
            return;
        }
        if ($('#merge_keep_select').data('select2')) {
            $('#merge_keep_select').select2('destroy');
        }
        $('#merge_keep_select').empty().append('<option value=""></option>');

        var $modal = $('#modal-merge-duplicate');
        var $dropdownParent = $modal.find('.modal-content').first();
        if (!$dropdownParent.length) {
            $dropdownParent = $modal;
        }

        $('#merge_keep_select').select2({
            placeholder: 'Type at least 2 characters (code or name)…',
            allowClear: true,
            width: '100%',
            dropdownParent: $dropdownParent,
            ajax: {
                url: '{{ route('produk.search_for_merge') }}',
                dataType: 'json',
                delay: 280,
                data: function (params) {
                    return {
                        q: params.term,
                        exclude_id: $('#merge_remove_id').val(),
                        shop_id: $('#merge_remove_shop_id').val()
                    };
                },
                processResults: function (data) {
                    return { results: data.results || [] };
                }
            },
            minimumInputLength: 2
        });
        mergeSelectInitialized = true;
    }

    window.openMergeDuplicateModal = function (opts) {
        opts = opts || {};
        var removeId = opts.removeId || '';
        var code = opts.itemCode || '';
        var name = opts.productName || '';
        var stok = opts.stok != null ? opts.stok : '';
        var shopId = opts.shopId || '';

        $('#merge_remove_id').val(removeId);
        $('#merge_remove_shop_id').val(shopId);
        $('#merge-remove-summary').html(
            '<strong>Row to remove after merge:</strong><br>'
            + '<span class="label label-success">' + $('<div>').text(code).html() + '</span> '
            + $('<div>').text(name).html()
            + (stok !== '' ? ' &mdash; Left: <strong>' + stok + '</strong>' : '')
        );
        $('#merge_keep_select').val(null).trigger('change');
        $('#modal-merge-duplicate').modal('show');
    };

    $(document).on('shown.bs.modal', '#modal-merge-duplicate', function () {
        initMergeKeepSelect();
    });

    $(document).on('click', '.btn-open-merge-duplicate', function () {
        var $btn = $(this);
        openMergeDuplicateModal({
            removeId: $btn.data('produk-id'),
            itemCode: $btn.data('item-code') || '',
            productName: $btn.data('product-name') || '',
            stok: $btn.data('stok'),
            shopId: $btn.data('shop-id') || ''
        });
    });

    $(document).on('click', '#btn_confirm_merge_duplicate', function () {
        var keepId = $('#merge_keep_select').val();
        var removeId = $('#merge_remove_id').val();
        if (!removeId) {
            alert('No product selected to merge away.');
            return;
        }
        if (!keepId) {
            alert('Search and select the product you want to keep.');
            return;
        }
        var removeLabel = $('#merge-remove-summary').text().replace(/\s+/g, ' ').trim();
        var keepText = $('#merge_keep_select option:selected').text() || ('#' + keepId);
        var msg = 'Merge duplicate stock?\n\n'
            + 'KEEP: ' + keepText + '\n'
            + 'REMOVE: ' + removeLabel + '\n\n'
            + 'All sales and stock from the removed row will move to the kept product. This cannot be undone.';
        if (!confirm(msg)) {
            return;
        }

        $.ajax({
            url: '{{ route('produk.merge_stock_duplicate') }}',
            type: 'POST',
            dataType: 'json',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                keep_id: keepId,
                remove_id: removeId
            }
        }).done(function (resp) {
            $('#modal-merge-duplicate').modal('hide');
            if (typeof table !== 'undefined' && table && table.ajax) {
                table.ajax.reload(null, false);
            }
            alert((resp && resp.message) ? resp.message : 'Merged successfully.');
        }).fail(function (xhr) {
            var err = 'Merge failed.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                err = xhr.responseJSON.message;
            }
            alert(err);
        });
    });
})();
</script>
