@php
    $paths = $letterheads ?? $site ?? [];
@endphp
<p class="text-muted">Upload a heading and a footer for quotations and invoices. They print across the top and bottom of the PDF and the printed page. JPG or PNG, up to 5 MB. An invoice uses the quotation images when its own images are not uploaded.</p>
<div class="row">
    <div class="col-md-6">
        <h4>Quotation</h4>
        <div class="form-group">
            <label>Heading image</label>
            <input type="file" name="quotation_header" class="form-control" accept="image/*">
            @if(!empty($paths['quotation_header_path']))
                <div style="margin-top:8px;"><img src="{{ asset('storage/' . $paths['quotation_header_path']) }}" alt="Quotation heading" style="max-width:100%;max-height:90px;"></div>
                <label class="checkbox-inline"><input type="checkbox" name="remove_quotation_header" value="1"> Remove</label>
            @endif
        </div>
        <div class="form-group">
            <label>Footer image</label>
            <input type="file" name="quotation_footer" class="form-control" accept="image/*">
            @if(!empty($paths['quotation_footer_path']))
                <div style="margin-top:8px;"><img src="{{ asset('storage/' . $paths['quotation_footer_path']) }}" alt="Quotation footer" style="max-width:100%;max-height:90px;"></div>
                <label class="checkbox-inline"><input type="checkbox" name="remove_quotation_footer" value="1"> Remove</label>
            @endif
        </div>
    </div>
    <div class="col-md-6">
        <h4>Invoice</h4>
        <div class="form-group">
            <label>Heading image</label>
            <input type="file" name="invoice_header" class="form-control" accept="image/*">
            @if(!empty($paths['invoice_header_path']))
                <div style="margin-top:8px;"><img src="{{ asset('storage/' . $paths['invoice_header_path']) }}" alt="Invoice heading" style="max-width:100%;max-height:90px;"></div>
                <label class="checkbox-inline"><input type="checkbox" name="remove_invoice_header" value="1"> Remove</label>
            @endif
        </div>
        <div class="form-group">
            <label>Footer image</label>
            <input type="file" name="invoice_footer" class="form-control" accept="image/*">
            @if(!empty($paths['invoice_footer_path']))
                <div style="margin-top:8px;"><img src="{{ asset('storage/' . $paths['invoice_footer_path']) }}" alt="Invoice footer" style="max-width:100%;max-height:90px;"></div>
                <label class="checkbox-inline"><input type="checkbox" name="remove_invoice_footer" value="1"> Remove</label>
            @endif
        </div>
    </div>
</div>
