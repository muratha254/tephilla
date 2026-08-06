@if (!empty($logo_pdf_path))
    <img src="{{ $logo_pdf_path }}" alt="{{ $companyName }}" style="max-height: 52px; max-width: 180px; margin-bottom: 8px;">
@endif
<div class="company-name">{{ $companyName }}</div>
<div class="company-meta">
    {{ $companyAddress }}<br>
    @if (!empty($companyTaxPin))
        Tax / PIN: {{ $companyTaxPin }}<br>
    @endif
    @if (($companyPhone ?? '-') !== '-')
        Phone: {{ $companyPhone }}<br>
    @endif
    @if (($companyEmail ?? '-') !== '-')
        Email: {{ $companyEmail }}<br>
    @endif
    @if (!empty($companyWebsite))
        Web: {{ $companyWebsite }}
    @endif
</div>
