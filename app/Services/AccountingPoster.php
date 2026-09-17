<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\Sale;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AccountingPoster
{
    public function __construct(
        private AccountingCatalog $catalog,
        private DocumentNumberService $numbers
    ) {
    }

    public function ensureControlAccounts(int $companyId): void
    {
        $this->catalog->ensure($companyId);
        $this->catalog->ensureOperationalAccounts($companyId);
    }

    public function postSale(Sale $sale): ?JournalEntry
    {
        // Voided sales are posted then reversed by postSaleVoid during rebuilds.
        if (! in_array($sale->status, [Sale::STATUS_COMPLETED, Sale::STATUS_VOIDED, Sale::STATUS_RETURNED], true)) {
            return null;
        }

        $this->ensureControlAccounts((int) $sale->company_id);
        $sale->loadMissing(['payments', 'items.product']);

        $revenue = round(max(0, (float) $sale->total - (float) $sale->tax_amount), 2);
        $tax = round((float) $sale->tax_amount, 2);
        $total = round((float) $sale->total, 2);
        $paid = $this->salePaidForPosting($sale);
        $balance = round(max(0, $total - $paid), 2);

        if ($total <= 0) {
            return null;
        }

        $lines = [];
        foreach ($this->allocateSaleReceipts($sale, $paid) as $receipt) {
            $lines[] = [
                'account' => $receipt['account'],
                'debit' => $receipt['amount'],
                'credit' => 0,
                'description' => 'Sale receipt',
            ];
        }
        if ($balance > 0) {
            $lines[] = ['account' => $this->accountByName((int) $sale->company_id, 'Accounts Receivable'), 'debit' => $balance, 'credit' => 0, 'description' => 'Sale receivable'];
        }
        if ($revenue > 0) {
            $lines[] = ['account' => $this->accountByName((int) $sale->company_id, 'Sales Revenue'), 'debit' => 0, 'credit' => $revenue, 'description' => 'Sale revenue'];
        }
        if ($tax > 0) {
            $lines[] = ['account' => $this->accountByName((int) $sale->company_id, 'Tax Payable(VAT OUTPUT)'), 'debit' => 0, 'credit' => $tax, 'description' => 'VAT output'];
        }

        $cogs = $this->saleCogs($sale);
        if ($cogs > 0) {
            $lines[] = ['account' => $this->accountByName((int) $sale->company_id, 'Cost Of Goods Sold'), 'debit' => $cogs, 'credit' => 0, 'description' => 'COGS'];
            $lines[] = ['account' => $this->accountByName((int) $sale->company_id, 'Inventory'), 'debit' => 0, 'credit' => $cogs, 'description' => 'Inventory relief'];
        }

        return $this->postBalanced(
            (int) $sale->company_id,
            $sale->branch_id ? (int) $sale->branch_id : null,
            $sale,
            'sale_complete',
            'Sale ' . ($sale->number ?: $sale->id),
            optional($sale->sale_date)->toDateString() ?: now()->toDateString(),
            $lines
        );
    }

    public function postSalePayment(Sale $sale, Payment $payment): ?JournalEntry
    {
        $amount = round((float) $payment->amount, 2);
        if ($amount <= 0) {
            return null;
        }

        $this->ensureControlAccounts((int) $sale->company_id);

        $cash = $this->paymentMethodAccount((int) $sale->company_id, (string) $payment->method);
        $ar = $this->accountByName((int) $sale->company_id, 'Accounts Receivable');

        return $this->postBalanced(
            (int) $sale->company_id,
            $payment->branch_id ? (int) $payment->branch_id : ($sale->branch_id ? (int) $sale->branch_id : null),
            $payment,
            'sale_payment',
            'Payment ' . ($payment->number ?: $payment->id) . ' for sale ' . ($sale->number ?: $sale->id),
            optional($payment->paid_at)->toDateString() ?: now()->toDateString(),
            [
                ['account' => $cash, 'debit' => $amount, 'credit' => 0, 'description' => 'Customer payment'],
                ['account' => $ar, 'debit' => 0, 'credit' => $amount, 'description' => 'Clear receivable'],
            ]
        );
    }

    public function postCustomerArPayment(Customer $customer, Payment $payment): ?JournalEntry
    {
        $amount = round((float) $payment->amount, 2);
        if ($amount <= 0) {
            return null;
        }

        $this->ensureControlAccounts((int) $customer->company_id);

        $cash = $this->paymentMethodAccount((int) $customer->company_id, (string) $payment->method);
        $ar = $this->accountByName((int) $customer->company_id, 'Accounts Receivable');

        return $this->postBalanced(
            (int) $customer->company_id,
            $payment->branch_id ? (int) $payment->branch_id : ($customer->branch_id ? (int) $customer->branch_id : null),
            $payment,
            'customer_ar_payment',
            'Payment ' . ($payment->number ?: $payment->id) . ' for customer ' . ($customer->name ?: $customer->id),
            optional($payment->paid_at)->toDateString() ?: now()->toDateString(),
            [
                ['account' => $cash, 'debit' => $amount, 'credit' => 0, 'description' => 'Customer payment'],
                ['account' => $ar, 'debit' => 0, 'credit' => $amount, 'description' => 'Clear receivable'],
            ]
        );
    }

    public function postSaleVoid(Sale $sale): ?JournalEntry
    {
        $original = $this->findExisting($sale, 'sale_complete');
        if (! $original) {
            return null;
        }

        return $this->reverseEntry($original, $sale, 'sale_void', 'Void sale ' . ($sale->number ?: $sale->id));
    }

    public function postCreditNote(CreditNote $note): ?JournalEntry
    {
        if ($note->status !== CreditNote::STATUS_POSTED && $note->status !== CreditNote::STATUS_COMPLETED) {
            return null;
        }

        $total = round((float) $note->total, 2);
        $tax = round((float) $note->tax_amount, 2);
        $revenue = round(max(0, $total - $tax), 2);
        if ($total <= 0) {
            return null;
        }

        $this->ensureControlAccounts((int) $note->company_id);
        $note->loadMissing(['sale.payments', 'items.saleItem.product']);

        // Only clear AR for unpaid balances; refund cash/bank for the paid portion.
        $sale = $note->sale;
        $outstanding = $sale
            ? round(max(0, (float) $sale->total - (float) $sale->paid_amount), 2)
            : 0.0;
        $arPortion = round(min($total, $outstanding), 2);
        $refundPortion = round(max(0, $total - $arPortion), 2);

        $lines = [
            ['account' => $this->accountByName((int) $note->company_id, 'Sales Revenue'), 'debit' => $revenue, 'credit' => 0, 'description' => 'Credit note revenue'],
        ];
        if ($tax > 0) {
            $lines[] = ['account' => $this->accountByName((int) $note->company_id, 'Tax Payable(VAT OUTPUT)'), 'debit' => $tax, 'credit' => 0, 'description' => 'VAT reverse'];
        }
        if ($arPortion > 0) {
            $lines[] = ['account' => $this->accountByName((int) $note->company_id, 'Accounts Receivable'), 'debit' => 0, 'credit' => $arPortion, 'description' => 'Credit customer receivable'];
        }
        if ($refundPortion > 0) {
            foreach ($this->allocateSaleReceipts($sale ?: new Sale(), $refundPortion) as $receipt) {
                $lines[] = [
                    'account' => $receipt['account'],
                    'debit' => 0,
                    'credit' => $receipt['amount'],
                    'description' => 'Credit note refund',
                ];
            }
        }

        $cogs = $this->creditNoteCogs($note);
        if ($cogs > 0 && $note->stock_restored) {
            $lines[] = ['account' => $this->accountByName((int) $note->company_id, 'Inventory'), 'debit' => $cogs, 'credit' => 0, 'description' => 'Stock return'];
            $lines[] = ['account' => $this->accountByName((int) $note->company_id, 'Cost Of Goods Sold'), 'debit' => 0, 'credit' => $cogs, 'description' => 'COGS reverse'];
        }

        return $this->postBalanced(
            (int) $note->company_id,
            $note->branch_id ? (int) $note->branch_id : null,
            $note,
            'credit_note',
            'Credit note ' . $note->number,
            optional($note->credit_date)->toDateString() ?: now()->toDateString(),
            $lines
        );
    }

    public function reverseCreditNote(CreditNote $note): ?JournalEntry
    {
        $original = $this->findExisting($note, 'credit_note');
        if (! $original) {
            return null;
        }

        return $this->reverseEntry($original, $note, 'credit_note_void', 'Void credit note ' . $note->number);
    }

    public function postPurchaseReceipt(PurchaseOrder $purchase, float $amount, float $tax = 0, ?Model $source = null): ?JournalEntry
    {
        $amount = round($amount, 2);
        $tax = round($tax, 2);
        $net = round(max(0, $amount - $tax), 2);
        if ($amount <= 0) {
            return null;
        }

        $this->ensureControlAccounts((int) $purchase->company_id);
        $source = $source ?: $purchase;

        return $this->postBalanced(
            (int) $purchase->company_id,
            $purchase->branch_id ? (int) $purchase->branch_id : null,
            $source,
            'purchase_receipt',
            'Purchase receive ' . ($purchase->number ?: $purchase->id),
            now()->toDateString(),
            [
                ['account' => $this->accountByName((int) $purchase->company_id, 'Inventory'), 'debit' => $net, 'credit' => 0, 'description' => 'Inventory receipt'],
                ['account' => $this->accountByName((int) $purchase->company_id, 'Tax Receivable(VAT Input)'), 'debit' => $tax, 'credit' => 0, 'description' => 'VAT input'],
                ['account' => $this->accountByName((int) $purchase->company_id, 'Accounts Payable'), 'debit' => 0, 'credit' => $amount, 'description' => 'Supplier payable'],
            ]
        );
    }

    public function postPurchasePayment(PurchaseOrder $purchase, Payment $payment): ?JournalEntry
    {
        $amount = round((float) $payment->amount, 2);
        if ($amount <= 0) {
            return null;
        }

        $this->ensureControlAccounts((int) $purchase->company_id);
        $purchase->loadMissing('receipts');

        // Payments before goods are received are supplier advances, not AP clearances.
        $hasReceipt = $purchase->receipts->isNotEmpty()
            || in_array($purchase->status, [
                PurchaseOrder::STATUS_RECEIVED,
                PurchaseOrder::STATUS_PARTIAL,
            ], true);
        $debitAccount = $hasReceipt
            ? $this->accountByName((int) $purchase->company_id, 'Accounts Payable')
            : $this->accountByName((int) $purchase->company_id, 'Suppliers Advance');

        return $this->postBalanced(
            (int) $purchase->company_id,
            $payment->branch_id ? (int) $payment->branch_id : ($purchase->branch_id ? (int) $purchase->branch_id : null),
            $payment,
            'purchase_payment',
            'Supplier payment ' . ($payment->number ?: $payment->id),
            optional($payment->paid_at)->toDateString() ?: now()->toDateString(),
            [
                ['account' => $debitAccount, 'debit' => $amount, 'credit' => 0, 'description' => $hasReceipt ? 'Clear payable' : 'Supplier advance'],
                ['account' => $this->paymentMethodAccount((int) $purchase->company_id, (string) $payment->method), 'debit' => 0, 'credit' => $amount, 'description' => 'Cash/bank out'],
            ]
        );
    }

    public function postPurchaseReturn(PurchaseReturn $return): ?JournalEntry
    {
        $total = round((float) ($return->total ?? $return->refund_amount ?? 0), 2);
        if ($total <= 0) {
            // Fallback sum items
            $return->loadMissing('items');
            $total = round((float) $return->items->sum('line_total'), 2);
        }
        if ($total <= 0) {
            return null;
        }

        $this->ensureControlAccounts((int) $return->company_id);
        $return->loadMissing(['items', 'purchaseOrder.receipts']);

        $purchase = $return->purchaseOrder;
        $received = $this->purchaseReceivedAmount($purchase);
        $paid = $purchase
            ? round((float) Payment::query()
                ->where('payable_type', PurchaseOrder::class)
                ->where('payable_id', $purchase->id)
                ->sum('amount'), 2)
            : 0.0;
        $priorReturns = round((float) PurchaseReturn::query()
            ->where('purchase_order_id', optional($purchase)->id)
            ->where('id', '<', $return->id)
            ->sum('total'), 2);
        $apBalance = round(max(0, $received - $paid - $priorReturns), 2);
        $apPortion = round(min($total, $apBalance), 2);
        $advancePortion = round(max(0, $total - $apPortion), 2);

        $lines = [];
        if ($apPortion > 0) {
            $lines[] = ['account' => $this->accountByName((int) $return->company_id, 'Accounts Payable'), 'debit' => $apPortion, 'credit' => 0, 'description' => 'Reduce payable'];
        }
        if ($advancePortion > 0) {
            $lines[] = ['account' => $this->accountByName((int) $return->company_id, 'Suppliers Advance'), 'debit' => $advancePortion, 'credit' => 0, 'description' => 'Supplier refund due'];
        }
        $lines[] = ['account' => $this->accountByName((int) $return->company_id, 'Inventory'), 'debit' => 0, 'credit' => $total, 'description' => 'Inventory out'];

        return $this->postBalanced(
            (int) $return->company_id,
            $return->branch_id ? (int) $return->branch_id : null,
            $return,
            'purchase_return',
            'Purchase return ' . ($return->number ?: $return->id),
            optional($return->return_date)->toDateString() ?: now()->toDateString(),
            $lines
        );
    }

    public function postExpense(Expense $expense): ?JournalEntry
    {
        $amount = round((float) $expense->amount, 2);
        if ($amount <= 0 || ($expense->status ?? 'paid') === 'cancelled') {
            return null;
        }

        $this->ensureControlAccounts((int) $expense->company_id);
        $expenseAccount = $this->accountByName((int) $expense->company_id, 'Operating Expenses');
        $cash = $this->paymentMethodAccount((int) $expense->company_id, (string) ($expense->payment_method ?? 'cash'));

        return $this->postBalanced(
            (int) $expense->company_id,
            $expense->branch_id ? (int) $expense->branch_id : null,
            $expense,
            'expense',
            'Expense ' . ($expense->number ?: $expense->id),
            optional($expense->expense_date)->toDateString() ?: now()->toDateString(),
            [
                ['account' => $expenseAccount, 'debit' => $amount, 'credit' => 0, 'description' => 'Expense'],
                ['account' => $cash, 'debit' => 0, 'credit' => $amount, 'description' => 'Expense payment'],
            ]
        );
    }

    /**
     * @param array<int, array{account:LedgerAccount,debit:float,credit:float,description?:string}> $lines
     */
    public function postBalanced(
        int $companyId,
        ?int $branchId,
        Model $source,
        string $event,
        string $description,
        string $entryDate,
        array $lines
    ): ?JournalEntry {
        $existing = $this->findExisting($source, $event);
        if ($existing) {
            if (method_exists($existing, 'trashed') && $existing->trashed()) {
                JournalLine::query()->where('journal_entry_id', $existing->id)->delete();
                $existing->forceDelete();
            } else {
                return $existing;
            }
        }

        $normalized = [];
        foreach ($lines as $line) {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);
            if ($debit <= 0 && $credit <= 0) {
                continue;
            }
            if (! ($line['account'] instanceof LedgerAccount)) {
                throw new InvalidArgumentException('Journal line missing ledger account.');
            }
            $normalized[] = [
                'account' => $line['account'],
                'debit' => $debit,
                'credit' => $credit,
                'description' => $line['description'] ?? $description,
            ];
        }

        $debitTotal = round(collect($normalized)->sum('debit'), 2);
        $creditTotal = round(collect($normalized)->sum('credit'), 2);
        if ($debitTotal <= 0 || abs($debitTotal - $creditTotal) > 0.01) {
            throw new InvalidArgumentException('Journal entry is not balanced: debit ' . $debitTotal . ' credit ' . $creditTotal);
        }

        return DB::transaction(function () use ($companyId, $branchId, $source, $event, $description, $entryDate, $normalized) {
            $entry = JournalEntry::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'user_id' => auth()->id(),
                'number' => $this->numbers->next($companyId, 'journal'),
                'reference' => method_exists($source, 'getKey') ? (string) $source->getKey() : null,
                'entry_date' => $entryDate,
                'description' => $description,
                'source_type' => get_class($source),
                'source_id' => $source->getKey(),
                'source_event' => $event,
            ]);

            foreach ($normalized as $line) {
                JournalLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'ledger_account_id' => $line['account']->id,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'description' => $line['description'],
                ]);
            }

            return $entry->fresh('lines');
        });
    }

    public function findExisting(Model $source, string $event): ?JournalEntry
    {
        return JournalEntry::withTrashed()
            ->where('source_type', get_class($source))
            ->where('source_id', $source->getKey())
            ->where('source_event', $event)
            ->first();
    }

    private function reverseEntry(JournalEntry $original, Model $source, string $event, string $description): ?JournalEntry
    {
        if ($this->findExisting($source, $event)) {
            return $this->findExisting($source, $event);
        }

        $original->loadMissing('lines.account');
        $lines = [];
        foreach ($original->lines as $line) {
            $lines[] = [
                'account' => $line->account,
                'debit' => (float) $line->credit,
                'credit' => (float) $line->debit,
                'description' => $description,
            ];
        }

        return $this->postBalanced(
            (int) $original->company_id,
            $original->branch_id ? (int) $original->branch_id : null,
            $source,
            $event,
            $description,
            now()->toDateString(),
            $lines
        );
    }

    private function accountByName(int $companyId, string $name): LedgerAccount
    {
        $account = LedgerAccount::query()
            ->where('company_id', $companyId)
            ->where('name', $name)
            ->where('is_active', true)
            ->first();

        if (! $account) {
            $this->catalog->ensureOperationalAccounts($companyId);
            $account = LedgerAccount::query()
                ->where('company_id', $companyId)
                ->where('name', $name)
                ->first();
        }

        if (! $account) {
            throw new InvalidArgumentException('Missing ledger account: ' . $name);
        }

        return $account;
    }

    private function paymentMethodAccount(int $companyId, string $method): LedgerAccount
    {
        $method = strtolower($method);
        $map = [
            'cash' => 'Cash',
            'mpesa' => 'Mpesa',
            'card' => 'Bank',
            'bank_transfer' => 'Bank',
            'cheque' => 'CHEQUE',
            'bank' => 'Bank',
        ];

        $name = $map[$method] ?? 'Cash';
        try {
            return $this->accountByName($companyId, $name);
        } catch (InvalidArgumentException $e) {
            return $this->accountByName($companyId, 'Cash');
        }
    }

    private function paymentAccountForSale(Sale $sale): LedgerAccount
    {
        $sale->loadMissing('payments');
        $method = optional($sale->payments->sortByDesc('id')->first())->method ?: 'cash';

        return $this->paymentMethodAccount((int) $sale->company_id, (string) $method);
    }

    private function salePaidForPosting(Sale $sale): float
    {
        $paid = round((float) $sale->paid_amount, 2);
        if (in_array($sale->status, [Sale::STATUS_VOIDED], true)) {
            $fromPayments = round((float) $sale->payments->sum('amount'), 2);
            $paid = round(min((float) $sale->total, max($paid, $fromPayments)), 2);
        }

        return max(0, $paid);
    }

    /**
     * @return array<int, array{account:LedgerAccount,amount:float}>
     */
    private function allocateSaleReceipts(?Sale $sale, float $amount): array
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return [];
        }

        $companyId = (int) optional($sale)->company_id;
        if ($companyId <= 0) {
            return [];
        }

        $allocations = [];
        $remaining = $amount;
        $payments = optional($sale)->relationLoaded('payments')
            ? $sale->payments
            : collect();

        foreach ($payments->sortBy('id') as $payment) {
            if ($remaining <= 0) {
                break;
            }
            $slice = round(min($remaining, (float) $payment->amount), 2);
            if ($slice <= 0) {
                continue;
            }
            $account = $this->paymentMethodAccount($companyId, (string) $payment->method);
            $key = $account->id;
            if (! isset($allocations[$key])) {
                $allocations[$key] = ['account' => $account, 'amount' => 0.0];
            }
            $allocations[$key]['amount'] = round($allocations[$key]['amount'] + $slice, 2);
            $remaining = round($remaining - $slice, 2);
        }

        if ($remaining > 0) {
            $account = $sale ? $this->paymentAccountForSale($sale) : $this->accountByName($companyId, 'Cash');
            $key = $account->id;
            if (! isset($allocations[$key])) {
                $allocations[$key] = ['account' => $account, 'amount' => 0.0];
            }
            $allocations[$key]['amount'] = round($allocations[$key]['amount'] + $remaining, 2);
        }

        return array_values($allocations);
    }

    private function purchaseReceivedAmount(?PurchaseOrder $purchase): float
    {
        if (! $purchase) {
            return 0.0;
        }

        $purchase->loadMissing('receipts.items');
        $fromItems = 0.0;
        foreach ($purchase->receipts as $receipt) {
            foreach ($receipt->items as $item) {
                $fromItems += (float) $item->quantity_received * (float) $item->unit_cost;
            }
        }
        $fromItems = round($fromItems, 2);
        if ($fromItems > 0) {
            $tax = round((float) $purchase->tax_amount, 2);
            // When PO total already embeds tax (subtotal == total), do not add tax again.
            if ($tax > 0 && abs((float) $purchase->total - (float) $purchase->subtotal) < 0.009) {
                $net = round(max(0, (float) $purchase->total - $tax), 2);
                if (abs($fromItems - $net) < 0.05) {
                    return round((float) $purchase->total, 2);
                }
            }

            return $fromItems;
        }

        if (in_array($purchase->status, [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_PARTIAL], true)) {
            return round((float) $purchase->total, 2);
        }

        return 0.0;
    }

    private function saleCogs(Sale $sale): float
    {
        $sale->loadMissing('items.product');
        $total = 0.0;
        foreach ($sale->items as $item) {
            $cost = (float) ($item->cost_price ?? optional($item->product)->cost_price ?? 0);
            $total += $cost * (float) $item->quantity;
        }

        return round($total, 2);
    }

    private function creditNoteCogs(CreditNote $note): float
    {
        $note->loadMissing('items.saleItem.product');
        $total = 0.0;
        foreach ($note->items as $item) {
            $cost = (float) (optional($item->saleItem)->cost_price
                ?? optional(optional($item->saleItem)->product)->cost_price
                ?? 0);
            $total += $cost * (float) $item->quantity;
        }

        return round($total, 2);
    }
}
