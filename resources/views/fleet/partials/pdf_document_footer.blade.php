@php
    $hasFooterImage = ! empty($documentFooterImagePdfPath) || ! empty($documentFooterImageUrl);
    $showFooterText = ! $hasFooterImage
        && (! empty($documentFooter) || ! empty($invoiceFooter) || (! empty($showGeneratedLine) && ! empty($generatedAt)));
@endphp

@if ($hasFooterImage)
    @php
        $footerImageSrc = $documentFooterImagePdfPath ?? $documentFooterImageUrl;
    @endphp
    <img src="{{ $footerImageSrc }}" class="document-footer-image" alt="">
@elseif ($showFooterText)
    <div class="document-edge-footer-text">
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
