</div>
@php
    $hasFooterImage = ! empty($documentFooterImagePdfPath) || ! empty($documentFooterImageUrl);
    $showFooterText = ! $hasFooterImage
        && (! empty($documentFooter) || ! empty($invoiceFooter) || (! empty($showGeneratedLine) && ! empty($generatedAt)));
@endphp
@if ($hasFooterImage)
    @php
        $footerImageSrc = $documentFooterImagePdfPath ?? $documentFooterImageUrl;
        $footerImageStyle = ! empty($documentFooterImageHeightMm)
            ? 'width:210mm;height:' . $documentFooterImageHeightMm . 'mm;'
            : 'width:210mm;';
    @endphp
    <div class="pdf-page-footer">
        <img src="{{ $footerImageSrc }}" alt="" style="{{ $footerImageStyle }}">
    </div>
@elseif ($showFooterText)
    <div class="pdf-page-footer document-edge-footer-text">
        @if (! empty($documentFooter))
            {!! nl2br(e($documentFooter)) !!}
        @elseif (! empty($invoiceFooter))
            {!! nl2br(e($invoiceFooter)) !!}
        @endif
        @if (! empty($showGeneratedLine) && ! empty($generatedAt))
            <div class="footer-generated">Generated on {{ $generatedAt }} | {{ $companyName }}</div>
        @endif
    </div>
@endif
