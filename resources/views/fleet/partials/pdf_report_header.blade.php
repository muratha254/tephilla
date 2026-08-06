<div class="pdf-report-header">
    @if (!empty($logo_pdf_path))
        <img src="{{ $logo_pdf_path }}" alt="{{ $companyName }}" style="max-height: 48px; max-width: 160px; margin-bottom: 6px;">
    @endif
    <h1>{{ $companyName }}</h1>
    @if (($companyAddress ?? '-') !== '-')
        <p>{{ $companyAddress }}</p>
    @endif
    @if (!empty($companyTaxPin))
        <p>Tax / PIN: {{ $companyTaxPin }}</p>
    @endif
    <p>
        @if (($companyPhone ?? '-') !== '-')
            Phone: {{ $companyPhone }}
        @endif
        @if (($companyEmail ?? '-') !== '-')
            @if (($companyPhone ?? '-') !== '-') &bull; @endif
            Email: {{ $companyEmail }}
        @endif
    </p>
</div>
