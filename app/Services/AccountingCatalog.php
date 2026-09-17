<?php

namespace App\Services;

use App\Models\AccountSubType;
use App\Models\AccountType;
use App\Models\LedgerAccount;

class AccountingCatalog
{
    public function ensure(int $companyId): void
    {
        if (AccountType::query()->where('company_id', $companyId)->exists()) {
            return;
        }

        $types = [];
        foreach ($this->defaultTypes() as $row) {
            $types[$row['name']] = AccountType::query()->create(array_merge($row, [
                'company_id' => $companyId,
            ]));
        }

        $subTypes = [];
        foreach ($this->defaultSubTypes() as $row) {
            $type = $types[$row['type']] ?? null;
            if (! $type) {
                continue;
            }
            $subTypes[$row['name']] = AccountSubType::query()->create([
                'company_id' => $companyId,
                'account_type_id' => $type->id,
                'code' => $row['code'],
                'name' => $row['name'],
                'description' => $row['description'] ?? null,
                'is_active' => true,
            ]);
        }

        foreach ($this->defaultAccounts() as $row) {
            $sub = $subTypes[$row['sub']] ?? null;
            if (! $sub) {
                continue;
            }
            LedgerAccount::query()->create([
                'company_id' => $companyId,
                'account_sub_type_id' => $sub->id,
                'name' => $row['name'],
                'gl_code' => $row['gl'],
                'description' => $row['description'] ?? null,
                'opening_balance' => 0,
                'is_active' => $row['active'] ?? true,
            ]);
        }
    }

    /**
     * Ensure AR/AP/Inventory/expense control accounts exist for companies
     * that already have a seeded chart (ensure() is a no-op once seeded).
     */
    public function ensureOperationalAccounts(int $companyId): void
    {
        $this->ensure($companyId);

        $needed = [
            ['name' => 'Accounts Receivable', 'gl' => 'GL0020', 'sub' => 'Current Assets'],
            ['name' => 'Accounts Payable', 'gl' => 'GL0021', 'sub' => 'Current Liability'],
            ['name' => 'Inventory', 'gl' => 'GL0022', 'sub' => 'Current Assets'],
            ['name' => 'Operating Expenses', 'gl' => 'GL0023', 'sub' => 'OPERATING EXPENSES'],
            ['name' => 'Salaries Expense', 'gl' => 'GL0024', 'sub' => 'ADMINISTRATIVE EXPENSES'],
            ['name' => 'Salaries Payable', 'gl' => 'GL0025', 'sub' => 'Current Liability'],
        ];

        foreach ($needed as $row) {
            $exists = LedgerAccount::query()
                ->where('company_id', $companyId)
                ->where('name', $row['name'])
                ->exists();
            if ($exists) {
                continue;
            }

            $sub = AccountSubType::query()
                ->where('company_id', $companyId)
                ->where('name', $row['sub'])
                ->first();
            if (! $sub) {
                continue;
            }

            $gl = $row['gl'];
            if (LedgerAccount::query()->where('company_id', $companyId)->where('gl_code', $gl)->exists()) {
                $gl = $this->nextGlCode($companyId);
            }

            LedgerAccount::query()->create([
                'company_id' => $companyId,
                'account_sub_type_id' => $sub->id,
                'name' => $row['name'],
                'gl_code' => $gl,
                'description' => 'System control account',
                'opening_balance' => 0,
                'is_active' => true,
            ]);
        }
    }

    public function nextSubTypeCode(int $companyId): int
    {
        return ((int) AccountSubType::query()->where('company_id', $companyId)->max('code')) + 1;
    }

    public function nextGlCode(int $companyId): string
    {
        $last = LedgerAccount::query()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->value('gl_code');

        $number = 1;
        if ($last && preg_match('/(\d+)$/', $last, $match)) {
            $number = ((int) $match[1]) + 1;
        }

        return 'GL' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<int, array{code:int,name:string,report_type:string,description:string}>
     */
    public function defaultTypes(): array
    {
        return [
            ['code' => 1, 'name' => 'ASSETS', 'report_type' => 'BS', 'description' => 'Assets'],
            ['code' => 2, 'name' => 'LIABILITIES', 'report_type' => 'BS', 'description' => 'Liability'],
            ['code' => 3, 'name' => 'Equity Capital', 'report_type' => 'BS', 'description' => 'Equity'],
            ['code' => 4, 'name' => 'Income/Revenue', 'report_type' => 'PL', 'description' => 'Income'],
            ['code' => 5, 'name' => 'EXPENSES', 'report_type' => 'PL', 'description' => 'Expense'],
        ];
    }

    /**
     * @return array<int, array{code:int,name:string,type:string,description?:string}>
     */
    public function defaultSubTypes(): array
    {
        return [
            ['code' => 1, 'name' => 'Cash & Cash Equivalent', 'type' => 'ASSETS', 'description' => 'All Payment Accounts'],
            ['code' => 2, 'name' => 'Current Assets', 'type' => 'ASSETS'],
            ['code' => 3, 'name' => 'Non-Current Assets', 'type' => 'ASSETS'],
            ['code' => 4, 'name' => 'Current Liability', 'type' => 'LIABILITIES'],
            ['code' => 5, 'name' => 'Long-Term Liability', 'type' => 'LIABILITIES'],
            ['code' => 6, 'name' => 'Equity Accounts', 'type' => 'Equity Capital'],
            ['code' => 7, 'name' => 'Revenues', 'type' => 'Income/Revenue'],
            ['code' => 8, 'name' => 'OPERATING EXPENSES', 'type' => 'EXPENSES'],
            ['code' => 9, 'name' => 'DIRECT EXPENSES', 'type' => 'EXPENSES'],
            ['code' => 10, 'name' => 'ADMINISTRATIVE EXPENSES', 'type' => 'EXPENSES'],
            ['code' => 12, 'name' => 'General Assets', 'type' => 'ASSETS'],
        ];
    }

    /**
     * @return array<int, array{name:string,gl:string,sub:string,description?:string,active?:bool}>
     */
    public function defaultAccounts(): array
    {
        return [
            ['name' => 'Sales Revenue', 'gl' => 'GL0002', 'sub' => 'Revenues'],
            ['name' => 'Cost Of Goods Sold', 'gl' => 'GL0003', 'sub' => 'OPERATING EXPENSES'],
            ['name' => 'Cash', 'gl' => 'GL0004', 'sub' => 'Cash & Cash Equivalent', 'description' => 'cash'],
            ['name' => 'Mpesa', 'gl' => 'GL0005', 'sub' => 'Cash & Cash Equivalent'],
            ['name' => 'Bank', 'gl' => 'GL0006', 'sub' => 'Cash & Cash Equivalent'],
            ['name' => 'Loyalty Points', 'gl' => 'GL0007', 'sub' => 'Current Liability'],
            ['name' => 'CHEQUE', 'gl' => 'GL0008', 'sub' => 'Cash & Cash Equivalent', 'active' => false],
            ['name' => 'Tax Payable(VAT OUTPUT)', 'gl' => 'GL0009', 'sub' => 'Current Liability', 'description' => 'Sales and Invoices'],
            ['name' => 'Tax Receivable(VAT Input)', 'gl' => 'GL0010', 'sub' => 'Current Assets'],
            ['name' => 'Withholding Tax', 'gl' => 'GL0011', 'sub' => 'Current Liability'],
            ['name' => 'Commission Payable', 'gl' => 'GL0012', 'sub' => 'Current Liability'],
            ['name' => 'Suppliers Advance', 'gl' => 'GL0013', 'sub' => 'Current Assets'],
            ['name' => 'Retained Earnings', 'gl' => 'GL0014', 'sub' => 'Equity Accounts'],
            ['name' => 'Accounts Receivable', 'gl' => 'GL0020', 'sub' => 'Current Assets'],
            ['name' => 'Accounts Payable', 'gl' => 'GL0021', 'sub' => 'Current Liability'],
            ['name' => 'Inventory', 'gl' => 'GL0022', 'sub' => 'Current Assets'],
            ['name' => 'Operating Expenses', 'gl' => 'GL0023', 'sub' => 'OPERATING EXPENSES'],
            ['name' => 'Salaries Expense', 'gl' => 'GL0024', 'sub' => 'ADMINISTRATIVE EXPENSES'],
            ['name' => 'Salaries Payable', 'gl' => 'GL0025', 'sub' => 'Current Liability'],
        ];
    }
}
