<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use App\Services\DocumentNumberService;
use App\Services\AccountingPoster;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePermission('expenses.view');

        $form = $request->input('form', 'all');
        $query = Expense::query()
            ->with(['category', 'vendor', 'user'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        if (in_array($form, [Expense::TYPE_DIRECT, Expense::TYPE_BILL], true)) {
            $query->where('entry_type', $form);
        }

        return view('expenses.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'expenses.index',
            'expenses' => $query->get(),
            'form' => $form,
            'canCreate' => auth()->user()->hasPermission('expenses.create'),
            'canUpdate' => auth()->user()->hasPermission('expenses.update'),
            'canDelete' => auth()->user()->hasPermission('expenses.delete'),
        ]));
    }

    public function create(Request $request)
    {
        $this->authorizePermission('expenses.create');

        $this->ensureCategories();

        $type = $request->input('type', Expense::TYPE_DIRECT);
        if (! in_array($type, [Expense::TYPE_DIRECT, Expense::TYPE_BILL], true)) {
            $type = Expense::TYPE_DIRECT;
        }

        return view('expenses.form', array_merge(fleet_shared_view_data(), $this->formData(new Expense([
            'expense_date' => now()->toDateString(),
            'entry_type' => $type,
            'vat_type' => Expense::VAT_EXEMPT,
            'amount' => null,
            'paid_amount' => 0,
            'status' => $type === Expense::TYPE_BILL ? Expense::STATUS_UNPAID : Expense::STATUS_PAID,
        ]))));
    }

    public function store(Request $request, DocumentNumberService $numbers, AccountingPoster $accounting, AuditLogger $audit)
    {
        $this->authorizePermission('expenses.create');

        $data = $this->validated($request);
        $data['company_id'] = auth()->user()->company_id;
        $data['branch_id'] = $this->currentBranchId();
        $data['user_id'] = auth()->id();
        $data['number'] = $numbers->next((int) $data['company_id'], 'expense');
        $data['attachment_path'] = $this->storeAttachment($request);

        DB::transaction(function () use ($data, $accounting, $audit) {
            $expense = Expense::query()->create($data);
            if (($expense->status ?? null) === Expense::STATUS_PAID || (float) $expense->paid_amount >= (float) $expense->amount) {
                $accounting->postExpense($expense);
            }
            $audit->record('create', 'expenses', $expense, null, $expense->only(['number', 'amount', 'status', 'payment_method']));
        });

        return redirect()->route('expenses.index')->with('success', 'Expense saved.');
    }

    public function edit(Expense $expense)
    {
        $this->authorizePermission('expenses.update');

        $this->ensureCategories();

        return view('expenses.form', array_merge(fleet_shared_view_data(), $this->formData($expense)));
    }

    public function update(Request $request, Expense $expense)
    {
        $this->authorizePermission('expenses.update');

        $data = $this->validated($request, $expense);
        $path = $this->storeAttachment($request);
        if ($path) {
            $this->deleteAttachment($expense->attachment_path);
            $data['attachment_path'] = $path;
        }

        $expense->update($data);

        return redirect()->route('expenses.index')->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense)
    {
        $this->authorizePermission('expenses.delete');

        $this->deleteAttachment($expense->attachment_path);
        $expense->delete();

        return redirect()->route('expenses.index')->with('success', 'Expense deleted.');
    }

    public function pay(Expense $expense, AccountingPoster $accounting, AuditLogger $audit)
    {
        $this->authorizePermission('expenses.update');

        if ($expense->isPaid()) {
            return back()->with('error', 'This expense is already paid.');
        }

        DB::transaction(function () use ($expense, $accounting, $audit) {
            $before = $expense->only(['status', 'paid_amount']);
            $expense->update([
                'paid_amount' => $expense->amount,
                'status' => Expense::STATUS_PAID,
                'paying_account' => $expense->paying_account ?: $expense->payment_method ?: 'cash',
                'payment_method' => $expense->payment_method ?: $expense->paying_account ?: 'cash',
            ]);
            $accounting->postExpense($expense->fresh());
            $audit->record('pay', 'expenses', $expense, $before, $expense->only(['status', 'paid_amount']));
        });

        return back()->with('success', 'Expense marked as paid.');
    }

    public function storeCategory(Request $request)
    {
        $this->authorizePermission('expenses.create');

        $companyId = auth()->user()->company_id;
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('expense_categories', 'name')->where(function ($query) use ($companyId) {
                    $query->where('company_id', $companyId);
                }),
            ],
        ]);

        $category = ExpenseCategory::query()->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'is_active' => true,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['id' => $category->id, 'name' => $category->name, 'label' => $category->name]);
        }

        return back()->with('success', 'Expense category saved.');
    }

    private function validated(Request $request, ?Expense $expense = null): array
    {
        $companyId = auth()->user()->company_id;
        $type = $request->input('entry_type', Expense::TYPE_DIRECT);
        $isBill = $type === Expense::TYPE_BILL;
        $accounts = array_keys(config('sellix.payment_methods', []));

        $data = $request->validate([
            'entry_type' => ['required', Rule::in([Expense::TYPE_DIRECT, Expense::TYPE_BILL])],
            'expense_date' => 'required|date',
            'expense_category_id' => [
                'required',
                Rule::exists('expense_categories', 'id')->where(function ($query) use ($companyId) {
                    $query->where('company_id', $companyId);
                }),
            ],
            'vendor_id' => [
                $isBill ? 'required' : 'nullable',
                Rule::exists('suppliers', 'id')->where(function ($query) use ($companyId) {
                    $query->where('company_id', $companyId);
                }),
            ],
            'paying_account' => [$isBill ? 'nullable' : 'required', Rule::in($accounts)],
            'amount' => 'required|numeric|min:0.01',
            'voucher_no' => 'nullable|string|max:100',
            'vat_type' => ['required', Rule::in([Expense::VAT_EXEMPT, Expense::VAT_EXCLUSIVE, Expense::VAT_INCLUSIVE])],
            'notes' => 'required|string|max:2000',
            'attachment' => 'nullable|file|max:5120',
        ]);

        $amount = round((float) $data['amount'], 2);
        $vatType = $data['vat_type'];
        $rate = ((float) config('sellix.default_tax_rate', 16)) / 100;
        $vatAmount = 0.0;
        if ($vatType === Expense::VAT_EXCLUSIVE) {
            $vatAmount = round($amount * $rate, 2);
        } elseif ($vatType === Expense::VAT_INCLUSIVE) {
            $vatAmount = round($amount - ($amount / (1 + $rate)), 2);
        }

        $account = $data['paying_account'] ?? null;
        $paidAmount = $expense ? (float) $expense->paid_amount : 0.0;
        if (! $expense) {
            $paidAmount = $isBill && empty($account) ? 0.0 : $amount;
        } elseif ($expense->isPaid()) {
            $paidAmount = $amount;
        } else {
            $paidAmount = min($paidAmount, $amount);
        }

        $status = Expense::STATUS_UNPAID;
        if ($paidAmount >= $amount) {
            $status = Expense::STATUS_PAID;
        } elseif ($paidAmount > 0) {
            $status = Expense::STATUS_PARTIAL;
        }

        $category = ExpenseCategory::query()->find($data['expense_category_id']);

        return [
            'entry_type' => $data['entry_type'],
            'expense_date' => $data['expense_date'],
            'expense_category_id' => $data['expense_category_id'],
            'vendor_id' => $data['vendor_id'] ?? null,
            'paying_account' => $account,
            'payment_method' => $account,
            'amount' => $amount,
            'paid_amount' => $paidAmount,
            'vat_type' => $vatType,
            'vat_amount' => $vatAmount,
            'voucher_no' => $data['voucher_no'] ?? null,
            'notes' => $data['notes'],
            'description' => $data['notes'] ?: optional($category)->name ?: 'Expense',
            'status' => $status,
        ];
    }

    private function formData(Expense $expense): array
    {
        $taxRate = (int) config('sellix.default_tax_rate', 16);

        return [
            'activeMenu' => $expense->exists ? 'expenses.index' : 'expenses.create',
            'expense' => $expense,
            'isBill' => $expense->entry_type === Expense::TYPE_BILL,
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'vendors' => Supplier::query()->orderBy('name')->get(),
            'accounts' => config('sellix.payment_methods', []),
            'vatTypes' => [
                Expense::VAT_EXEMPT => 'Tax exempt',
                Expense::VAT_EXCLUSIVE => $taxRate . '% Exclusive',
                Expense::VAT_INCLUSIVE => $taxRate . '% Inclusive',
            ],
        ];
    }

    private function ensureCategories(): void
    {
        if (ExpenseCategory::query()->exists()) {
            return;
        }

        $companyId = auth()->user()->company_id;
        foreach (ExpenseCategory::defaultNames() as $name) {
            ExpenseCategory::query()->create([
                'company_id' => $companyId,
                'name' => $name,
                'is_active' => true,
            ]);
        }
    }

    private function storeAttachment(Request $request): ?string
    {
        if (! $request->hasFile('attachment')) {
            return null;
        }

        return $request->file('attachment')->store('expenses', 'public');
    }

    private function deleteAttachment(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
