@extends('layouts.master')

@section('title')
    Find sale to edit
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Find sale to edit</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-search"></i> Initiate edit by receipt</h3>
                    <div class="box-tools">
                        <a href="{{ route('transaksi.baru') }}" class="btn btn-primary btn-flat"><i class="fa fa-plus-circle"></i> New Transaction</a>
                        <a href="{{ route('penjualan.initiated_edits') }}" class="btn btn-default btn-flat"><i class="fa fa-list"></i> Initiated Sale Edits</a>
                    </div>
                </div>
                <div class="box-body">
                    @if($errors->any())
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            {{ $errors->first() }}
                        </div>
                    @endif
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            {{ session('success') }}
                        </div>
                    @endif

                    <p class="text-muted">
                        Enter the <strong>receipt number</strong> of a completed sale <strong>you created</strong>. Line items will appear below; then click
                        <strong>Start editing in POS</strong> to open the sale for changes and complete again.
                    </p>

                    <div class="row" style="margin-bottom: 15px;">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="receiptno">Receipt number</label>
                                <input type="text" class="form-control" id="receiptno" placeholder="e.g. U001" autocomplete="off">
                            </div>
                            <button type="button" class="btn btn-info btn-flat" id="btn-lookup">
                                <i class="fa fa-search"></i> Look up sale
                            </button>
                            <span id="lookup-spinner" class="hide text-muted" style="margin-left: 10px;"><i class="fa fa-spinner fa-spin"></i> Searching…</span>
                        </div>
                    </div>

                    <div id="lookup-alert" class="hide"></div>

                    <div id="preview-wrap" class="hide" style="margin-top: 20px;">
                        <h4><i class="fa fa-shopping-cart"></i> Sale preview</h4>
                        <p class="text-muted small" id="preview-meta"></p>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="preview-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Shop</th>
                                        <th>Item</th>
                                        <th>Qty</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody id="preview-tbody"></tbody>
                            </table>
                        </div>

                        <form action="{{ route('transaksi.initiate_edit_start') }}" method="post" id="form-start">
                            @csrf
                            <input type="hidden" name="id_penjualan" id="hidden_id_penjualan" value="">
                            <button type="submit" class="btn btn-success btn-lg btn-flat" id="btn-start">
                                <i class="fa fa-play"></i> Start editing in POS
                            </button>
                            <a href="{{ route('transaksi.baru') }}" class="btn btn-default btn-lg btn-flat" title="Return to POS without starting an edit" style="margin-left: 8px;">
                                <i class="fa fa-arrow-left"></i> Cancel — back to POS
                            </a>
                        </form>
                        <p class="help-block text-warning" style="margin-top: 10px;">
                            <i class="fa fa-warning"></i> This reverses the original payment and restores stock so you can change the sale safely. Then complete the transaction as usual.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var token = $('meta[name="csrf-token"]').attr('content');
    var lookupUrl = @json(route('transaksi.initiate_edit_lookup'));

    function showAlert(type, html) {
        var $a = $('#lookup-alert');
        $a.removeClass('hide alert-success alert-danger alert-info alert-warning')
          .addClass('alert alert-' + type)
          .html(html);
    }

    function formatMoney(n) {
        return 'Ksh ' + Number(n).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    $('#btn-lookup').on('click', function () {
        var receiptno = ($('#receiptno').val() || '').trim();
        if (!receiptno) {
            showAlert('danger', 'Enter a receipt number.');
            return;
        }
        $('#lookup-spinner').removeClass('hide');
        $('#preview-wrap').addClass('hide');
        $('#lookup-alert').addClass('hide');

        $.ajax({
            url: lookupUrl,
            method: 'POST',
            data: { _token: token, receiptno: receiptno },
            dataType: 'json'
        }).done(function (data) {
            $('#lookup-spinner').addClass('hide');
            if (!data || !data.ok) {
                showAlert('danger', (data && data.message) ? data.message : 'Lookup failed.');
                return;
            }

            if (data.already_initiated) {
                showAlert('info', (data.message || 'Already waiting for edit.') +
                    ' <a class="btn btn-sm btn-success" href="' + (data.resume_url || '#') + '">Open POS</a>');
                $('#preview-wrap').addClass('hide');
                return;
            }

            showAlert('success', 'Sale found. Review items below, then start editing.');
            $('#hidden_id_penjualan').val(data.id_penjualan);
            $('#preview-meta').text(
                'Receipt: ' + (data.receiptno || '') +
                ' · Date: ' + (data.saledate || '') +
                ' · Total pay: ' + formatMoney(data.total_pay || 0) +
                ' · Items: ' + (data.total_item || 0)
            );

            var tbody = $('#preview-tbody').empty();
            (data.items || []).forEach(function (row, i) {
                tbody.append(
                    '<tr>' +
                    '<td>' + (i + 1) + '</td>' +
                    '<td>' + $('<div/>').text(row.shop || '').html() + '</td>' +
                    '<td>' + $('<div/>').text(row.nama_produk || '').html() +
                        (row.kode_produk ? ' <small class="text-muted">(' + $('<div/>').text(row.kode_produk).html() + ')</small>' : '') +
                    '</td>' +
                    '<td>' + row.qty + '</td>' +
                    '<td>' + formatMoney(row.subtotal) + '</td>' +
                    '</tr>'
                );
            });

            $('#preview-wrap').removeClass('hide');
        }).fail(function (xhr) {
            $('#lookup-spinner').addClass('hide');
            var msg = 'Unable to look up receipt.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                var k = Object.keys(xhr.responseJSON.errors)[0];
                msg = xhr.responseJSON.errors[k][0];
            }
            showAlert('danger', msg);
        });
    });

    $('#receiptno').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $('#btn-lookup').click();
        }
    });
})();
</script>
@endpush
