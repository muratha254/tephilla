<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\MoneyEntry;
use App\Models\Sale;
use App\Models\Supplier;
use Illuminate\Support\Collection;

class AccountingLedger
{
    public function accountBalance(LedgerAccount $account, ?string $asOf = null): float
    {
        $opening = (float) $account->opening_balance;
        $journalDebit = (float) $account->journalLines()
            ->whereHas('entry', function ($query) use ($asOf) {
                if ($asOf) {
                    $query->whereDate('entry_date', '<=', $asOf);
                }
            })
            ->sum('debit');
        $journalCredit = (float) $account->journalLines()
            ->whereHas('entry', function ($query) use ($asOf) {
                if ($asOf) {
                    $query->whereDate('entry_date', '<=', $asOf);
                }
            })
            ->sum('credit');
        $moneyIn = (float) $account->moneyEntries()
            ->where('type', '!=', MoneyEntry::TYPE_OPEN_BAL)
            ->when($asOf, fn ($query) => $query->whereDate('payment_date', '<=', $asOf))
            ->sum('amount_in');
        $moneyOut = (float) $account->moneyEntries()
            ->where('type', '!=', MoneyEntry::TYPE_OPEN_BAL)
            ->when($asOf, fn ($query) => $query->whereDate('payment_date', '<=', $asOf))
            ->sum('amount_out');

        $raw = $opening + $journalDebit - $journalCredit + $moneyIn - $moneyOut;

        return $account->isDebitNormal() ? round($raw, 2) : round(-$raw, 2);
    }

    /**
     * @return array{debit:float,credit:float}
     */
    public function accountPeriod(LedgerAccount $account, ?string $from = null, ?string $to = null): array
    {
        $journal = JournalLine::query()
            ->where('ledger_account_id', $account->id)
            ->whereHas('entry', function ($query) use ($from, $to) {
                if ($from) {
                    $query->whereDate('entry_date', '>=', $from);
                }
                if ($to) {
                    $query->whereDate('entry_date', '<=', $to);
                }
            })
            ->selectRaw('COALESCE(SUM(debit),0) as debit, COALESCE(SUM(credit),0) as credit')
            ->first();

        $money = MoneyEntry::query()
            ->where('ledger_account_id', $account->id)
            ->where('type', '!=', MoneyEntry::TYPE_OPEN_BAL)
            ->when($from, fn ($query) => $query->whereDate('payment_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('payment_date', '<=', $to))
            ->selectRaw('COALESCE(SUM(amount_in),0) as amount_in, COALESCE(SUM(amount_out),0) as amount_out')
            ->first();

        return [
            'debit' => round((float) optional($journal)->debit + (float) optional($money)->amount_in, 2),
            'credit' => round((float) optional($journal)->credit + (float) optional($money)->amount_out, 2),
        ];
    }

    public function accountsWithBalances(?string $asOf = null): Collection
    {
        return LedgerAccount::query()
            ->with(['subType.accountType'])
            ->orderBy('name')
            ->get()
            ->map(function (LedgerAccount $account) use ($asOf) {
                $account->current_balance = $this->accountBalance($account, $asOf);

                return $account;
            });
    }

    /**
     * @return array{income:array<int,array<string,mixed>>,expenses:array<int,array<string,mixed>>,income_total:float,expense_total:float,net:float}
     */
    public function profitAndLoss(string $from, string $to): array
    {
        $income = [];
        $expenses = [];
        $salesGross = $this->salesTotal($from, $to);
        $salesTax = $this->salesTaxTotal($from, $to);
        $recordedExpenses = $this->expenseTotal($from, $to);
        $expensesIncluded = false;

        foreach ($this->accountsWithType(['Income/Revenue']) as $account) {
            $period = $this->accountPeriod($account, $from, $to);
            $journalNet = round($period['credit'] - $period['debit'], 2);
            if ($account->name === 'Sales Revenue') {
                // The sales journal already credits the amount after VAT. Adding the
                // sales-list total on top counted the same sale twice.
                $manual = round($journalNet - ($salesGross - $salesTax), 2);
                $amount = round($salesGross + $manual, 2);
                if (abs($amount) >= 0.0001) {
                    $income[] = ['name' => 'Sales Revenue', 'amount' => $amount];
                }
                if (abs($salesTax) >= 0.0001) {
                    $income[] = ['name' => 'VAT on sales', 'amount' => round(-$salesTax, 2)];
                }
                continue;
            }
            if (abs($journalNet) < 0.0001) {
                continue;
            }
            $income[] = ['name' => $account->name, 'amount' => $journalNet];
        }

        if ($salesGross && ! collect($income)->contains(fn ($row) => $row['name'] === 'Sales Revenue')) {
            $income[] = ['name' => 'Sales Revenue', 'amount' => $salesGross];
            if (abs($salesTax) >= 0.0001) {
                $income[] = ['name' => 'VAT on sales', 'amount' => round(-$salesTax, 2)];
            }
        }

        foreach ($this->accountsWithType(['EXPENSES']) as $account) {
            $period = $this->accountPeriod($account, $from, $to);
            $journalNet = round($period['debit'] - $period['credit'], 2);
            if ($account->name === 'Operating Expenses') {
                $manual = round($journalNet - $recordedExpenses, 2);
                $amount = round($recordedExpenses + $manual, 2);
                $expensesIncluded = true;
            } else {
                $amount = $journalNet;
            }
            if (abs($amount) < 0.0001) {
                continue;
            }
            $expenses[] = ['name' => $account->name, 'amount' => $amount];
        }

        if ($recordedExpenses && ! $expensesIncluded) {
            $expenses[] = ['name' => 'Recorded Expenses', 'amount' => $recordedExpenses];
        }

        $incomeTotal = round(collect($income)->sum('amount'), 2);
        $expenseTotal = round(collect($expenses)->sum('amount'), 2);

        return [
            'income' => $income,
            'expenses' => $expenses,
            'income_total' => $incomeTotal,
            'expense_total' => $expenseTotal,
            'net' => round($incomeTotal - $expenseTotal, 2),
        ];
    }

    /**
     * @return array{assets:array<int,array<string,mixed>>,liabilities:array<int,array<string,mixed>>,equity:array<int,array<string,mixed>>,asset_total:float,liability_total:float,equity_total:float}
     */
    public function balanceSheet(string $asOf): array
    {
        $assets = $this->groupBalances(['ASSETS'], $asOf);
        $liabilities = $this->groupBalances(['LIABILITIES'], $asOf);
        $equity = $this->groupBalances(['Equity Capital'], $asOf);

        $pl = $this->profitAndLoss('2000-01-01', $asOf);
        if (abs($pl['net']) >= 0.0001 && ! collect($equity)->contains(fn ($row) => $row['name'] === 'Retained Earnings')) {
            $equity[] = ['name' => 'Retained Earnings', 'amount' => $pl['net']];
        } else {
            foreach ($equity as &$row) {
                if ($row['name'] === 'Retained Earnings') {
                    $row['amount'] = round($row['amount'] + $pl['net'], 2);
                }
            }
            unset($row);
        }

        $assetTotal = round(collect($assets)->sum('amount'), 2);
        $liabilityTotal = round(collect($liabilities)->sum('amount'), 2);
        $equityTotal = round(collect($equity)->sum('amount'), 2);

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'asset_total' => $assetTotal,
            'liability_total' => $liabilityTotal,
            'equity_total' => $equityTotal,
            'as_of' => $asOf,
        ];
    }

    public function trialBalance(string $asOf): Collection
    {
        return LedgerAccount::query()
            ->with(['subType.accountType'])
            ->orderBy('gl_code')
            ->get()
            ->map(function (LedgerAccount $account) use ($asOf) {
                $period = $this->accountPeriod($account, null, $asOf);
                $opening = (float) $account->opening_balance;
                $debit = $period['debit'] + ($account->isDebitNormal() && $opening > 0 ? $opening : 0);
                $credit = $period['credit'] + ((! $account->isDebitNormal()) && $opening > 0 ? $opening : 0);
                if ($opening < 0) {
                    if ($account->isDebitNormal()) {
                        $credit += abs($opening);
                    } else {
                        $debit += abs($opening);
                    }
                }

                return [
                    'name' => $account->name,
                    'gl_code' => $account->gl_code,
                    'type' => optional(optional($account->subType)->accountType)->name,
                    'debit' => round($debit, 2),
                    'credit' => round($credit, 2),
                ];
            })
            ->filter(fn ($row) => abs($row['debit']) >= 0.0001 || abs($row['credit']) >= 0.0001)
            ->values();
    }

    public function combinedGl(string $from, string $to, ?int $accountId = null): Collection
    {
        $journals = JournalLine::query()
            ->with(['account', 'entry'])
            ->when($accountId, fn ($query) => $query->where('ledger_account_id', $accountId))
            ->whereHas('entry', function ($query) use ($from, $to) {
                $query->whereDate('entry_date', '>=', $from)->whereDate('entry_date', '<=', $to);
            })
            ->get()
            ->map(function (JournalLine $line) {
                return [
                    'date' => optional($line->entry)->entry_date,
                    'account' => optional($line->account)->name,
                    'reference' => optional($line->entry)->reference ?: optional($line->entry)->number,
                    'description' => $line->description ?: optional($line->entry)->description,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                ];
            });

        $money = MoneyEntry::query()
            ->with('account')
            ->when($accountId, fn ($query) => $query->where('ledger_account_id', $accountId))
            ->whereDate('payment_date', '>=', $from)
            ->whereDate('payment_date', '<=', $to)
            ->get()
            ->map(function (MoneyEntry $entry) {
                return [
                    'date' => $entry->payment_date,
                    'account' => optional($entry->account)->name,
                    'reference' => $entry->voucher_no ?: $entry->number,
                    'description' => $entry->description,
                    'debit' => (float) $entry->amount_in,
                    'credit' => (float) $entry->amount_out,
                ];
            });

        return $journals->concat($money)->sortBy('date')->values();
    }

    private function accountsWithType(array $names): Collection
    {
        return LedgerAccount::query()
            ->with(['subType.accountType'])
            ->whereHas('subType.accountType', function ($query) use ($names) {
                $query->whereIn('name', $names);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<int, string>  $types
     * @return array<int, array{name:string,amount:float}>
     */
    private function groupBalances(array $types, string $asOf): array
    {
        $rows = [];
        foreach ($this->accountsWithType($types) as $account) {
            $amount = $this->accountBalance($account, $asOf);
            if (abs($amount) < 0.0001) {
                continue;
            }
            $rows[] = ['name' => $account->name, 'amount' => $amount];
        }

        return $rows;
    }

    private function salesTotal(string $from, string $to): float
    {
        return round((float) $this->completedSales($from, $to)->sum('total'), 2);
    }

    private function salesTaxTotal(string $from, string $to): float
    {
        return round((float) $this->completedSales($from, $to)->sum('tax_amount'), 2);
    }

    private function completedSales(string $from, string $to)
    {
        return Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereDate('sale_date', '>=', $from)
            ->whereDate('sale_date', '<=', $to);
    }

    private function expenseTotal(string $from, string $to): float
    {
        return round((float) Expense::query()
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to)
            ->sum('amount'), 2);
    }

    public function customerRows(): Collection
    {
        return Customer::query()
            ->with('branch')
            ->withSum(['sales as credit_sales' => function ($query) {
                $query->where('status', Sale::STATUS_COMPLETED);
            }], 'balance')
            ->orderBy('name')
            ->get();
    }

    public function supplierRows(): Collection
    {
        return Supplier::query()->with('branch')->orderBy('name')->get();
    }
}
