@if (!empty($documentHeaderImagePdfPath) || !empty($documentHeaderImageUrl))
    @php
        $headerImageSrc = $documentHeaderImagePdfPath ?? $documentHeaderImageUrl;
    @endphp
    <div class="pdf-chrome-header">
        <img src="{{ $headerImageSrc }}" class="document-header-image" alt="">
    </div>
@endif

@php
    $hasFooterImage = ! empty($documentFooterImagePdfPath) || ! empty($documentFooterImageUrl);
    $showFooterText = ! $hasFooterImage
        && (! empty($documentFooter) || ! empty($invoiceFooter) || (! empty($showGeneratedLine) && ! empty($generatedAt)));
@endphp

@if ($hasFooterImage)
    @php
        $footerImageSrc = $documentFooterImagePdfPath ?? $documentFooterImageUrl;
    @endphp
    <div class="pdf-chrome-footer">
        <img src="{{ $footerImageSrc }}" class="document-footer-image" alt="">
    </div>
@elseif ($showFooterText)
    <div class="pdf-chrome-footer">
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
    </div>
@endif
