<?php

namespace Tests\Feature;

use Tests\Support\CreatesSellixWorld;
use Tests\TestCase;

class PurchaseMenuTest extends TestCase
{
    use CreatesSellixWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSellixWorld();
    }

    public function test_purchases_menu_lists_the_purchase_screens(): void
    {
        $this->actingAsAdmin()
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Items/Products')
            ->assertSee('Products')
            ->assertSee('Colours')
            ->assertSee('Categories')
            ->assertSee('Units')
            ->assertSee('Inventory')
            ->assertSee('Opening stock')
            ->assertSee('Stock ledger')
            ->assertSee('Folding')
            ->assertSee('Production')
            ->assertSee('Folding list')
            ->assertSee('Flat sheet stock')
            ->assertSee('Purchases')
            ->assertSee('Purchase List')
            ->assertSee('Payment Schedule')
            ->assertSee('Direct Purchase')
            ->assertSee('LPO List')
            ->assertSee('New LPO')
            ->assertSee('Expenses')
            ->assertSee('New Expense')
            ->assertSee('Expenses List')
            ->assertSee('Accounting')
            ->assertSee('Accounts Type')
            ->assertSee('Sub-Accounts Type')
            ->assertSee('Chart of Accounts')
            ->assertSee('Accounts Balances')
            ->assertSee('Journal Entry')
            ->assertSee('Profit & Loss')
            ->assertSee('Balance Sheet')
            ->assertSee('Trial Balance')
            ->assertSee('Combined GL')
            ->assertSee('Customers Balances')
            ->assertSee('Suppliers Balances')
            ->assertSee('Summary Reports')
            ->assertSee('Tax Reports')
            ->assertSee('Sales Reports')
            ->assertSee('Purchase Reports')
            ->assertSee('Stock/Products Reports')
            ->assertSee('Expense Report')
            ->assertSee('Suppliers Report')
            ->assertSee('Loyalty Points Report')
            ->assertSee('Expired Items Report')
            ->assertSee('Customer Reports')
            ->assertSee('User Logs')
            ->assertSee('Audit Trail Report')
            ->assertSee('Company Profile')
            ->assertSee('Billing')
            ->assertSee('Manage Branch')
            ->assertSee('Site Settings')
            ->assertSee('Tax List')
            ->assertSee('Salutation')
            ->assertSee('Progress Status')
            ->assertSee('Places')
            ->assertSee('Currency List')
            ->assertSee('Change Password')
            ->assertSee('Database Backup');
    }

    public function test_purchase_menu_pages_open_with_the_right_heading(): void
    {
        $this->actingAsAdmin()->get(route('purchases.index'))
            ->assertOk()
            ->assertSee('Purchase List');

        $this->actingAsAdmin()->get(route('purchases.index', ['view' => 'schedule']))
            ->assertOk()
            ->assertSee('Payment Schedule');

        $this->actingAsAdmin()->get(route('purchases.index', ['view' => 'lpo']))
            ->assertOk()
            ->assertSee('LPO List');

        $this->actingAsAdmin()->get(route('purchases.create', ['type' => 'direct']))
            ->assertOk()
            ->assertSee('Direct Purchase')
            ->assertSee('value="received" selected', false);

        $this->actingAsAdmin()->get(route('purchases.create', ['type' => 'lpo']))
            ->assertOk()
            ->assertSee('New LPO')
            ->assertSee('value="ordered" selected', false);
    }
}
