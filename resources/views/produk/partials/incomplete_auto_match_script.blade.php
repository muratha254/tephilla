<script>
    function resetIncompleteAutoMatchUi() {
        $('#incomplete-auto-match-banner').hide();
        $('#incomplete-auto-match-text').text('');
        $('#existing_id_produk').val('');
    }

    /** When editing a quick-add row, show that entered code matches stock and will auto-link on save. */
    function syncIncompleteEditAutoMatch(resp) {
        if (!$('#modal-form').is(':visible')) {
            return;
        }
        if (($('#modal-form [name=_method]').val() || '').toLowerCase() !== 'put') {
            resetIncompleteAutoMatchUi();
            return;
        }
        if (!$('#is_incomplete').val() || String($('#is_incomplete').val()) === '0') {
            resetIncompleteAutoMatchUi();
            return;
        }

        var selectedShopId = parseInt($('#shop_id').val(), 10) || 0;
        var selfId = parseInt($('#modal-form form').attr('action').split('/').pop(), 10) || 0;
        var match = null;
        if (resp && resp.found && Array.isArray(resp.matches)) {
            match = resp.matches.find(function (m) {
                return parseInt(m.shop_id, 10) === selectedShopId && parseInt(m.id_produk, 10) !== selfId;
            });
        }
        if (!match && resp && resp.found && parseInt(resp.shop_id, 10) === selectedShopId && parseInt(resp.id_produk, 10) !== selfId) {
            match = {
                id_produk: resp.id_produk,
                nama_produk: resp.nama_produk,
                item_code: resp.item_code
            };
        }
        if (!match || !match.id_produk) {
            resetIncompleteAutoMatchUi();
            return;
        }

        $('#existing_id_produk').val(String(match.id_produk));
        var name = (match.nama_produk || '').toString();
        $('#incomplete-auto-match-text').html(
            'This code matches existing stock <strong>#' + match.id_produk + '</strong>'
            + (name ? ' — ' + $('<div/>').text(name).html() : '')
            + '. When you save, sales and stock will link to that product automatically.'
        );
        $('#incomplete-auto-match-banner').show();
    }

    function lookupIncompleteAutoMatchForEdit() {
        if (!$('#modal-form').is(':visible')) return;
        if (($('#modal-form [name=_method]').val() || '').toLowerCase() !== 'put') return;
        if (!$('#is_incomplete').val() || String($('#is_incomplete').val()) === '0') return;

        var itemCode = ($('#item_code').val() || '').trim();
        var shopId = parseInt($('#shop_id').val(), 10) || 0;
        if (!itemCode || shopId <= 0) {
            resetIncompleteAutoMatchUi();
            return;
        }

        $.get('{{ route('produk.quick_lookup') }}', {
            item_code: itemCode,
            shop_id: shopId
        }).done(function (resp) {
            syncIncompleteEditAutoMatch(resp);
        });
    }

    $(function () {
        if (typeof quickLookupByItemCode === 'function') {
            return;
        }
        var incompleteAutoMatchTimer = null;
        $(document).on('input', '#item_code', function () {
            if (($('#modal-form [name=_method]').val() || '').toLowerCase() !== 'put') return;
            if (String($('#is_incomplete').val() || '0') !== '1') return;
            if (($(this).val() || '').trim().length < 2) return;
            clearTimeout(incompleteAutoMatchTimer);
            incompleteAutoMatchTimer = setTimeout(lookupIncompleteAutoMatchForEdit, 500);
        });
        $(document).on('blur', '#item_code', function () {
            if (($('#modal-form [name=_method]').val() || '').toLowerCase() !== 'put') return;
            if (String($('#is_incomplete').val() || '0') !== '1') return;
            lookupIncompleteAutoMatchForEdit();
        });
        $(document).on('change', '#shop_id', function () {
            if (($('#modal-form [name=_method]').val() || '').toLowerCase() !== 'put') return;
            if (String($('#is_incomplete').val() || '0') !== '1') return;
            lookupIncompleteAutoMatchForEdit();
        });
    });
</script>
