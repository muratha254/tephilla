<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
    <h4 class="modal-title">Service Fee Details - Receipt No: {{ $penjualan->receiptno }}</h4>
</div>
<div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
    <div class="row">
        <div class="col-md-6">
            <table class="table table-bordered">
                <tr>
                    <th width="40%">Receipt No:</th>
                    <td>{{ $penjualan->receiptno }}</td>
                </tr>
                <tr>
                    <th>Date:</th>
                    <td>{{ $penjualan->saledate ? date('Y-m-d', strtotime($penjualan->saledate)) : tanggal_indonesia($penjualan->created_at, false) }}</td>
                </tr>
                <tr>
                    <th>Cashier:</th>
                    <td>{{ $penjualan->user->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Total Items:</th>
                    <td><strong>{{ $penjualan->total_item }} items</strong></td>
                </tr>
            </table>
        </div>
        <div class="col-md-6">
            <table class="table table-bordered">
                <tr>
                    <th width="40%">Subtotal (Before VAT):</th>
                    <td><strong>Ksh {{ format_uang($subtotalBeforeVat) }}</strong></td>
                </tr>
                <tr>
                    <th>VAT (16%):</th>
                    <td>Ksh {{ format_uang($penjualan->tax ?? 0) }}</td>
                </tr>
                <tr>
                    <th>Total Payable:</th>
                    <td><strong>Ksh {{ format_uang($penjualan->bayar) }}</strong></td>
                </tr>
                <tr>
                    <th>Commission Rate:</th>
                    <td><strong>{{ $commissionRate }}%</strong></td>
                </tr>
                <tr>
                    <th>Service Fee:</th>
                    <td><strong style="color: #28a745; font-size: 16px;">Ksh {{ format_uang($driverCommission) }}</strong></td>
                </tr>
            </table>
        </div>
    </div>

    <h4>Items Purchased:</h4>
    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>#</th>
                <th>Product Code</th>
                <th>Product Name</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalQty = 0;
            @endphp
            @foreach($details as $index => $detail)
                @php
                    $totalQty += $detail->jumlah;
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $detail->produk->kode_produk ?? 'N/A' }}</td>
                    <td>{{ $detail->produk->nama_produk ?? 'N/A' }}</td>
                    <td class="text-right">{{ format_uang($detail->jumlah) }}</td>
                    <td class="text-right">Ksh {{ format_uang($detail->harga_jual) }}</td>
                    <td class="text-right">Ksh {{ format_uang($detail->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3" class="text-right">Total:</th>
                <th class="text-right">{{ format_uang($totalQty) }} items</th>
                <th></th>
                <th class="text-right">Ksh {{ format_uang($penjualan->total_harga) }}</th>
            </tr>
        </tfoot>
    </table>
</div>
<div class="modal-footer">
    <a href="{{ route('driver-commission.export-pdf', $penjualan->id_penjualan) }}" target="_blank" class="btn btn-warning btn-flat">
        <i class="fa fa-file-pdf-o"></i> Export PDF
    </a>
    <button type="button" class="btn btn-default btn-flat" data-dismiss="modal">Close</button>
</div>




