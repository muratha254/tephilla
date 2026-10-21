<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Models\Sale;
use App\Services\DocumentShareService;
use Illuminate\Http\Request;

class DocumentShareController extends Controller
{
    public function quotation(Quotation $quotation, DocumentShareService $share)
    {
        $this->authorizePermission('quotations.view');

        return response()->json($share->quotationPayload($quotation));
    }

    public function invoice(Sale $sale, DocumentShareService $share)
    {
        $this->authorizePermission('sales.view');

        return response()->json($share->invoicePayload($sale));
    }

    public function emailQuotation(Request $request, Quotation $quotation, DocumentShareService $share)
    {
        $this->authorizePermission('quotations.view');
        $data = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        try {
            $share->sendQuotationEmail($quotation, $data['email']);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Could not send email. Use Email app or check mail settings.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Quotation emailed to ' . $data['email'] . '.',
        ]);
    }

    public function emailInvoice(Request $request, Sale $sale, DocumentShareService $share)
    {
        $this->authorizePermission('sales.view');
        $data = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        try {
            $share->sendInvoiceEmail($sale, $data['email']);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Could not send email. Use Email app or check mail settings.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Invoice emailed to ' . $data['email'] . '.',
        ]);
    }

    public function publicQuotation(Quotation $quotation, DocumentShareService $share)
    {
        [$pdf, $filename] = $share->quotationPdf($quotation);

        return $pdf->stream($filename);
    }

    public function publicInvoice(Sale $sale, DocumentShareService $share)
    {
        [$pdf, $filename] = $share->invoicePdf($sale);

        return $pdf->stream($filename);
    }
}
