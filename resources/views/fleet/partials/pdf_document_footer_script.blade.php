@if (! empty($documentFooterImagePdfPath) && ! empty($documentFooterImageHeightPt))
<script type="text/php">
if (isset($pdf)) {
    $pdf->page_script('
        if ($PAGE_NUM == 1) {
            $pageWidth = $pdf->get_width();
            $pageHeight = $pdf->get_height();
            $imgHeight = {{ $documentFooterImageHeightPt }};
            $pdf->image({!! json_encode(str_replace('\\', '/', $documentFooterImagePdfPath)) !!}, 0, $pageHeight - $imgHeight, $pageWidth, $imgHeight);
        }
    ');
}
</script>
@endif
