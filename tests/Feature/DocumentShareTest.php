<?php

namespace Tests\Feature;

use App\Mail\DocumentShareMail;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\Sale;
use App\Services\DocumentShareService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\Support\CreatesSellixWorld;
use Tests\TestCase;

class DocumentShareTest extends TestCase
{
    use CreatesSellixWorld;

    protected Customer $client;

    protected Quotation $quotation;

    protected Sale $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSellixWorld();

        $this->client = Customer::query()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'type' => Customer::TYPE_REGULAR,
            'name' => 'Acme Roofing',
            'phone' => '0712345678',
            'email' => 'acme@example.com',
            'is_walk_in' => false,
            'is_active' => true,
        ]);

        $this->quotation = Quotation::query()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->client->id,
            'user_id' => $this->admin->id,
            'number' => 'QT-SHARE-1',
            'quote_date' => now()->toDateString(),
            'status' => Quotation::STATUS_DRAFT,
            'subtotal' => 1500,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 1500,
        ]);

        $this->invoice = Sale::query()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->client->id,
            'user_id' => $this->admin->id,
            'document_type' => Sale::TYPE_INVOICE,
            'number' => 'SL-SHARE-1',
            'invoice_number' => 'INV-SHARE-1',
            'sale_date' => now(),
            'status' => Sale::STATUS_COMPLETED,
            'payment_status' => Sale::PAYMENT_UNPAID,
            'subtotal' => 2500,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 2500,
            'paid_amount' => 0,
            'balance' => 2500,
        ]);
    }

    public function test_quotation_and_invoice_pages_show_whatsapp_and_email_share(): void
    {
        $this->actingAsAdmin()
            ->get(route('quotations.show', $this->quotation))
            ->assertOk()
            ->assertSee('WhatsApp', false)
            ->assertSee('Email', false)
            ->assertSee(route('quotations.share', $this->quotation), false);

        $this->actingAsAdmin()
            ->get(route('sales.show', $this->invoice))
            ->assertOk()
            ->assertSee('WhatsApp', false)
            ->assertSee('Email', false)
            ->assertSee(route('sales.share', $this->invoice), false);
    }

    public function test_share_payload_includes_whatsapp_link_and_customer_contacts(): void
    {
        $quote = $this->actingAsAdmin()
            ->getJson(route('quotations.share', $this->quotation))
            ->assertOk()
            ->json();

        $this->assertSame('Acme Roofing', $quote['customer']);
        $this->assertSame('0712345678', $quote['phone']);
        $this->assertSame('acme@example.com', $quote['email']);
        $this->assertStringContainsString('254712345678', $quote['whatsapp_url']);
        $this->assertStringContainsString('QT-SHARE-1', $quote['message']);
        $this->assertStringContainsString('attached as a PDF', $quote['message']);
        $this->assertStringNotContainsString('/share/', $quote['message']);
        $this->assertStringContainsString('/share/quotations/', $quote['share_url']);

        $invoice = $this->actingAsAdmin()
            ->getJson(route('sales.share', $this->invoice))
            ->assertOk()
            ->json();

        $this->assertStringContainsString('INV-SHARE-1', $invoice['title']);
        $this->assertStringContainsString('/share/invoices/', $invoice['share_url']);
    }

    public function test_signed_share_link_opens_the_pdf_without_login(): void
    {
        $quoteUrl = URL::temporarySignedRoute('share.quotations', now()->addDay(), [
            'quotation' => $this->quotation->id,
        ]);
        $quotePdf = $this->get($quoteUrl)->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $quotePdf->headers->get('content-type'));

        $invoiceUrl = URL::temporarySignedRoute('share.invoices', now()->addDay(), [
            'sale' => $this->invoice->id,
        ]);
        $invoicePdf = $this->get($invoiceUrl)->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $invoicePdf->headers->get('content-type'));

        $this->get(route('share.quotations', $this->quotation))->assertStatus(403);
        $this->get(route('share.invoices', $this->invoice))->assertStatus(403);
    }

    public function test_email_sends_the_document_pdf(): void
    {
        Mail::fake();

        $this->actingAsAdmin()
            ->postJson(route('quotations.share.email', $this->quotation), [
                'email' => 'buyer@example.com',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        Mail::assertSent(DocumentShareMail::class, function (DocumentShareMail $mail) {
            return $mail->hasTo('buyer@example.com')
                && $mail->payload['number'] === 'QT-SHARE-1'
                && $mail->filename !== ''
                && $mail->pdfBinary !== ''
                && strpos($mail->payload['message'], '/share/') === false;
        });

        $this->actingAsAdmin()
            ->postJson(route('sales.share.email', $this->invoice), [
                'email' => 'accounts@example.com',
            ])
            ->assertOk();

        Mail::assertSent(DocumentShareMail::class, function (DocumentShareMail $mail) {
            return $mail->hasTo('accounts@example.com')
                && $mail->payload['number'] === 'INV-SHARE-1'
                && $mail->pdfBinary !== '';
        });
    }

    public function test_whatsapp_number_normalizes_kenyan_mobiles(): void
    {
        $share = app(DocumentShareService::class);
        $this->assertSame('254712345678', $share->whatsappNumber('0712 345 678'));
        $this->assertSame('254712345678', $share->whatsappNumber('+254712345678'));
        $this->assertSame('254712345678', $share->whatsappNumber('712345678'));
    }
}
