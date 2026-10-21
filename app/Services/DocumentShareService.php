<?php

namespace App\Services;

use App\Mail\DocumentShareMail;
use App\Models\Quotation;
use App\Models\Sale;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use PDF;

class DocumentShareService
{
    public function quotationPayload(Quotation $quotation): array
    {
        $quotation->loadMissing(['customer', 'company']);

        return $this->payload(
            'quotation',
            'Quotation',
            (string) $quotation->number,
            (float) $quotation->total,
            $quotation->customer,
            $quotation->company,
            URL::temporarySignedRoute('share.quotations', now()->addDays(30), ['quotation' => $quotation->id]),
            route('quotations.pdf', $quotation),
            route('quotations.share.email', $quotation)
        );
    }

    public function invoicePayload(Sale $sale): array
    {
        abort_unless($sale->status === Sale::STATUS_COMPLETED, 404);

        $sale->loadMissing(['customer', 'company']);
        $kindLabel = $sale->isInvoice() ? 'Invoice' : 'Sale';

        return $this->payload(
            'invoice',
            $kindLabel,
            $sale->documentNumber(),
            (float) $sale->total,
            $sale->customer,
            $sale->company,
            URL::temporarySignedRoute('share.invoices', now()->addDays(30), ['sale' => $sale->id]),
            route('sales.pdf', $sale),
            route('sales.share.email', $sale)
        );
    }

    public function quotationPdf(Quotation $quotation)
    {
        $this->bindCompany((int) $quotation->company_id);
        $quotation->loadMissing(['customer', 'user', 'items.product.unit', 'items.variant', 'company', 'branch']);
        $profile = fleet_company_profile();
        $filename = 'quotation-' . preg_replace('/[^A-Za-z0-9\-]+/', '-', (string) $quotation->number) . '.pdf';

        $pdf = PDF::loadView('quotations.pdf', [
            'quotation' => $quotation,
            'profile' => $profile,
            'logo_pdf_path' => $profile['logo_pdf_path'] ?? null,
            'companyName' => $profile['companyName'] ?? fleet_system_name(),
        ])->setPaper('a4', 'portrait');

        return [$pdf, $filename];
    }

    public function invoicePdf(Sale $sale)
    {
        abort_unless($sale->status === Sale::STATUS_COMPLETED, 404);

        $this->bindCompany((int) $sale->company_id);
        $sale->loadMissing(['items.product.unit', 'items.variant', 'customer', 'cashier', 'branch', 'payments', 'company']);
        $profile = fleet_company_profile();
        $filename = 'sale-' . preg_replace('/[^A-Za-z0-9\-]+/', '-', $sale->documentNumber()) . '.pdf';

        $pdf = PDF::loadView('sales.pdf', [
            'sale' => $sale,
            'profile' => $profile,
            'logo_pdf_path' => $profile['logo_pdf_path'] ?? null,
            'paymentLabels' => config('sellix.payment_methods', []),
            'companyName' => $profile['companyName'] ?? fleet_system_name(),
        ])->setPaper('a4', 'portrait');

        return [$pdf, $filename];
    }

    public function sendQuotationEmail(Quotation $quotation, string $email): void
    {
        $payload = $this->quotationPayload($quotation);
        [$pdf, $filename] = $this->quotationPdf($quotation);
        $this->send($email, $payload, $pdf->output(), $filename);
    }

    public function sendInvoiceEmail(Sale $sale, string $email): void
    {
        $payload = $this->invoicePayload($sale);
        [$pdf, $filename] = $this->invoicePdf($sale);
        $this->send($email, $payload, $pdf->output(), $filename);
    }

    public function whatsappNumber(?string $raw): string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);
        if ($digits === '') {
            return '';
        }

        if (strpos($digits, '254') === 0 && strlen($digits) >= 12) {
            return $digits;
        }

        if (strpos($digits, '0') === 0 && strlen($digits) === 10) {
            return '254' . substr($digits, 1);
        }

        if (strlen($digits) === 9 && strpos($digits, '7') === 0) {
            return '254' . $digits;
        }

        return $digits;
    }

    private function send(string $email, array $payload, string $pdfBinary, string $filename): void
    {
        Mail::to($email)->send(new DocumentShareMail($payload, $pdfBinary, $filename));
    }

    private function payload(
        string $kind,
        string $kindLabel,
        string $number,
        float $total,
        $customer,
        $company,
        string $shareUrl,
        string $pdfUrl,
        string $emailUrl
    ): array {
        $customerName = '';
        $phone = '';
        $email = '';
        if ($customer && empty($customer->is_walk_in)) {
            $customerName = trim((string) $customer->name);
            $phone = trim((string) ($customer->mobile ?: $customer->phone));
            $email = trim((string) $customer->email);
        }

        $companyName = trim((string) optional($company)->name) ?: fleet_system_name();
        $amount = 'Ksh ' . number_format($total, 2);
        $greeting = $customerName !== '' ? $customerName : 'Customer';
        $message = "Hello {$greeting},\n\n"
            . "Please find your {$kindLabel} {$number} from {$companyName}.\n"
            . "Total: {$amount}\n\n"
            . "View or download: {$shareUrl}\n\n"
            . 'Thank you.';

        $whatsapp = $this->whatsappNumber($phone);
        $whatsappUrl = 'https://wa.me/' . ($whatsapp !== '' ? $whatsapp : '') . '?text=' . rawurlencode($message);
        $mailto = 'mailto:' . rawurlencode($email) . '?subject=' . rawurlencode($kindLabel . ' ' . $number)
            . '&body=' . rawurlencode($message);

        return [
            'kind' => $kind,
            'title' => $kindLabel . ' ' . $number,
            'number' => $number,
            'customer' => $customerName,
            'phone' => $phone,
            'email' => $email,
            'message' => $message,
            'share_url' => $shareUrl,
            'pdf_url' => $pdfUrl,
            'email_url' => $emailUrl,
            'whatsapp_url' => $whatsappUrl,
            'mailto' => $mailto,
            'filename' => strtolower($kindLabel) . '-' . preg_replace('/[^A-Za-z0-9\-]+/', '-', $number) . '.pdf',
        ];
    }

    private function bindCompany(int $companyId): void
    {
        if ($companyId > 0) {
            app()->instance('currentCompanyId', $companyId);
        }
    }
}
