@if (!empty($documentHeader))
<div class="document-header-text">
    {!! nl2br(e($documentHeader)) !!}
</div>
@endif

@if (empty($hasDocumentHeaderImage))
    @include('fleet.partials.pdf_company_block')
@endif
