<?php

namespace Tests\Feature;

use App\Mail\SubscriptionInvoiceMail;
use App\Models\SubscriptionInvoice;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesSellixWorld;
use Tests\TestCase;

class SubscriptionBillingTest extends TestCase
{
    use CreatesSellixWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSellixWorld();
    }

    public function test_owner_can_send_invoice_and_tenant_sees_payment_history(): void
    {
        Mail::fake();
        $owner = $this->makeOwner();

        $this->actingAs($owner)
            ->post(route('owner.businesses.invoice', $this->company), [
                'amount' => 2500,
                'due_date' => now()->addDays(7)->toDateString(),
                'email' => 'jane@sunrise.test',
                'notes' => 'September subscription',
            ])
            ->assertRedirect();

        $invoice = SubscriptionInvoice::query()->where('company_id', $this->company->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('jane@sunrise.test', $invoice->sent_to_email);
        Mail::assertSent(SubscriptionInvoiceMail::class);

        $this->actingAsAdmin()
            ->get(route('billing.index'))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('Payment history');

        $this->actingAs($owner)
            ->post(route('owner.businesses.payment', $this->company), [
                'amount' => 2500,
                'method' => 'mpesa',
                'reference' => 'PAY-1',
                'paid_at' => now()->toDateString(),
                'invoice_id' => $invoice->id,
            ])
            ->assertRedirect();

        $this->assertSame(SubscriptionInvoice::STATUS_PAID, $invoice->fresh()->status);

        $this->actingAsAdmin()
            ->get(route('billing.index'))
            ->assertOk()
            ->assertSee('PAY-1')
            ->assertSee('Paid');
    }

    protected function makeOwner()
    {
        $role = \App\Models\Role::query()->withoutGlobalScope('company')->firstOrCreate(
            ['name' => PermissionCatalog::SYSTEM_OWNER, 'company_id' => null],
            ['display_name' => 'System Owner', 'is_system' => true]
        );

        return \App\Models\User::query()->create([
            'company_id' => null,
            'branch_id' => null,
            'role_id' => $role->id,
            'name' => 'Bill Owner',
            'email' => 'bill-owner@test.local',
            'username' => 'billowner',
            'password' => \Illuminate\Support\Facades\Hash::make('secret'),
            'is_active' => true,
        ]);
    }
}
