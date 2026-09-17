<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\AccountingLedger;
use App\Services\AccountingRebuildService;
use Illuminate\Console\Command;

class RebuildAccountingJournals extends Command
{
    protected $signature = 'accounting:rebuild-journals
                            {--company= : Limit rebuild to a company id}
                            {--force : Required to write changes}';

    protected $description = 'Delete auto journal entries and rebuild AP/AR/Cash/Mpesa from sales, purchases, payments, and expenses';

    public function handle(AccountingRebuildService $rebuild, AccountingLedger $ledger): int
    {
        if (! $this->option('force')) {
            $this->error('Refusing to run without --force (this rewrites journal entries).');

            return self::FAILURE;
        }

        $companyId = $this->option('company') !== null ? (int) $this->option('company') : null;
        if ($companyId) {
            if (! Company::query()->whereKey($companyId)->exists()) {
                $this->error('Company not found: ' . $companyId);

                return self::FAILURE;
            }
        }

        $this->info('Rebuilding accounting journals...');
        $result = $rebuild->rebuild($companyId);

        $this->line('Deleted journal entries: ' . $result['deleted']);
        $this->line('Posted journal entries: ' . $result['posted']);
        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        $this->newLine();
        $this->info('Control account balances after rebuild:');
        $watch = [
            'Accounts Payable',
            'Accounts Receivable',
            'Cash',
            'Mpesa',
            'Bank',
            'Suppliers Advance',
            'Inventory',
            'Sales Revenue',
            'Cost Of Goods Sold',
            'Operating Expenses',
            'Tax Payable(VAT OUTPUT)',
            'Tax Receivable(VAT Input)',
        ];
        foreach ($ledger->accountsWithBalances() as $account) {
            if (! in_array($account->name, $watch, true)) {
                continue;
            }
            $this->line(sprintf(
                '  %-32s %12.2f',
                $account->name,
                $account->current_balance
            ));
        }

        return empty($result['errors']) ? self::SUCCESS : self::FAILURE;
    }
}
