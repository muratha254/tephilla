@if (!empty($documentHeaderImagePdfPath) || !empty($documentHeaderImageUrl))
    @php
        $headerImageSrc = $documentHeaderImagePdfPath ?? $documentHeaderImageUrl;
    @endphp
    <img src="{{ $headerImageSrc }}" class="document-header-image" alt="">
@endif
