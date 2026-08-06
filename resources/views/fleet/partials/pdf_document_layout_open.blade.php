@php
    $hasFooterImage = ! empty($documentFooterImagePdfPath) || ! empty($documentFooterImageUrl);
    $showFooterText = ! $hasFooterImage
        && (! empty($documentFooter) || ! empty($invoiceFooter) || (! empty($showGeneratedLine) && ! empty($generatedAt)));

    $bodyStyles = ['padding-left:12mm', 'padding-right:12mm'];
    if (empty($documentHeaderImagePdfPath) && empty($documentHeaderImageUrl)) {
        $bodyStyles[] = 'padding-top:4mm';
    }
    if (! empty($documentFooterImageHeightMm)) {
        $bodyStyles[] = 'padding-bottom:' . ($documentFooterImageHeightMm + 2) . 'mm';
    } elseif ($showFooterText) {
        $bodyStyles[] = 'padding-bottom:12mm';
    }
@endphp
@if (! empty($documentHeaderImagePdfPath) || ! empty($documentHeaderImageUrl))
    @php $headerImageSrc = $documentHeaderImagePdfPath ?? $documentHeaderImageUrl; @endphp
    <img src="{{ $headerImageSrc }}" class="document-header-image" alt="">
@endif
<div class="pdf-page-body" style="{{ implode(';', $bodyStyles) }}">
