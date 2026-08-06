<script>

(function () {

    function escapeHtmlStockMv(s) {

        return String(s == null ? '' : s)

            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    }



    function renderStockMovementRows(rows) {

        if (!rows.length) {

            return '<tr><td colspan="6" class="text-center text-muted">No stock movements or sales recorded yet for this product.</td></tr>';

        }

        var html = '';

        for (var i = 0; i < rows.length; i++) {

            var ev = rows[i];

            var isEdit = ev.kind === 'edit';

            var ch = ev.change;

            var chStr = (ch === null || ch === undefined) ? '—' : (ch > 0 ? '+' + ch : String(ch));

            var bal = ev.balance_display;

            var balStr = (bal === null || bal === undefined) ? '—' : String(bal);

            var detail = ev.detail || '';

            if (!detail && ev.title) {

                detail = '';

            }

            var rowClass = isEdit ? ' class="stock-movement-edit-row"' : '';

            html += '<tr' + rowClass + '>' +

                '<td>' + escapeHtmlStockMv(ev.date_label || '') + '</td>' +

                '<td>' + escapeHtmlStockMv(ev.time_label || '') + '</td>' +

                '<td>' + escapeHtmlStockMv(ev.title || '') + '</td>' +

                '<td class="stock-movement-detail-cell">' + escapeHtmlStockMv(detail) + '</td>' +

                '<td class="text-right">' + escapeHtmlStockMv(chStr) + '</td>' +

                '<td class="text-right"><strong>' + escapeHtmlStockMv(balStr) + '</strong></td>' +

                '</tr>';

        }

        return html;

    }



    var stockMovementPdfUrlTemplate = @json(route('produk.stock-movement-pdf', ['produk' => 999999999]));



    $(document).on('click', '.btn-open-stock-movement', function (e) {

        e.preventDefault();

        var $btn = $(this);

        var url = $btn.attr('data-url');

        var pid = $btn.attr('data-produk-id');

        var name = $btn.attr('data-product-name') || '';

        var pdfUrl = stockMovementPdfUrlTemplate.replace('999999999', String(pid || ''));

        if (pid) {

            $('#btn-stock-movement-export-pdf').attr('href', pdfUrl).css('display', 'inline-block');

        } else {

            $('#btn-stock-movement-export-pdf').attr('href', '#').hide();

        }

        $('#stock-movement-product-name').text(name);

        $('#stock-movement-tbody').html('<tr><td colspan="6" class="text-center text-muted">Loading…</td></tr>');

        $('#stock-movement-summary').text('');

        $('#stock-movement-current-stock').text('');

        $('#modal-stock-movement').modal('show');

        if (!url) {

            $('#stock-movement-tbody').html('<tr><td colspan="6" class="text-center text-danger">Missing URL.</td></tr>');

            return;

        }

        $.get(url)

            .done(function (res) {

                var p = res.product || {};

                var parts = [];

                if (p.code) { parts.push('Code: ' + p.code); }

                if (p.supplier) { parts.push('Supplier: ' + p.supplier); }

                if (p.shop) { parts.push('Shop: ' + p.shop); }

                $('#stock-movement-summary').text(parts.join(' · '));

                var csVal = (p.current_stock != null && p.current_stock !== undefined) ? p.current_stock : '—';
                var stockHtml = '<strong>Current stock: ' + escapeHtmlStockMv(String(csVal)) + '</strong>';
                if (res.note) {
                    stockHtml += '<div class="alert alert-warning" style="margin-top:8px;padding:8px 12px;margin-bottom:0;">' + escapeHtmlStockMv(res.note) + '</div>';
                }
                $('#stock-movement-current-stock').html(stockHtml);

                $('#stock-movement-tbody').html(renderStockMovementRows(res.events || []));

            })

            .fail(function (xhr) {

                var msg = 'Unable to load stock movement.';

                if (xhr.responseJSON && xhr.responseJSON.message) {

                    msg = xhr.responseJSON.message;

                }

                $('#stock-movement-tbody').html('<tr><td colspan="6" class="text-center text-danger">' + escapeHtmlStockMv(msg) + '</td></tr>');

                $('#stock-movement-current-stock').text('Current stock: —');

            });

    });

})();

</script>

<style>

    #stock-movement-table tr.stock-movement-edit-row td {

        background-color: #fff8e6 !important;

    }

    #stock-movement-table .stock-movement-detail-cell {

        font-size: 12px;

        line-height: 1.4;

        word-break: break-word;

    }

</style>


