<div class="sx-sticker-sheet">
    @foreach($products as $product)
        @php
            $copies = (int) ($quantities[$product->id] ?? 1);
            $code = preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($product->barcode ?: $product->sku ?: ''));
            if ($code === '') {
                $code = 'ITM' . str_pad((string) $product->id, 6, '0', STR_PAD_LEFT);
            }
        @endphp
        @for($i = 0; $i < $copies; $i++)
            <div class="sx-sticker">
                <div class="sx-sticker-company">{{ $companyName }}</div>
                <div class="sx-sticker-barcode">
                    @php
                        try {
                            $barcodeHtml = \DNS1D::getBarcodeHTML($code, 'C128', 2, 50);
                        } catch (\Throwable $e) {
                            $barcodeHtml = '<div class="sx-sticker-code">' . e($code) . '</div>';
                        }
                    @endphp
                    {!! $barcodeHtml !!}
                </div>
                <div class="sx-sticker-name">{{ $product->name }}</div>
                <div class="sx-sticker-price">Price: Ksh {{ number_format((float) $product->selling_price, 2) }}</div>
            </div>
        @endfor
    @endforeach
</div>
