<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Sale;
class AccountingRebuildService
{
    public function __construct(
        private AccountingPoster $poster
    ) {
    }

    /**
     * Wipe system journals and repost from operational documents.
     *
     * @return array{deleted:int,posted:int,errors:array<int,string>}
     */
    public function rebuild(?int $companyId = null): array
    {
        $deleted = 0;
        $posted = 0;
        $errors = [];

        $entryQuery = JournalEntry::withTrashed()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId));
        $entryIds = $entryQuery->pluck('id');
        if ($entryIds->isNotEmpty()) {
            JournalLine::query()->whereIn('journal_entry_id', $entryIds)->delete();
            $deleted = JournalEntry::withTrashed()->whereIn('id', $entryIds)->forceDelete();
        }

        foreach ($this->sales($companyId) as $sale) {
            try {
                if ($this->poster->postSale($sale)) {
                    $posted++;
                }
                if ($sale->status === Sale::STATUS_VOIDED && $this->poster->postSaleVoid($sale)) {
                    $posted++;
                }
            } catch (\Throwable $e) {
                $errors[] = 'Sale ' . ($sale->number ?: $sale->id) . ': ' . $e->getMessage();
            }
        }

        foreach ($this->creditNotes($companyId) as $note) {
            try {
                if ($this->poster->postCreditNote($note)) {
                    $posted++;
                }
            } catch (\Throwable $e) {
                $errors[] = 'Credit note ' . ($note->number ?: $note->id) . ': ' . $e->getMessage();
            }
        }

        foreach ($this->goodsReceipts($companyId) as $receipt) {
            try {
                [$amount, $tax] = $this->receiptAmount($receipt);
                if ($amount <= 0 || ! $receipt->purchaseOrder) {
                    continue;
                }
                if ($this->poster->postPurchaseReceipt($receipt->purchaseOrder, $amount, $tax, $receipt)) {
                    $posted++;
                }
            } catch (\Throwable $e) {
                $errors[] = 'Goods receipt ' . ($receipt->number ?: $receipt->id) . ': ' . $e->getMessage();
            }
        }

        foreach ($this->purchasePayments($companyId) as $payment) {
            try {
                $purchase = $payment->payable;
                if (! $purchase instanceof PurchaseOrder) {
                    continue;
                }
                if ($this->poster->postPurchasePayment($purchase, $payment)) {
                    $posted++;
                }
            } catch (\Throwable $e) {
                $errors[] = 'Purchase payment ' . ($payment->number ?: $payment->id) . ': ' . $e->getMessage();
            }
        }

        foreach ($this->purchaseReturns($companyId) as $return) {
            try {
                if ($this->poster->postPurchaseReturn($return)) {
                    $posted++;
                }
            } catch (\Throwable $e) {
                $errors[] = 'Purchase return ' . ($return->number ?: $return->id) . ': ' . $e->getMessage();
            }
        }

        foreach ($this->expenses($companyId) as $expense) {
            try {
                if ($this->poster->postExpense($expense)) {
                    $posted++;
                }
            } catch (\Throwable $e) {
                $errors[] = 'Expense ' . ($expense->number ?: $expense->id) . ': ' . $e->getMessage();
            }
        }

        return compact('deleted', 'posted', 'errors');
    }

    private function sales(?int $companyId)
    {
        return Sale::query()
            ->with(['payments', 'items.product'])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereIn('status', [Sale::STATUS_COMPLETED, Sale::STATUS_VOIDED, Sale::STATUS_RETURNED])
            ->orderBy('sale_date')
            ->orderBy('id')
            ->get();
    }

    private function creditNotes(?int $companyId)
    {
        return CreditNote::query()
            ->with(['sale.payments', 'items.saleItem.product'])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereIn('status', [CreditNote::STATUS_POSTED, CreditNote::STATUS_COMPLETED])
            ->orderBy('credit_date')
            ->orderBy('id')
            ->get();
    }

    private function goodsReceipts(?int $companyId)
    {
        return GoodsReceipt::query()
            ->with(['items', 'purchaseOrder'])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();
    }

    private function purchasePayments(?int $companyId)
    {
        return Payment::query()
            ->with('payable')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('payable_type', PurchaseOrder::class)
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();
    }

    private function purchaseReturns(?int $companyId)
    {
        return PurchaseReturn::query()
            ->with(['items', 'purchaseOrder.receipts.items'])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('return_date')
            ->orderBy('id')
            ->get();
    }

    private function expenses(?int $companyId)
    {
        return Expense::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where(function ($q) {
                $q->where('status', Expense::STATUS_PAID)
                    ->orWhereColumn('paid_amount', '>=', 'amount');
            })
            ->orderBy('expense_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{0:float,1:float}
     */
    private function receiptAmount(GoodsReceipt $receipt): array
    {
        $net = round((float) $receipt->items->sum(function (GoodsReceiptItem $item) {
            return (float) $item->quantity_received * (float) $item->unit_cost;
        }), 2);

        $po = $receipt->purchaseOrder;
        $tax = 0.0;

        if ($net <= 0 && $po) {
            $tax = round((float) $po->tax_amount, 2);
            $amount = round((float) $po->total, 2);
            if ($tax > 0 && abs((float) $po->total - (float) $po->subtotal) < 0.009) {
                $net = round(max(0, $amount - $tax), 2);
            } else {
                $net = round(max(0, $amount - $tax), 2);
            }

            return [round($net + $tax, 2), $tax];
        }

        if ($po && (float) $po->tax_amount > 0) {
            $poNet = round(max(0, (float) $po->total - (float) $po->tax_amount), 2);
            if (abs($net - $poNet) < 0.05) {
                $tax = round((float) $po->tax_amount, 2);
            }
        }

        return [round($net + $tax, 2), $tax];
    }
}
