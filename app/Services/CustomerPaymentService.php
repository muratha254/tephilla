<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class CustomerPaymentService
{
    protected DocumentNumberService $numbers;
    protected AccountingPoster $accounting;

    public function __construct(DocumentNumberService $numbers, AccountingPoster $accounting)
    {
        $this->numbers = $numbers;
        $this->accounting = $accounting;
    }

    /**
     * Apply a customer receipt FIFO against unpaid sales, then opening balance.
     *
     * @return array{applied:float,payments:array<int,Payment>}
     */
    public function apply(
        Customer $customer,
        float $amount,
        string $method,
        $paidAt,
        ?string $reference = null,
        ?string $notes = null,
        $branchId = null
    ): array {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $due = $customer->creditAmount();
        if ($amount > $due + 0.009) {
            throw new \InvalidArgumentException('Payment cannot exceed the outstanding balance of Ksh ' . number_format($due, 2) . '.');
        }

        $companyId = (int) $customer->company_id;
        $branchId = $branchId ?: ($customer->branch_id ?: null);
        $number = $this->numbers->next($companyId, 'payment');
        $payments = [];

        DB::transaction(function () use (
            $customer, $amount, $method, $paidAt, $reference, $notes,
            $companyId, $branchId, $number, &$payments
        ) {
            $remaining = $amount;
            $sales = Sale::query()
                ->where('customer_id', $customer->id)
                ->where('status', Sale::STATUS_COMPLETED)
                ->where('balance', '>', 0)
                ->orderBy('sale_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($sales as $sale) {
                if ($remaining <= 0) {
                    break;
                }

                $due = (float) $sale->balance;
                $pay = round(min($due, $remaining), 2);
                if ($pay <= 0) {
                    continue;
                }

                $paid = round((float) $sale->paid_amount + $pay, 2);
                $balance = round(max(0, (float) $sale->total - $paid), 2);
                $status = Sale::PAYMENT_UNPAID;
                if ($balance <= 0.009) {
                    $status = Sale::PAYMENT_PAID;
                    $balance = 0;
                } elseif ($paid > 0) {
                    $status = Sale::PAYMENT_PARTIAL;
                }

                $sale->update([
                    'paid_amount' => $paid,
                    'balance' => $balance,
                    'payment_status' => $status,
                ]);

                $payment = Payment::query()->create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId ?: $sale->branch_id,
                    'user_id' => auth()->id(),
                    'customer_id' => $customer->id,
                    'number' => $number,
                    'payable_type' => Sale::class,
                    'payable_id' => $sale->id,
                    'method' => $method,
                    'amount' => $pay,
                    'reference' => $reference,
                    'paid_at' => $paidAt,
                    'notes' => $notes,
                ]);

                $this->accounting->postSalePayment($sale->fresh(), $payment);
                $payments[] = $payment;
                $remaining = round($remaining - $pay, 2);
            }

            if ($remaining > 0) {
                $customer->refresh();
                $open = round((float) $customer->opening_balance, 2);
                $againstOpen = round(min(max(0, $open), $remaining), 2);
                if ($againstOpen > 0) {
                    $customer->decrement('opening_balance', $againstOpen);
                    $payment = Payment::query()->create([
                        'company_id' => $companyId,
                        'branch_id' => $branchId,
                        'user_id' => auth()->id(),
                        'customer_id' => $customer->id,
                        'number' => $number,
                        'payable_type' => Customer::class,
                        'payable_id' => $customer->id,
                        'method' => $method,
                        'amount' => $againstOpen,
                        'reference' => $reference,
                        'paid_at' => $paidAt,
                        'notes' => $notes,
                    ]);
                    $this->accounting->postCustomerArPayment($customer->fresh(), $payment);
                    $payments[] = $payment;
                    $remaining = round($remaining - $againstOpen, 2);
                }

                // Overpayment after clearing all debts becomes a credit (negative opening balance).
                if ($remaining > 0) {
                    $customer->decrement('opening_balance', $remaining);
                    $remaining = 0;
                }
            }
        });

        return [
            'applied' => $amount,
            'payments' => $payments,
        ];
    }
}
