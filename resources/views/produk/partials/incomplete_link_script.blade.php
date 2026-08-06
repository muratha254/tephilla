<script>
(function () {
    function $saveBtn() {
        return $('#btn_modal_save_produk');
    }
    function $linkBtn() {
        return $('#btn_link_incomplete');
    }

    /** When quick-add "Wrong code?" panel is visible: Save needs search engagement; Link needs a selected product. */
    window.refreshIncompleteLinkFooterButtons = function () {
        var $panel = $('#incomplete-link-panel');
        if (!$panel.length || !$panel.is(':visible')) {
            if ($saveBtn().length) {
                $saveBtn().prop('disabled', false);
            }
            if ($linkBtn().length) {
                $linkBtn().prop('disabled', false);
            }
            return;
        }
        var $sel = $('#link_target_select');
        var hasSelection = (function () {
            try {
                var v = $sel.val();
                if (v === null || v === undefined || v === '') {
                    return false;
                }
                if (Array.isArray(v)) {
                    return v.length > 0;
                }
                return String(v).length > 0;
            } catch (e) {
                return false;
            }
        })();
        var engaged = !!$sel.data('linkSearchEngaged');
        // When user begins linking (types into search), disable Save to force linking flow.
        // If user hasn't typed yet (engaged=false), Save remains active.
        if ($linkBtn().length) {
            $linkBtn().prop('disabled', !hasSelection);
        }
        if ($saveBtn().length) {
            $saveBtn().prop('disabled', engaged);
        }
    };

    window.initLinkTargetSelect = function () {
        if ($('#link_target_select').length === 0) {
            return;
        }
        // Ensure it's interactable even if the modal previously disabled fields
        $('#link_target_select').prop('disabled', false);
        if ($('#link_target_select').data('select2')) {
            $('#link_target_select').select2('destroy');
        }
        $('#link_target_select').removeData('linkSearchEngaged');
        var $modal = $('#modal-form');
        var $dropdownParent = $modal.find('.modal-content').first();
        if (!$dropdownParent.length) {
            $dropdownParent = $modal;
        }
        $('#link_target_select').select2({
            placeholder: 'Type at least 2 characters (code or name)…',
            allowClear: true,
            width: '100%',
            dropdownParent: $dropdownParent,
            ajax: {
                url: '{{ route('produk.search_for_link') }}',
                dataType: 'json',
                delay: 280,
                data: function (params) {
                    return { q: params.term, exclude_id: $('#link_remove_id').val() };
                },
                processResults: function (data) {
                    return { results: data.results || [] };
                }
            },
            minimumInputLength: 2
        });

        $('#link_target_select').off('.incompleteLink');
        $('#link_target_select').on('select2:open.incompleteLink', function () {
            setTimeout(function () {
                var $field = $('.select2-container--open .select2-search__field');
                $field.off('input.incompleteLink').on('input.incompleteLink', function () {
                    if ($(this).val().length >= 2) {
                        $('#link_target_select').data('linkSearchEngaged', true);
                        if (typeof window.refreshIncompleteLinkFooterButtons === 'function') {
                            window.refreshIncompleteLinkFooterButtons();
                        }
                    }
                });
            }, 0);
        });
        $('#link_target_select').on('select2:select.incompleteLink', function () {
            $('#link_target_select').data('linkSearchEngaged', true);
            if (typeof window.refreshIncompleteLinkFooterButtons === 'function') {
                window.refreshIncompleteLinkFooterButtons();
            }
        });
        $('#link_target_select').on('select2:clear.incompleteLink', function () {
            if (typeof window.refreshIncompleteLinkFooterButtons === 'function') {
                window.refreshIncompleteLinkFooterButtons();
            }
        });

        $('#link_target_select').off('select2:open.linkScrollFix');
        $('#link_target_select').on('select2:open.linkScrollFix', function () {
            var winY = $(window).scrollTop();
            var $mb = $modal.find('.modal-body').first();
            var modalBodyY = $mb.length ? $mb.scrollTop() : 0;
            function restoreScroll() {
                $(window).scrollTop(winY);
                if ($mb.length) {
                    $mb.scrollTop(modalBodyY);
                }
            }
            restoreScroll();
            setTimeout(restoreScroll, 0);
            setTimeout(restoreScroll, 10);
            setTimeout(restoreScroll, 30);
            setTimeout(function () {
                var el = $modal.find('.select2-container--open .select2-search__field').get(0);
                if (el && typeof el.focus === 'function') {
                    try {
                        el.focus({ preventScroll: true });
                    } catch (e) {
                        el.focus();
                    }
                }
                restoreScroll();
            }, 0);
            setTimeout(restoreScroll, 100);
        });

        if (typeof window.refreshIncompleteLinkFooterButtons === 'function') {
            window.refreshIncompleteLinkFooterButtons();
        }
    };

    $(document).on('hidden.bs.modal', '#modal-form', function () {
        if (typeof window.refreshIncompleteLinkFooterButtons === 'function') {
            window.refreshIncompleteLinkFooterButtons();
        }
    });

    // Initialize whenever the modal is shown and the panel is visible.
    // Without Select2, a <select> is not typeable (user can't search).
    $(document).on('shown.bs.modal', '#modal-form', function () {
        if ($('#incomplete-link-panel').is(':visible')) {
            if (typeof window.initLinkTargetSelect === 'function') {
                window.initLinkTargetSelect();
            }
        }
    });

    $(document).on('click', '#btn_link_incomplete', function () {
        var keepId = $('#link_target_select').val();
        var removeId = $('#link_remove_id').val();
        if (!removeId) {
            alert('Open an incomplete quick-add row first.');
            return;
        }
        if (!keepId) {
            alert('Search and select the correct product that is already in stock.');
            return;
        }
        if (!confirm('Link this quick-add line to the selected stock product?\n\nPast sales will move to that product. Stock on that product will be adjusted: units sold on this line will reduce it, and this line\'s remaining quantity will be added.')) {
            return;
        }
        $.ajax({
            url: '{{ route('produk.link_incomplete_to_existing') }}',
            type: 'POST',
            dataType: 'json',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                keep_id: keepId,
                remove_id: removeId
            }
        }).done(function (resp) {
            $('#modal-form').modal('hide');
            if (typeof table !== 'undefined' && table && table.ajax) {
                try {
                    table.ajax.reload(null, false);
                } catch (e) {
                    table.ajax.reload();
                }
            }
            if (typeof updateIncompleteCount === 'function') {
                updateIncompleteCount();
            }
            if (typeof updateIncompleteProductsCount === 'function') {
                updateIncompleteProductsCount();
            }
            alert((resp && resp.message) ? resp.message : 'Linked successfully.');
        }).fail(function (xhr) {
            var err = 'Link failed.';
            var json = xhr.responseJSON;
            if (!json && xhr.responseText) {
                try { json = JSON.parse(xhr.responseText); } catch (e) { json = null; }
            }
            if (json && json.message) {
                err = json.message;
            }
            alert(err);
        });
    });
})();
</script>
