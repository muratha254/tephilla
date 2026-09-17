@extends('layouts.fleet')

@section('title', 'Print Labels')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Print Labels',
    'subtitle' => 'Add/Update sale',
    'backUrl' => route('products.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Print Labels'],
    ],
])

<form id="sx-label-form" action="{{ route('products.labels.print') }}" method="post" target="_blank">
    @csrf
    <input type="hidden" name="auto_print" id="sx-label-autoprint" value="0">

    <div class="sx-label-page">
        <div class="sx-label-search-wrap">
            <div class="sx-label-search">
                <span class="sx-label-search-icon"><i class="fa fa-th-large"></i></span>
                <input type="text" id="sx-label-q" class="form-control" placeholder="Item name/Barcode/item code" autocomplete="off">
                <div id="sx-label-results" class="sx-label-results" hidden></div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered sx-gold-table sx-label-table">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th class="sx-label-qty-col">Quantity</th>
                        <th class="sx-label-action-col">Action</th>
                    </tr>
                </thead>
                <tbody id="sx-label-rows">
                </tbody>
            </table>
        </div>

        <div class="sx-label-total">
            <span>Total Labels</span>
            <strong id="sx-label-total">0</strong>
        </div>

        <div class="sx-label-actions">
            <button type="button" class="btn sx-btn-preview" id="sx-label-preview">Preview</button>
            <a href="{{ route('products.index') }}" class="btn sx-btn-label-close">Close</a>
            <button type="button" class="btn sx-btn-print" id="sx-label-print">Print</button>
        </div>

        <div id="sx-label-preview-area" class="sx-label-preview-area" hidden></div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function ($) {
    var searchUrl = @json(route('products.search'));
    var previewUrl = @json(route('products.labels.preview'));
    var token = $('meta[name="csrf-token"]').attr('content');
    var timer = null;
    var rows = {};

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : value).html();
    }

    function totalLabels() {
        var total = 0;
        Object.keys(rows).forEach(function (id) {
            total += rows[id].qty;
        });
        $('#sx-label-total').text(total);
        return total;
    }

    function syncHidden() {
        $('#sx-label-form input[name^="items["]').remove();
        Object.keys(rows).forEach(function (id) {
            $('<input>', {
                type: 'hidden',
                name: 'items[' + id + ']',
                value: rows[id].qty
            }).appendTo('#sx-label-form');
        });
    }

    function renderRow(item) {
        var row = $(
            '<tr data-id="' + item.id + '">' +
                '<td><input type="text" class="form-control" value="' + escapeHtml(item.name) + '" readonly></td>' +
                '<td>' +
                    '<div class="sx-qty">' +
                        '<button type="button" class="sx-qty-btn sx-qty-minus">&minus;</button>' +
                        '<input type="number" class="form-control sx-qty-input" min="1" max="500" value="' + item.qty + '">' +
                        '<button type="button" class="sx-qty-btn sx-qty-plus">+</button>' +
                    '</div>' +
                '</td>' +
                '<td class="text-center">' +
                    '<button type="button" class="btn btn-danger sx-qty-remove" title="Remove"><i class="fa fa-minus"></i></button>' +
                '</td>' +
            '</tr>'
        );
        $('#sx-label-rows').append(row);
    }

    function addItem(item) {
        var id = String(item.id);
        if (rows[id]) {
            rows[id].qty += 1;
            $('#sx-label-rows tr[data-id="' + id + '"] .sx-qty-input').val(rows[id].qty);
        } else {
            rows[id] = { id: item.id, name: item.name, qty: 1 };
            renderRow(rows[id]);
        }
        syncHidden();
        totalLabels();
        clearPreview();
        $('#sx-label-q').val('').focus();
        hideResults();
    }

    function hideResults() {
        $('#sx-label-results').empty().attr('hidden', true);
    }

    function showResults(items) {
        var box = $('#sx-label-results').empty();
        if (!items.length) {
            box.append('<div class="sx-label-empty">No matching items</div>').removeAttr('hidden');
            return;
        }
        items.forEach(function (item) {
            var meta = item.sku || item.barcode || '';
            box.append(
                '<button type="button" class="sx-label-result" data-id="' + item.id + '" data-name="' + escapeHtml(item.name) + '">' +
                    '<strong>' + escapeHtml(item.name) + '</strong>' +
                    (meta ? '<span>' + escapeHtml(meta) + '</span>' : '') +
                '</button>'
            );
        });
        box.removeAttr('hidden');
    }

    $('#sx-label-q').on('input', function () {
        var q = $.trim(this.value);
        clearTimeout(timer);
        if (!q) {
            hideResults();
            return;
        }
        timer = setTimeout(function () {
            $.getJSON(searchUrl, { q: q }).done(showResults).fail(hideResults);
        }, 250);
    });

    $(document).on('click', '.sx-label-result', function () {
        addItem({ id: $(this).data('id'), name: $(this).data('name') });
    });

    $(document).on('click', '.sx-qty-plus', function () {
        var tr = $(this).closest('tr');
        var id = String(tr.data('id'));
        rows[id].qty = Math.min(500, rows[id].qty + 1);
        tr.find('.sx-qty-input').val(rows[id].qty);
        syncHidden();
        totalLabels();
        clearPreview();
    });

    $(document).on('click', '.sx-qty-minus', function () {
        var tr = $(this).closest('tr');
        var id = String(tr.data('id'));
        rows[id].qty = Math.max(1, rows[id].qty - 1);
        tr.find('.sx-qty-input').val(rows[id].qty);
        syncHidden();
        totalLabels();
        clearPreview();
    });

    $(document).on('change', '.sx-qty-input', function () {
        var tr = $(this).closest('tr');
        var id = String(tr.data('id'));
        var qty = parseInt(this.value, 10);
        if (!qty || qty < 1) qty = 1;
        if (qty > 500) qty = 500;
        rows[id].qty = qty;
        this.value = qty;
        syncHidden();
        totalLabels();
        clearPreview();
    });

    $(document).on('click', '.sx-qty-remove', function () {
        var tr = $(this).closest('tr');
        delete rows[String(tr.data('id'))];
        tr.remove();
        syncHidden();
        totalLabels();
        clearPreview();
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.sx-label-search').length) {
            hideResults();
        }
    });

    function clearPreview() {
        $('#sx-label-preview-area').empty().attr('hidden', true);
    }

    function requireItems() {
        if (totalLabels()) {
            return true;
        }
        if (window.Swal) {
            Swal.fire({ icon: 'warning', title: 'Add an item', text: 'Search and add at least one item first.' });
        } else {
            alert('Search and add at least one item first.');
        }
        return false;
    }

    function submitLabels(autoPrint) {
        if (!requireItems()) return;
        syncHidden();
        $('#sx-label-autoprint').val(autoPrint ? '1' : '0');
        $('#sx-label-form')[0].submit();
    }

    function previewLabels() {
        if (!requireItems()) return;
        syncHidden();
        $.ajax({
            url: previewUrl,
            method: 'POST',
            data: $('#sx-label-form').serialize(),
            headers: { 'X-CSRF-TOKEN': token }
        }).done(function (html) {
            $('#sx-label-preview-area').html(html).removeAttr('hidden');
            var area = document.getElementById('sx-label-preview-area');
            if (area) area.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }).fail(function () {
            if (window.Swal) {
                Swal.fire({ icon: 'error', title: 'Preview failed', text: 'Could not build the label preview.' });
            } else {
                alert('Could not build the label preview.');
            }
        });
    }

    $('#sx-label-preview').on('click', function () { previewLabels(); });
    $('#sx-label-print').on('click', function () { submitLabels(true); });
})(jQuery);
</script>
@endpush
