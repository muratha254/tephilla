        </td>
    </tr>
    @php
        $hasPdfFooter = ! empty($hasDocumentFooterImage)
            || ! empty($documentFooter)
            || ! empty($invoiceFooter)
            || (! empty($showGeneratedLine) && ! empty($generatedAt));
    @endphp
    @if ($hasPdfFooter)
    <tr class="pdf-page-footer-row">
        <td class="pdf-page-footer-cell">
            @include('fleet.partials.pdf_document_footer')
        </td>
    </tr>
    @endif
</table>
