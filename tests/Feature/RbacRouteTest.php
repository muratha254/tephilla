<?php

namespace Tests\Feature;

use Tests\Support\CreatesSellixWorld;
use Tests\TestCase;

class RbacRouteTest extends TestCase
{
    use CreatesSellixWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSellixWorld();
    }

    public function test_cashier_can_access_pos(): void
    {
        $this->actingAsCashier()
            ->get(route('pos.index'))
            ->assertOk();
    }

    public function test_cashier_cannot_access_users_index(): void
    {
        $this->actingAsCashier()
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_inventory_manager_can_access_stock_transfers(): void
    {
        $this->actingAsInventoryManager()
            ->get(route('stock.transfers.index'))
            ->assertOk();
    }

    public function test_inventory_manager_cannot_access_hr_payroll_without_users_view(): void
    {
        $this->assertFalse($this->inventoryManager->fresh(['role.permissions'])->hasPermission('users.view'));

        $this->actingAsInventoryManager()
            ->get(route('hr.payroll.index'))
            ->assertForbidden();
    }
}
