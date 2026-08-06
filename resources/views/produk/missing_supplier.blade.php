@extends('layouts.master')

@section('title')
    Products Without Supplier
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Products Without Supplier</li>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/select2/dist/css/select2.min.css') }}">
<style>
    .missing-supplier-sel-wrap { min-width: 220px; max-width: 320px; }
    .missing-supplier-sel-wrap .select2-container { width: 100% !important; }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-user-times"></i> Summary</h3>
            </div>
            <div class="box-body">
                <div class="info-box bg-yellow">
                    <span class="info-box-icon"><i class="fa fa-truck"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Products with no supplier assigned</span>
                        <span class="info-box-number" id="total-missing-supplier">0</span>
                    </div>
                </div>
                <p class="text-muted">Listed items exist in stock but have no supplier, supplier id 0, or point to a deleted supplier. Open <strong>Edit product</strong> to assign a supplier.</p>
            </div>
        </div>
    </div>

    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-list"></i> Product list</h3>
                <div class="btn-group pull-right">
                    <button type="button" id="btn-export-missing-supplier-pdf" class="btn btn-danger btn-flat">
                        <i class="fa fa-file-pdf-o"></i> Export PDF
                    </button>
                    <a href="{{ route('produk.index') }}" class="btn btn-info btn-flat"><i class="fa fa-list"></i> Enter Stock</a>
                </div>
            </div>
            <div class="box-body">
                <form method="GET" action="{{ route('produk.missing_supplier') }}" id="filterForm" class="mb-3">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="shop_id">Shop</label>
                                <select name="shop_id" id="shop_id" class="form-control">
                                    <option value="">All Shops</option>
                                    @foreach(\App\Models\Shop::orderBy('shop_name')->get() as $shop)
                                        <option value="{{ $shop->id }}" {{ request('shop_id') == $shop->id ? 'selected' : '' }}>
                                            {{ $shop->shop_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="category_id">Category</label>
                                <select name="category_id" id="category_id" class="form-control">
                                    <option value="">All Categories</option>
                                    @foreach(\App\Models\Kategori::orderBy('nama_kategori')->get() as $category)
                                        <option value="{{ $category->id_kategori }}" {{ request('category_id') == $category->id_kategori ? 'selected' : '' }}>
                                            {{ $category->nama_kategori }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div class="btn-group btn-block">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-filter"></i> Filter
                                    </button>
                                    <a href="{{ route('produk.missing_supplier') }}" class="btn btn-default">
                                        <i class="fa fa-refresh"></i> Reset
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <table id="missing-supplier-table" class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th>Item code</th>
                            <th>Product name</th>
                            <th>Shop</th>
                            <th>Supplier</th>
                            <th>Cost</th>
                            <th>Sell</th>
                            <th>Stock</th>
                            <th>Reorder</th>
                            <th>Why listed</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('AdminLTE-2/bower_components/select2/dist/js/select2.min.js') }}"></script>
<script>
    $(function () {
        const suppliersSelectUrl = @json(route('produk.missing_supplier.suppliers_select'));
        const produkUpdateBaseUrl = @json(url('/produk'));

        function produkUpdateUrl(id) {
            return produkUpdateBaseUrl + '/' + encodeURIComponent(id);
        }

        var table;

        function initMissingSupplierSelect($sel) {
            if ($sel.data('select2')) {
                return;
            }
            var produkId = parseInt($sel.data('produk-id'), 10);
            $sel.select2({
                placeholder: $sel.data('placeholder') || 'Search supplier…',
                allowClear: false,
                width: '100%',
                dropdownParent: $('body'),
                ajax: {
                    url: suppliersSelectUrl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { q: params.term || '' };
                    },
                    processResults: function (data) {
                        return data;
                    }
                },
                minimumInputLength: 0
            });
            $sel.on('select2:select.missingSupplier', function (e) {
                var supplierId = parseInt(e.params.data.id, 10);
                if (!supplierId || !produkId) {
                    return;
                }
                $sel.prop('disabled', true);
                $.ajax({
                    url: produkUpdateUrl(produkId),
                    type: 'POST',
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        _method: 'PUT',
                        inline_fields_only: 1,
                        id_supplier: supplierId
                    }
                }).done(function () {
                    table.ajax.reload(null, false);
                }).fail(function (xhr) {
                    var json = xhr.responseJSON || {};
                    if (xhr.status === 422 && json.merge) {
                        var m = json.merge;
                        var duplicateInfo = '';
                        if (json.duplicate && json.duplicate.supplier_name) {
                            duplicateInfo = '\nExisting supplier: ' + json.duplicate.supplier_name;
                        }
                        var intro = (json.message || 'This item code exists on another product in this shop.') + duplicateInfo;
                        var detail = 'Merge will keep product #' + m.keep_id + ' (' + (m.keep_name || '') + ') and remove incomplete #' + m.remove_id
                            + '. Sales, stock, and supplier records move to the kept product.';
                        if (!confirm(intro + '\n\n' + detail + '\n\nProceed with merge?')) {
                            $sel.val(null).trigger('change');
                            return;
                        }

                        $.ajax({
                            url: '{{ route('produk.merge_incomplete_duplicate') }}',
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                _token: $('meta[name="csrf-token"]').attr('content'),
                                keep_id: m.keep_id,
                                remove_id: m.remove_id
                            }
                        }).done(function(resp) {
                            table.ajax.reload(null, false);
                            alert((resp && resp.message) ? resp.message : 'Merged successfully.');
                        }).fail(function(x2) {
                            var err = 'Merge failed.';
                            if (x2.responseJSON && x2.responseJSON.message) {
                                err = x2.responseJSON.message;
                            }
                            alert(err);
                        }).always(function () {
                            $sel.prop('disabled', false);
                        });
                        return;
                    }

                    var msg = 'Unable to assign supplier.';
                    if (json && json.message) {
                        msg = json.message;
                    }
                    if (xhr.status === 422) {
                        var askDifferentSupplier = confirm(
                            msg + '\n\nDo you want to assign a different supplier for this product?'
                        );
                        $sel.val(null).trigger('change');
                        if (askDifferentSupplier) {
                            setTimeout(function () {
                                if (!$sel.prop('disabled')) {
                                    $sel.select2('open');
                                }
                            }, 120);
                            return;
                        }
                    } else {
                        alert(msg);
                        $sel.val(null).trigger('change');
                    }
                }).always(function () {
                    $sel.prop('disabled', false);
                });
            });
        }

        table = $('#missing-supplier-table').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: {
                url: '{{ route('produk.missing_supplier.data') }}',
                data: function (d) {
                    d.shop_id = $('#shop_id').val();
                    d.category_id = $('#category_id').val();
                }
            },
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', searchable: false, sortable: false },
                { data: 'item_code', name: 'item_code' },
                { data: 'nama_produk', name: 'nama_produk' },
                { data: 'shop_name', name: 'shop_name' },
                { data: 'supplier_select', name: 'supplier_select', searchable: false, sortable: false, className: 'missing-supplier-sel-wrap' },
                { data: 'harga_beli', name: 'harga_beli', className: 'text-right' },
                { data: 'harga_jual', name: 'harga_jual', className: 'text-right' },
                { data: 'stok', name: 'stok', className: 'text-right' },
                { data: 'reorder', name: 'reorder', className: 'text-right' },
                { data: 'reason', name: 'reason', sortable: false },
            ],
            createdRow: function (row) {
                var $sel = $(row).find('.js-missing-supplier-sel');
                if ($sel.length) {
                    initMissingSupplierSelect($sel);
                }
            },
            drawCallback: function (settings) {
                const json = settings.json;
                if (json && json.total_missing_supplier !== undefined) {
                    $('#total-missing-supplier').text(json.total_missing_supplier);
                }
            }
        });

        $('#filterForm').on('submit', function (e) {
            e.preventDefault();
            table.ajax.reload();
        });

        $('#btn-export-missing-supplier-pdf').on('click', function (e) {
            e.preventDefault();
            const params = new URLSearchParams();
            const sid = ($('#shop_id').val() || '').trim();
            const cid = ($('#category_id').val() || '').trim();
            if (sid) {
                params.set('shop_id', sid);
            }
            if (cid) {
                params.set('category_id', cid);
            }
            const q = params.toString();
            window.open('{{ route('produk.missing_supplier.export_pdf') }}' + (q ? '?' + q : ''), '_blank');
        });
    });
</script>
@endpush
