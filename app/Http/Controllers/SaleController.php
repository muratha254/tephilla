<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleReturnItem;
use App\Models\SalePaymentPlan;
use App\Models\SalePaymentPlanItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Services\InventoryService;
use App\Services\AccountingPoster;
use App\Services\LoyaltyService;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use PDF;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePermission('sales.view');

        $period = (string) $request->input('period', 'today');
        [$from, $to] = $this->dateRange($period);
        $user = auth()->user();
        $branchId = $this->resolveBranchId(
            $request->filled('branch_id') ? (int) $request->input('branch_id') : null
        );

        $sales = Sale::query()
            ->with(['customer', 'cashier', 'branch'])
            ->whereNotIn('status', [Sale::STATUS_HELD, Sale::STATUS_DRAFT])
            ->when($from && $to, function ($query) use ($from, $to) {
                $query->whereBetween('sale_date', [$from, $to]);
            })
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->get();

        return view('sales.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'sales.index',
            'sales' => $sales,
            'period' => $period,
            'periodOptions' => $this->periodOptions(),
            'filterBranchId' => $branchId,
            'canCreate' => $user->hasPermission('pos.view') || $user->hasPermission('sales.create'),
            'canVoid' => $user->hasPermission('sales.void') || $user->hasPermission('pos.void'),
            'canReturn' => $user->hasPermission('sales.return'),
            'canPrint' => $user->hasPermission('sales.print_invoice')
                || $user->hasPermission('sales.print_receipt')
                || $user->hasPermission('sales.view'),
            'canDelivery' => $user->hasPermission('sales.delivery_note') || $user->hasPermission('sales.view'),
            'canDeletePayment' => $user->hasPermission('payments.delete'),
            'paymentMethods' => config('sellix.payment_methods', []),
        ]));
    }

    public function show(Sale $sale)
    {
        $this->authorizePermission('sales.view');
        abort_if(in_array($sale->status, [Sale::STATUS_HELD, Sale::STATUS_DRAFT], true), 404);

        $sale->load(['items.product.tax', 'customer', 'cashier', 'branch', 'payments.user', 'company', 'quotation', 'paymentPlan.items', 'paymentPlan.salesperson']);
        $user = auth()->user();

        return view('sales.show', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'sales.index',
            'sale' => $sale,
            'customers' => Customer::query()->where('is_active', true)->orderByDesc('is_walk_in')->orderBy('name')->get(),
            'salesPeople' => User::query()
                ->where('is_active', true)
                ->when($user->company_id, function ($query) use ($user) {
                    $query->where('company_id', $user->company_id);
                })
                ->orderBy('name')
                ->get(),
            'paymentMethods' => config('sellix.payment_methods', []),
            'canUpdate' => $user->hasPermission('sales.update') && $sale->status === Sale::STATUS_COMPLETED,
            'canPay' => $user->hasPermission('payments.create') && $sale->status === Sale::STATUS_COMPLETED && $sale->remainingBalance() > 0,
            'canPlan' => $user->hasPermission('payments.create') || $user->hasPermission('sales.update'),
            'canPrint' => $user->hasPermission('sales.print_invoice')
                || $user->hasPermission('sales.print_receipt')
                || $user->hasPermission('sales.view'),
        ]));
    }

    public function storePaymentPlan(Request $request, Sale $sale)
    {
        abort_unless(
            auth()->user()->hasPermission('payments.create') || auth()->user()->hasPermission('sales.update'),
            403
        );
        abort_unless($sale->status === Sale::STATUS_COMPLETED, 422, 'Only a completed sale can have a payment plan.');

        $companyId = (int) auth()->user()->company_id;
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })],
            'type' => 'required|in:regular,irregular',
            'installment_amount' => 'nullable|numeric|min:0.01',
            'due_date' => 'nullable|date',
            'items' => 'nullable|array',
            'items.*.amount' => 'nullable|numeric|min:0.01',
            'items.*.due_date' => 'nullable|date',
        ]);

        $target = $sale->remainingBalance();
        if ($target <= 0) {
            $target = (float) $sale->total;
        }

        if ($data['type'] === SalePaymentPlan::TYPE_REGULAR) {
            $amount = (float) ($data['installment_amount'] ?? 0);
            $due = $data['due_date'] ?? null;
            if ($amount <= 0 || ! $due) {
                return back()->with('error', 'Enter an instalment amount and due date for a regular plan.')->withInput();
            }
            $rows = $this->regularPlanRows($target, $amount, $due);
        } else {
            $rows = collect($data['items'] ?? [])
                ->filter(function ($row) {
                    return (float) ($row['amount'] ?? 0) > 0 && ! empty($row['due_date']);
                })
                ->map(function ($row) {
                    return [
                        'amount' => round((float) $row['amount'], 2),
                        'due_date' => $row['due_date'],
                    ];
                })
                ->values()
                ->all();
        }

        if (! $rows) {
            return back()->with('error', 'Could not generate instalments. Check the amount, due date, or irregular rows.')->withInput();
        }

        DB::transaction(function () use ($sale, $data, $rows, $companyId) {
            $plan = SalePaymentPlan::query()->updateOrCreate(
                ['sale_id' => $sale->id],
                [
                    'company_id' => $companyId,
                    'user_id' => $data['user_id'],
                    'type' => $data['type'],
                    'installment_amount' => (float) ($data['installment_amount'] ?? 0),
                    'due_date' => $data['due_date'] ?? ($rows[0]['due_date'] ?? null),
                ]
            );

            $plan->items()->delete();
            foreach ($rows as $index => $row) {
                SalePaymentPlanItem::query()->create([
                    'company_id' => $companyId,
                    'sale_payment_plan_id' => $plan->id,
                    'sale_id' => $sale->id,
                    'due_date' => $row['due_date'],
                    'amount' => $row['amount'],
                    'status' => 'pending',
                    'sort_order' => $index + 1,
                ]);
            }
        });

        return redirect()->route('sales.show', $sale)->with('success', 'Payment plan generated.');
    }

    /**
     * @return array<int, array{amount:float,due_date:string}>
     */
    private function regularPlanRows(float $target, float $amount, string $dueDate): array
    {
        $rows = [];
        $left = round($target, 2);
        $due = Carbon::parse($dueDate);
        $guard = 0;

        while ($left > 0.009 && $guard < 60) {
            $thisAmount = round(min($amount, $left), 2);
            $rows[] = [
                'amount' => $thisAmount,
                'due_date' => $due->toDateString(),
            ];
            $left = round($left - $thisAmount, 2);
            $due = $due->copy()->addMonth();
            $guard++;
        }

        return $rows;
    }

    public function updateDetails(Request $request, Sale $sale)
    {
        $this->authorizePermission('sales.update');
        abort_unless($sale->status === Sale::STATUS_COMPLETED, 422, 'Only a completed sale can be updated.');

        $companyId = auth()->user()->company_id;
        $data = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })],
            'sale_date' => 'required|date',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $sale->update($data);

        return redirect()->route('sales.show', $sale)->with('success', 'Sale details updated.');
    }

    public function applyDiscount(Request $request, Sale $sale)
    {
        $this->authorizePermission('sales.update');
        abort_unless($sale->status === Sale::STATUS_COMPLETED, 422, 'Only a completed sale can be updated.');

        $max = max(0.01, (float) $sale->subtotal);
        $data = $request->validate([
            'discount_amount' => 'required|numeric|min:0|max:' . $max,
        ]);

        $discount = round((float) $data['discount_amount'], 2);
        $total = round(max(0, (float) $sale->subtotal - $discount), 2);
        $sale->update([
            'discount_amount' => $discount,
            'discount_percent' => 0,
            'total' => $total,
        ]);
        $this->syncPaymentState($sale);

        return redirect()->route('sales.show', $sale)->with('success', 'Discount applied.');
    }

    public function removeVat(Sale $sale)
    {
        $this->authorizePermission('sales.update');
        abort_unless($sale->status === Sale::STATUS_COMPLETED, 422, 'Only a completed sale can be updated.');

        if ((float) $sale->tax_amount <= 0) {
            return redirect()->route('sales.show', $sale)->with('error', 'This sale has no VAT to remove.');
        }

        DB::transaction(function () use ($sale) {
            $sale->load('items');
            $subtotal = 0;
            foreach ($sale->items as $item) {
                $tax = (float) $item->tax_amount;
                $line = round((float) $item->line_total - $tax, 2);
                $item->update([
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                    'line_total' => $line,
                ]);
                $subtotal += $line;
            }

            $discount = min($subtotal, (float) $sale->discount_amount);
            $sale->update([
                'subtotal' => round($subtotal, 2),
                'discount_amount' => $discount,
                'tax_amount' => 0,
                'total' => round(max(0, $subtotal - $discount), 2),
            ]);
        });

        $this->syncPaymentState($sale->fresh());

        return redirect()->route('sales.show', $sale)->with('success', 'VAT removed from this sale.');
    }

    public function storePayment(Request $request, Sale $sale, DocumentNumberService $numbers, AccountingPoster $accounting, AuditLogger $audit)
    {
        $this->authorizePermission('payments.create');
        abort_unless($sale->status === Sale::STATUS_COMPLETED, 422, 'Only a completed sale can take payment.');

        $balance = $sale->remainingBalance();
        if ($balance <= 0) {
            return back()->with('error', 'This sale is already fully paid.');
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . max($balance, 0.01),
            'method' => 'required|in:' . implode(',', array_keys(config('sellix.payment_methods', ['cash' => 'Cash']))),
            'reference' => 'nullable|string|max:64',
            'notes' => 'nullable|string|max:1000',
            'paid_at' => 'required|date',
        ]);

        DB::transaction(function () use ($sale, $numbers, $data, $accounting, $audit) {
            $amount = round((float) $data['amount'], 2);
            $payment = Payment::query()->create([
                'company_id' => $sale->company_id,
                'branch_id' => $sale->branch_id,
                'user_id' => auth()->id(),
                'customer_id' => $sale->customer_id,
                'number' => $numbers->next($sale->company_id, 'payment'),
                'payable_type' => Sale::class,
                'payable_id' => $sale->id,
                'method' => $data['method'],
                'amount' => $amount,
                'reference' => $data['reference'] ?? null,
                'paid_at' => $data['paid_at'],
                'notes' => $data['notes'] ?? null,
            ]);

            $sale->update([
                'paid_amount' => round((float) $sale->paid_amount + $amount, 2),
            ]);

            $accounting->postSalePayment($sale->fresh(), $payment);
            $audit->record('payment', 'sales', $payment, null, [
                'sale_id' => $sale->id,
                'amount' => $amount,
                'method' => $payment->method,
            ]);
        });

        $this->syncPaymentState($sale->fresh());

        return redirect()->route('sales.show', $sale)->with('success', 'Payment saved.');
    }

    public function storePlanPayment(Request $request, Sale $sale, SalePaymentPlanItem $item, DocumentNumberService $numbers)
    {
        $this->authorizePermission('payments.create');
        abort_unless($sale->status === Sale::STATUS_COMPLETED, 422, 'Only a completed sale can take payment.');
        abort_unless((int) $item->sale_id === (int) $sale->id, 404);

        $saleBalance = $sale->remainingBalance();
        $itemBalance = $item->remainingAmount();
        $max = min($saleBalance > 0 ? $saleBalance : $itemBalance, $itemBalance);
        if ($max <= 0) {
            return back()->with('error', 'This instalment is already paid, or the sale has no remaining balance.');
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $max,
            'method' => 'required|in:' . implode(',', array_keys(config('sellix.payment_methods', ['cash' => 'Cash']))),
            'reference' => 'nullable|string|max:64',
            'notes' => 'nullable|string|max:1000',
            'paid_at' => 'required|date',
        ]);

        DB::transaction(function () use ($sale, $item, $numbers, $data) {
            $amount = round((float) $data['amount'], 2);
            Payment::query()->create([
                'company_id' => $sale->company_id,
                'branch_id' => $sale->branch_id,
                'user_id' => auth()->id(),
                'customer_id' => $sale->customer_id,
                'number' => $numbers->next($sale->company_id, 'payment'),
                'payable_type' => Sale::class,
                'payable_id' => $sale->id,
                'method' => $data['method'],
                'amount' => $amount,
                'reference' => $data['reference'] ?? null,
                'paid_at' => $data['paid_at'],
                'notes' => $data['notes'] ?: ('Instalment due ' . optional($item->due_date)->format('d-m-Y')),
            ]);

            $paid = round((float) $item->paid_amount + $amount, 2);
            $item->update([
                'paid_amount' => $paid,
                'status' => $paid + 0.009 >= (float) $item->amount ? 'paid' : 'partial',
            ]);

            $sale->update([
                'paid_amount' => round((float) $sale->paid_amount + $amount, 2),
            ]);
        });

        $this->syncPaymentState($sale->fresh());

        return redirect()->route('sales.show', $sale)->with('success', 'Instalment payment recorded.');
    }

    public function updatePayment(Request $request, Sale $sale, Payment $payment)
    {
        $this->authorizePermission('payments.create');
        abort_unless((int) $payment->payable_id === (int) $sale->id, 404);

        $data = $request->validate([
            'method' => 'required|in:' . implode(',', array_keys(config('sellix.payment_methods', ['cash' => 'Cash']))),
            'notes' => 'nullable|string|max:1000',
            'paid_at' => 'required|date',
            'reference' => 'nullable|string|max:64',
        ]);

        $payment->update($data);

        return redirect()->route('sales.show', $sale)->with('success', 'Payment updated.');
    }

    private function syncPaymentState(Sale $sale): void
    {
        $paid = (float) $sale->paid_amount;
        $total = (float) $sale->total;
        $balance = round(max(0, $total - $paid), 2);

        $sale->update([
            'balance' => $balance,
            'payment_status' => $paid <= 0
                ? Sale::PAYMENT_UNPAID
                : ($balance <= 0.009 ? Sale::PAYMENT_PAID : Sale::PAYMENT_PARTIAL),
        ]);
    }

    public function payments(Sale $sale)
    {
        $this->authorizePermission('sales.view');

        $sale->load(['customer', 'payments.user']);
        $methods = config('sellix.payment_methods', []);
        $customer = $sale->customer;
        $walkIn = ! $customer || $customer->is_walk_in;
        $canDelete = auth()->user()->hasPermission('payments.delete');
        $canPrint = auth()->user()->hasPermission('sales.print_invoice')
            || auth()->user()->hasPermission('sales.print_receipt')
            || auth()->user()->hasPermission('sales.view');

        return response()->json([
            'customer_name' => $walkIn ? 'WALK IN' : $customer->name,
            'mobile' => $walkIn ? '' : (string) $customer->phone,
            'phone' => $walkIn ? '' : (string) $customer->phone,
            'email' => $walkIn ? '' : (string) $customer->email,
            'number' => $sale->documentNumber(),
            'date' => optional($sale->sale_date)->format('d-m-Y') ?: '-',
            'total' => number_format((float) $sale->total, 2),
            'paid' => number_format((float) $sale->paid_amount, 2),
            'due' => number_format($sale->remainingBalance(), 2),
            'payments' => $sale->payments->map(function ($payment) use ($sale, $methods, $canDelete, $canPrint) {
                return [
                    'date' => optional($payment->paid_at)->format('d-m-Y') ?: '-',
                    'amount' => number_format((float) $payment->amount, 2),
                    'method' => $methods[$payment->method] ?? ucfirst((string) $payment->method),
                    'note' => $payment->notes ?: $payment->reference ?: '',
                    'user' => optional($payment->user)->name ?: '-',
                    'pos_url' => $canPrint ? route('sales.payments.print', ['sale' => $sale, 'payment' => $payment, 'mode' => 'pos']) : '',
                    'a4_url' => $canPrint ? route('sales.payments.print', ['sale' => $sale, 'payment' => $payment, 'mode' => 'a4']) : '',
                    'delete_url' => $canDelete ? route('sales.payments.destroy', [$sale, $payment]) : '',
                ];
            })->values(),
        ]);
    }

    public function printPayment(Sale $sale, Payment $payment, Request $request)
    {
        $this->authorizePermission('sales.view');
        abort_unless((int) $payment->payable_id === (int) $sale->id, 404);

        $mode = $request->query('mode', 'a4') === 'pos' ? 'pos' : 'a4';
        $sale->load(['customer', 'cashier', 'company']);
        $payment->load('user');
        $filename = 'payment-' . preg_replace('/[^A-Za-z0-9\-]+/', '-', (string) $payment->number) . '-' . $mode . '.pdf';

        $pdf = PDF::loadView('sales.payment-pdf', array_merge(fleet_shared_view_data(), [
            'sale' => $sale,
            'payment' => $payment,
            'mode' => $mode,
            'profile' => fleet_company_profile(),
            'paymentLabels' => config('sellix.payment_methods', []),
        ]));

        if ($mode === 'pos') {
            $pdf->setPaper([0, 0, 226.77, 600], 'portrait');
        } else {
            $pdf->setPaper('a4', 'portrait');
        }

        return $pdf->download($filename);
    }

    public function destroyPayment(Sale $sale, Payment $payment)
    {
        $this->authorizePermission('payments.delete');
        abort_unless((int) $payment->payable_id === (int) $sale->id, 404);

        $amount = (float) $payment->amount;

        DB::transaction(function () use ($sale, $payment, $amount) {
            $payment->delete();

            $sale->update([
                'paid_amount' => round(max(0, (float) $sale->paid_amount - $amount), 2),
            ]);

            $left = $amount;
            $plan = $sale->paymentPlan;
            $items = $plan ? $plan->items()->orderByDesc('sort_order')->get() : collect();

            foreach ($items as $item) {
                if ($left <= 0) {
                    break;
                }
                $applied = min((float) $item->paid_amount, $left);
                if ($applied <= 0) {
                    continue;
                }
                $paid = round((float) $item->paid_amount - $applied, 2);
                $item->update([
                    'paid_amount' => $paid,
                    'status' => $paid <= 0.009 ? 'pending' : 'partial',
                ]);
                $left = round($left - $applied, 2);
            }
        });

        $this->syncPaymentState($sale->fresh());

        return back()->with('success', 'Payment deleted.');
    }

    public function print(Sale $sale, Request $request)
    {
        $this->authorizePermission('sales.view');
        abort_unless($sale->status === Sale::STATUS_COMPLETED, 404);

        $mode = $request->query('mode', 'a4');
        if (! in_array($mode, ['a4', 'dispatch', 'delivery'], true)) {
            $mode = 'a4';
        }

        if ($mode === 'dispatch') {
            return $this->dispatchPdf($sale);
        }

        if ($mode === 'delivery') {
            return $this->deliveryPdf($sale);
        }

        $sale->load(['items', 'customer', 'cashier', 'branch', 'payments', 'company']);

        return view('sales.print', array_merge(fleet_shared_view_data(), [
            'sale' => $sale,
            'mode' => $mode,
            'profile' => fleet_company_profile(),
            'paymentLabels' => config('sellix.payment_methods', []),
        ]));
    }

    public function dispatchPdf(Sale $sale)
    {
        $this->authorizePermission('sales.view');
        abort_unless($sale->status === Sale::STATUS_COMPLETED, 404);

        $sale->load(['items', 'customer', 'company']);
        $profile = fleet_company_profile();
        $filename = 'dispatch-' . str_pad((string) $sale->id, 5, '0', STR_PAD_LEFT) . '.pdf';

        $pdf = PDF::loadView('sales.dispatch-pdf', array_merge(fleet_shared_view_data(), [
            'sale' => $sale,
            'profile' => $profile,
            'logo_pdf_path' => $profile['logo_pdf_path'] ?? null,
        ]))->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    public function deliveryPdf(Sale $sale)
    {
        abort_unless(
            auth()->user()->hasPermission('sales.delivery_note') || auth()->user()->hasPermission('sales.view'),
            403
        );
        abort_unless($sale->status === Sale::STATUS_COMPLETED, 404);

        $sale->load(['items.product.unit', 'customer', 'company']);
        $profile = fleet_company_profile();
        $filename = 'delivery-' . str_pad((string) $sale->id, 5, '0', STR_PAD_LEFT) . '.pdf';

        $pdf = PDF::loadView('sales.delivery-pdf', array_merge(fleet_shared_view_data(), [
            'sale' => $sale,
            'profile' => $profile,
            'logo_pdf_path' => $profile['logo_pdf_path'] ?? null,
        ]))->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    public function pdf(Sale $sale)
    {
        $this->authorizePermission('sales.view');
        abort_unless($sale->status === Sale::STATUS_COMPLETED, 404);

        $sale->load(['items', 'customer', 'cashier', 'branch', 'payments', 'company']);
        $profile = fleet_company_profile();
        $filename = 'sale-' . preg_replace('/[^A-Za-z0-9\-]+/', '-', $sale->documentNumber()) . '.pdf';

        $pdf = PDF::loadView('sales.pdf', array_merge(fleet_shared_view_data(), [
            'sale' => $sale,
            'profile' => $profile,
            'logo_pdf_path' => $profile['logo_pdf_path'] ?? null,
            'paymentLabels' => config('sellix.payment_methods', []),
        ]))->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    public function posPdf(Sale $sale)
    {
        $this->authorizePermission('sales.view');
        abort_unless($sale->status === Sale::STATUS_COMPLETED, 404);

        $sale->load(['items', 'customer', 'cashier', 'payments', 'company']);
        $filename = 'pos-' . preg_replace('/[^A-Za-z0-9\-]+/', '-', $sale->documentNumber()) . '.pdf';

        $pdf = PDF::loadView('sales.pos-pdf', array_merge(fleet_shared_view_data(), [
            'sale' => $sale,
            'profile' => fleet_company_profile(),
            'paymentLabels' => config('sellix.payment_methods', []),
        ]))->setPaper([0, 0, 255.12, 841.89], 'portrait');

        return $pdf->download($filename);
    }

    public function voids()
    {
        abort_unless(
            auth()->user()->hasPermission('sales.void') || auth()->user()->hasPermission('pos.void'),
            403
        );

        $sales = Sale::query()
            ->with(['customer', 'cashier', 'voidedBy', 'branch'])
            ->where('status', Sale::STATUS_VOIDED)
            ->orderByDesc('voided_at')
            ->orderByDesc('id')
            ->get();

        return view('sales.voids', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'sales.voids',
            'sales' => $sales,
        ]));
    }

    public function void(Request $request, Sale $sale, InventoryService $inventory, AccountingPoster $accounting, LoyaltyService $loyalty, AuditLogger $audit)
    {
        abort_unless(
            auth()->user()->hasPermission('sales.void') || auth()->user()->hasPermission('pos.void'),
            403
        );
        abort_unless($sale->status === Sale::STATUS_COMPLETED, 422, 'Only a completed sale can be cancelled.');

        $data = $request->validate([
            'invoice_date' => 'required|date',
            'receipt_ref' => 'required|string|max:64',
            'want_refund' => 'required|in:0,1',
            'void_reason' => 'required|string|max:500',
            'flagged' => 'nullable|boolean',
        ]);

        $wantRefund = (int) $data['want_refund'] === 1;
        $flagged = $request->boolean('flagged');

        DB::transaction(function () use ($sale, $inventory, $data, $wantRefund, $flagged, $accounting, $loyalty, $audit) {
            $sale->load('items.product');
            $before = $sale->only(['status', 'paid_amount', 'balance', 'total']);

            $returned = SaleReturnItem::query()
                ->selectRaw('sale_item_id, SUM(quantity) as qty')
                ->whereHas('saleReturn', function ($query) use ($sale) {
                    $query->where('sale_id', $sale->id);
                })
                ->groupBy('sale_item_id')
                ->pluck('qty', 'sale_item_id');

            foreach ($sale->items as $item) {
                $product = $item->product;
                if (! $product || ! $product->manage_stock) {
                    continue;
                }

                $restoreQty = round(max(0, (float) $item->quantity - (float) ($returned[$item->id] ?? 0)), 4);
                if ($restoreQty <= 0) {
                    continue;
                }

                $inventory->apply([
                    'company_id' => (int) $sale->company_id,
                    'branch_id' => (int) $sale->branch_id,
                    'product_id' => (int) $item->product_id,
                    'product_variant_id' => (int) $item->product_variant_id,
                    'type' => StockMovement::SALE_VOID,
                    'quantity_in' => $restoreQty,
                    'unit_cost' => $item->cost_price,
                    'user_id' => auth()->id(),
                    'notes' => 'Void ' . $sale->number,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'reference_number' => $sale->number,
                    'occurred_at' => now(),
                ]);
            }

            $update = [
                'status' => Sale::STATUS_VOIDED,
                'voided_at' => now(),
                'voided_by' => auth()->id(),
                'void_reason' => $data['void_reason'],
                'void_refund' => $wantRefund,
                'void_flagged' => $flagged,
            ];

            if ($wantRefund) {
                $update['paid_amount'] = 0;
                $update['balance'] = 0;
                $update['payment_status'] = Sale::PAYMENT_UNPAID;
            }

            $sale->update($update);
            $accounting->postSaleVoid($sale->fresh());
            $loyalty->reverseForSale($sale->fresh());
            $audit->record('void', 'sales', $sale, $before, $sale->only(['status', 'void_reason', 'paid_amount', 'balance']));
        });

        return redirect()
            ->route('sales.voids')
            ->with('success', 'Sale ' . $sale->documentNumber() . ' was cancelled.');
    }

    /**
     * @return array{0:?\Illuminate\Support\Carbon,1:?\Illuminate\Support\Carbon}
     */
    private function dateRange(string $period): array
    {
        $tz = config('app.timezone');
        $now = Carbon::now($tz);

        if ($period === 'yesterday') {
            $day = $now->copy()->subDay();

            return [$day->copy()->startOfDay(), $day->copy()->endOfDay()];
        }

        if ($period === 'last7') {
            return [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()];
        }

        if ($period === 'month') {
            return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
        }

        if ($period === 'year') {
            return [$now->copy()->startOfYear(), $now->copy()->endOfYear()];
        }

        if ($period === 'all') {
            return [null, null];
        }

        return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
    }

    /**
     * @return array<string, string>
     */
    private function periodOptions(): array
    {
        $tz = config('app.timezone');
        $now = Carbon::now($tz);

        return [
            'today' => 'Today ' . $now->format('d-m-Y'),
            'yesterday' => 'Yesterday ' . $now->copy()->subDay()->format('d-m-Y'),
            'last7' => 'Last 7 Days',
            'month' => 'This Month',
            'year' => 'This Year',
            'all' => 'All Dates',
        ];
    }
}
