<?php

namespace Tests\Feature;

use App\Models\Quotation;
use Tests\Support\CreatesSellixWorld;
use Tests\TestCase;

class DocumentNoteTest extends TestCase
{
    use CreatesSellixWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSellixWorld();
    }

    public function test_company_profile_saves_quotation_and_invoice_notes(): void
    {
        $this->actingAsAdmin()->put(route('settings.company.update'), [
            'tab' => 'documents',
            'quotation_terms' => "Note:\n1. Roofing is rust proof.",
            'invoice_note' => "Note:\n1. Pay Tephillah Roofing Products Ltd.",
        ])->assertRedirect(route('settings.company', ['tab' => 'documents']));

        $this->company->refresh();
        $this->assertSame("Note:\n1. Roofing is rust proof.", $this->company->quotation_terms);
        $this->assertSame("Note:\n1. Pay Tephillah Roofing Products Ltd.", $this->company->invoice_note);
    }

    public function test_new_quotation_and_invoice_are_prefilled_with_company_notes(): void
    {
        $this->company->update([
            'quotation_terms' => 'Quote bank: UBA 55010160031479',
            'invoice_note' => 'Invoice paybill 4320207',
        ]);

        $this->actingAsAdmin()
            ->get(route('quotations.create'))
            ->assertOk()
            ->assertSee('Quote bank: UBA 55010160031479', false);

        $this->actingAsAdmin()
            ->get(route('sales.invoices.create'))
            ->assertOk()
            ->assertSee('Invoice paybill 4320207', false);
    }

    public function test_invoice_uses_quotation_note_when_its_own_note_is_blank(): void
    {
        $this->company->update([
            'quotation_terms' => 'Shared payment details',
            'invoice_note' => '',
        ]);

        $this->assertSame('Shared payment details', $this->company->fresh()->documentNote('invoice'));
        $this->assertSame(Quotation::DEFAULT_TERMS, (new \App\Models\Company(['quotation_terms' => null]))->documentNote('quotation'));
    }
}
