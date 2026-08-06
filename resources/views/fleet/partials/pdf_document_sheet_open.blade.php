@php
    $hasFooterImage = ! empty($documentFooterImagePdfPath) || ! empty($documentFooterImageUrl);
    $showFooterText = ! $hasFooterImage
        && (! empty($documentFooter) || ! empty($invoiceFooter) || (! empty($showGeneratedLine) && ! empty($generatedAt)));

    $bodyStyles = [];
    if (! empty($documentHeaderImageHeightMm)) {
        $bodyStyles[] = 'padding-top:' . ($documentHeaderImageHeightMm + 2) . 'mm';
    } else {
        $bodyStyles[] = 'padding-top:4mm';
    }
    if (! empty($documentFooterImageHeightMm)) {
        $bodyStyles[] = 'padding-bottom:' . ($documentFooterImageHeightMm + 2) . 'mm';
    } elseif ($showFooterText) {
        $bodyStyles[] = 'padding-bottom:12mm';
    }
@endphp
<div class="pdf-sheet">
    @if (! empty($documentHeaderImagePdfPath) || ! empty($documentHeaderImageUrl))
        @php $headerImageSrc = $documentHeaderImagePdfPath ?? $documentHeaderImageUrl; @endphp
        <div class="pdf-sheet-header">
            <img src="{{ $headerImageSrc }}" class="document-header-image" alt="">
        </div>
    @endif

    @if ($showFooterText)
        <div class="pdf-sheet-footer">
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

    <div class="pdf-sheet-body"@if ($bodyStyles) style="{{ implode(';', $bodyStyles) }}"@endif>
