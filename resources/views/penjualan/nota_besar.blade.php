<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>PDF Notes</title>

    <style>
        table td {
            /* font-family: Arial, Helvetica, sans-serif; */
            font-size: 14px;
        }
        table.data td,
        table.data th {
            border: 1px solid #ccc;
            padding: 5px;
        }
        table.data {
            border-collapse: collapse;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
    </style>
</head>
<body>
    <table width="100%">
        <tr>
            <td rowspan="4" width="60%">
                <img src="{{ public_path($setting->path_logo) }}" alt="{{ $setting->path_logo }}" width="120">
                <br>
                {{ $setting->alamat }}
                <br>
                Tel: 0722205028
                <br>
                E-mail: info@utamaduni.co.ke
                <br>
                PIN NO: P000605273Y
                <br>
                <br>
            </td>
            <td>Date</td>
            <td>: {{ tanggal_indonesia(date('Y-m-d')) }}</td>
        </tr>
        <tr>
            <td>Member Code</td>
            <td>: {{ $penjualan->member->kode_member ?? '' }}</td>
        </tr>
    </table>

    <div>
        <p>{{ date('d-m-Y') }}</p>
    </div>
    <p>Receipt No: {{ $penjualan->receiptno }}</p>
    <p>Transaction No: {{ tambah_nol_didepan($penjualan->id_penjualan, 10) }}</p>
    <p class="text-center">===================================</p>

    @php $hasItemDiscount = $detail->contains(fn($i) => ($i->diskon ?? 0) > 0); @endphp
    <table class="data" width="100%">
        <thead>
            <tr>
                <th>#</th>
                <th>Code</th>
                <th>Name</th>
                <th>Cost</th>
                <th>Quantity</th>
                @if($hasItemDiscount)<th>Discount</th>@endif
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($detail as $key => $item)
                <tr>
                    <td class="text-center">{{ $key+1 }}</td>
                    <td>{{ optional($item->produk)->nama_produk ?? ('[Missing #'.(int)($item->id_produk ?? 0)).']') }}</td>
                    <td>{{ optional($item->produk)->kode_produk ?? '—' }}</td>
                    <td class="text-right">{{ format_uang($item->harga_jual) }}</td>
                    <td class="text-right">{{ format_uang($item->jumlah) }}</td>
                    @if($hasItemDiscount)<td class="text-right">{{ $item->diskon }}%</td>@endif
                    <td class="text-right">{{ format_uang($item->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            @php $colspan = $hasItemDiscount ? 6 : 5; @endphp
            <tr>
                <td colspan="{{ $colspan }}" class="text-right"><b>Total Price</b></td>
                <td class="text-right"><b>{{ format_uang($penjualan->total_harga) }}</b></td>
            </tr>
            @if(($penjualan->diskon ?? 0) > 0 || ($penjualan->discount_amount ?? 0) > 0)
            @php
                $vatAmountCalc = $penjualan->total_harga * (16/116);
                $subTotalCalc = $penjualan->total_harga - $vatAmountCalc;
                $discountType = $penjualan->discount_type ?? 'percentage';
                if ($discountType === 'fixed' && ($penjualan->discount_amount ?? 0) > 0) {
                    $discountAmountCalc = $penjualan->discount_amount;
                    $discountLabelCalc = 'Discount';
                } else {
                    $totalPayableCalc = $subTotalCalc + $vatAmountCalc;
                    $discountAmountCalc = round($totalPayableCalc * ($penjualan->diskon / 100), 0);
                    $discountLabelCalc = 'Discount (' . $penjualan->diskon . '%)';
                }
            @endphp
            <tr>
                <td colspan="{{ $colspan }}" class="text-right"><b>{{ $discountLabelCalc }}</b></td>
                <td class="text-right"><b>-{{ format_uang($discountAmountCalc) }}</b></td>
            </tr>
            @endif
            <tr>
                <td colspan="{{ $colspan }}" class="text-right"><b>Total Pay</b></td>
                <td class="text-right"><b>{{ format_uang($penjualan->bayar) }}</b></td>
            </tr>
            <tr>
                <td colspan="{{ $colspan }}" class="text-right"><b>Received</b></td>
                <td class="text-right"><b>{{ format_uang($penjualan->diterima) }}</b></td>
            </tr>
            <tr>
                <td colspan="{{ $colspan }}" class="text-right"><b>Return</b></td>
                <td class="text-right"><b>{{ format_uang($penjualan->diterima - $penjualan->bayar) }}</b></td>
            </tr>
        </tfoot>
    </table>

    <table width="100%">
        <tr>
            <td colspan="2" class="text-center" style="padding-bottom: 10px;">
                <b>Served by {{ ucwords(strtolower($penjualan->user->name ?? auth()->user()->name)) }}</b>
            </td>
        </tr>
        <tr>
            <td><b>Thank you for shopping. We hope to see you again!</b></td>
            <td class="text-center">
                Cashier
                <br>
                <br>
                {{ auth()->user()->name }}
            </td>
        </tr>
    </table>
</body>
</html>