@php
    $bodyStyles = [];
    if (! empty($documentHeaderImageHeightMm)) {
        $bodyStyles[] = 'padding-top: ' . ($documentHeaderImageHeightMm + 2) . 'mm';
    }

    $footerReserveMm = 0;
    if (! empty($documentFooterImageHeightMm)) {
        $footerReserveMm += $documentFooterImageHeightMm + 2;
    }
    $showFooterText = (! empty($documentFooter) || ! empty($invoiceFooter) || (! empty($showGeneratedLine) && ! empty($generatedAt)))
        && (empty($hasDocumentFooterImage) || ! empty($documentFooterIsCustom));
    if ($showFooterText) {
        $footerReserveMm += 8;
    }
    if ($footerReserveMm > 0) {
        $bodyStyles[] = 'padding-bottom: ' . $footerReserveMm . 'mm';
    }
@endphp
<div class="document-body"@if ($bodyStyles) style="{{ implode('; ', $bodyStyles) }}"@endif>
