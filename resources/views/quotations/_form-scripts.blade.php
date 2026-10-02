<script>
(function ($) {
    var rowIndex = $('#sx-qt-rows .sx-qt-row').length;

    function money(n) {
        return (Math.round((n + Number.EPSILON) * 100) / 100).toFixed(2);
    }

    function recalc() {
        var subtotal = 0;
        var taxTotal = 0;

        $('#sx-qt-rows .sx-qt-row').each(function () {
            var $row = $(this);
            var qty = parseFloat($row.find('.sx-qt-qty').val()) || 0;
            var price = parseFloat($row.find('.sx-qt-price').val()) || 0;
            var taxRate = parseFloat($row.find('.sx-qt-tax').val()) || 0;
            var discount = parseFloat($row.find('.sx-qt-discount').val()) || 0;
            var base = qty * price;
            if (discount > base) {
                discount = base;
            }
            var taxable = Math.max(0, base - discount);
            var tax = taxable * (taxRate / 100);
            var line = taxable + tax;
            subtotal += taxable;
            taxTotal += tax;
            $row.find('.sx-qt-line-total').text(money(line));
        });

        var headerDiscount = parseFloat($('#sx-qt-header-discount').val()) || 0;
        if (headerDiscount > subtotal) {
            headerDiscount = subtotal;
        }
        var grand = Math.max(0, subtotal - headerDiscount + taxTotal);

        $('#sx-qt-subtotal').text(money(subtotal));
        $('#sx-qt-tax').text(money(taxTotal));
        $('#sx-qt-grand').text(money(grand));
    }

    function bindRow($row) {
        $row.find('.sx-qt-product').on('change', function () {
            var $opt = $(this).find('option:selected');
            var price = parseFloat($opt.data('price'));
            var qty = $opt.attr('data-qty');
            if (price > 0) {
                $row.find('.sx-qt-price').val(price);
            } else if ($opt.val()) {
                $row.find('.sx-qt-price').val('');
            }
            if ($opt.data('tax') !== undefined && $opt.data('tax') !== '') {
                $row.find('.sx-qt-tax').val($opt.data('tax'));
            }
            var hint = '';
            if ($opt.val() && qty !== undefined && qty !== '') {
                hint = 'Available ' + qty;
            }
            if ($opt.val() && price > 0) {
                hint += (hint ? ' · ' : '') + 'Ksh ' + money(price);
            }
            $row.find('.sx-inv-stock').text(hint);
            recalc();
        });
        $row.find('.sx-qt-qty, .sx-qt-price, .sx-qt-tax, .sx-qt-discount').on('input change', recalc);
        $row.find('.sx-qt-remove').on('click', function () {
            if ($('#sx-qt-rows .sx-qt-row').length <= 1) {
                return;
            }
            $row.remove();
            recalc();
        });
    }

    $('#sx-qt-rows .sx-qt-row').each(function () {
        bindRow($(this));
    });

    $('#sx-qt-add-row').on('click', function () {
        var html = $('#sx-qt-row-template').html().replace(/__INDEX__/g, String(rowIndex++));
        var $row = $(html);
        $('#sx-qt-rows').append($row);
        bindRow($row);
        recalc();
    });

    $('#sx-qt-header-discount').on('input change', recalc);
    recalc();
})(jQuery);
</script>
