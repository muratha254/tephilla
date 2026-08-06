@php
    $contentStyles = ['padding-left:12mm', 'padding-right:12mm'];

    if (! empty($documentHeaderImageHeightMm)) {
        $contentStyles[] = 'margin-top:' . ($documentHeaderImageHeightMm + 2) . 'mm';
    } else {
        $contentStyles[] = 'margin-top:4mm';
    }

    if (! empty($documentFooterImageHeightMm)) {
        $contentStyles[] = 'margin-bottom:' . ($documentFooterImageHeightMm + 2) . 'mm';
    } elseif (! empty($hasDocumentFooterImage)) {
        $contentStyles[] = 'margin-bottom:30mm';
    } elseif (
        empty($hasDocumentFooterImage)
        && (! empty($documentFooter) || ! empty($invoiceFooter) || (! empty($showGeneratedLine) && ! empty($generatedAt)))
    ) {
        $contentStyles[] = 'margin-bottom:12mm';
    }
@endphp
<div class="pdf-content" style="{{ implode(';', $contentStyles) }}">
