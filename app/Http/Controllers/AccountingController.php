<?php

namespace App\Http\Controllers;

use App\Models\AccountSubType;
use App\Models\AccountType;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Customer;
use App\Models\LedgerAccount;
use App\Models\MoneyEntry;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
use App\Services\AccountingCatalog;
use App\Services\AccountingLedger;
use App\Services\DocumentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PDF;

class AccountingController extends Controller
{
    public function __construct(
        private AccountingCatalog $catalog,
        private AccountingLedger $ledger
    ) {
    }

    public function types()
    {
        $this->authorizeAccounting();
        $this->catalog->ensure((int) auth()->user()->company_id);

        return $this->page('accounting.types', 'accounting.types', [
            'types' => AccountType::query()->orderBy('code')->get(),
        ]);
    }

    public function subTypes()
    {
        $this->authorizeAccounting();
        $this->catalog->ensure((int) auth()->user()->company_id);

        return $this->page('accounting.sub-types', 'accounting.sub-types', [
            'subTypes' => AccountSubType::query()->with('accountType')->orderBy('code')->get(),
            'accountTypes' => AccountType::query()->orderBy('code')->get(),
            'canManage' => $this->canManage(),
        ]);
    }

    public function storeSubType(Request $request)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $this->catalog->ensure($companyId);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'account_type_id' => ['required', Rule::exists('account_types', 'id')->where('company_id', $companyId)],
            'description' => 'nullable|string|max:500',
        ]);

        AccountSubType::query()->create([
            'company_id' => $companyId,
            'account_type_id' => $data['account_type_id'],
            'code' => $this->catalog->nextSubTypeCode($companyId),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('accounting.sub-types')->with('success', 'Sub account type saved.');
    }

    public function updateSubType(Request $request, AccountSubType $subType)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'account_type_id' => ['required', Rule::exists('account_types', 'id')->where('company_id', $companyId)],
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $subType->update([
            'name' => $data['name'],
            'account_type_id' => $data['account_type_id'],
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active', $subType->is_active),
        ]);

        return redirect()->route('accounting.sub-types')->with('success', 'Sub account type updated.');
    }

    public function destroySubType(AccountSubType $subType)
    {
        $this->authorizeManage();

        if ($subType->accounts()->exists()) {
            return back()->with('error', 'This sub account type is in use.');
        }

        $subType->delete();

        return redirect()->route('accounting.sub-types')->with('success', 'Sub account type deleted.');
    }

    public function chart()
    {
        $this->authorizeAccounting();
        $this->catalog->ensure((int) auth()->user()->company_id);

        return $this->page('accounting.chart', 'accounting.chart', [
            'accounts' => LedgerAccount::query()->with(['subType.accountType'])->orderBy('gl_code')->get(),
            'subTypes' => AccountSubType::query()->with('accountType')->where('is_active', true)->orderBy('name')->get(),
            'nextGl' => $this->catalog->nextGlCode((int) auth()->user()->company_id),
            'canManage' => $this->canManage(),
        ]);
    }

    public function storeChart(Request $request)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'account_sub_type_id' => ['required', Rule::exists('account_sub_types', 'id')->where('company_id', $companyId)],
            'description' => 'nullable|string|max:500',
        ]);

        LedgerAccount::query()->create([
            'company_id' => $companyId,
            'account_sub_type_id' => $data['account_sub_type_id'],
            'name' => $data['name'],
            'gl_code' => $this->catalog->nextGlCode($companyId),
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('accounting.chart')->with('success', 'Chart of account saved.');
    }

    public function updateChart(Request $request, LedgerAccount $account)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'account_sub_type_id' => ['required', Rule::exists('account_sub_types', 'id')->where('company_id', $companyId)],
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $account->update([
            'name' => $data['name'],
            'account_sub_type_id' => $data['account_sub_type_id'],
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active', $account->is_active),
        ]);

        return redirect()->route('accounting.chart')->with('success', 'Account updated.');
    }

    public function destroyChart(LedgerAccount $account)
    {
        $this->authorizeManage();

        if ($account->journalLines()->exists() || $account->moneyEntries()->exists()) {
            return back()->with('error', 'This account has transactions and cannot be deleted.');
        }

        $account->delete();

        return redirect()->route('accounting.chart')->with('success', 'Account deleted.');
    }

    public function balances()
    {
        $this->authorizeAccounting();
        $this->catalog->ensure((int) auth()->user()->company_id);

        return $this->page('accounting.balances', 'accounting.balances', [
            'accounts' => $this->ledger->accountsWithBalances(),
            'canManage' => $this->canManage(),
        ]);
    }

    public function statement(Request $request)
    {
        $this->authorizeAccounting();
        $this->catalog->ensure((int) auth()->user()->company_id);

        $data = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'account_id' => ['required', Rule::exists('ledger_accounts', 'id')->where('company_id', auth()->user()->company_id)],
        ]);

        $account = LedgerAccount::query()->with('subType.accountType')->findOrFail($data['account_id']);
        $openingDate = date('Y-m-d', strtotime($data['from'] . ' -1 day'));
        $opening = $this->ledger->accountBalance($account, $openingDate);
        $rows = $this->ledger->combinedGl($data['from'], $data['to'], (int) $account->id);
        $closing = $this->ledger->accountBalance($account, $data['to']);

        return $this->page('accounting.statement', 'accounting.balances', [
            'account' => $account,
            'from' => $data['from'],
            'to' => $data['to'],
            'opening' => $opening,
            'closing' => $closing,
            'rows' => $rows,
        ]);
    }

    public function money(Request $request)
    {
        $this->authorizeAccounting();
        $this->catalog->ensure((int) auth()->user()->company_id);

        $tab = $request->input('tab', 'payments');
        $query = MoneyEntry::query()->with(['account', 'user', 'branch'])->orderByDesc('payment_date')->orderByDesc('id');

        if ($tab === 'refunds') {
            $query->where('type', MoneyEntry::TYPE_REFUND);
        } elseif ($tab === 'cheques') {
            $query->where('type', MoneyEntry::TYPE_UNPAID_CHEQUE);
        } elseif ($tab === 'fines') {
            $query->where('type', MoneyEntry::TYPE_CHEQUE_FINE);
        } elseif ($tab === 'open') {
            $query->where('type', MoneyEntry::TYPE_OPEN_BAL);
        } elseif ($tab === 'transfer') {
            $query->where('type', MoneyEntry::TYPE_TRANSFER);
        }

        $entries = $query->get();

        $suppliers = Supplier::query()->orderBy('name')->get()->map(function (Supplier $supplier) {
            return [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'due' => $supplier->currentBalance(),
            ];
        });

        $customers = Customer::query()
            ->withSum(['sales as credit_sales' => function ($query) {
                $query->where('status', Sale::STATUS_COMPLETED);
            }], 'balance')
            ->orderBy('name')
            ->get()
            ->map(function (Customer $customer) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'due' => $customer->creditAmount(),
                ];
            });

        return $this->page('accounting.money', 'accounting.money', [
            'tab' => $tab,
            'entries' => $entries,
            'accounts' => LedgerAccount::query()->where('is_active', true)->orderBy('name')->get(),
            'suppliers' => $suppliers,
            'customers' => $customers,
            'payModes' => config('sellix.payment_methods', []),
            'chequeTypes' => config('sellix.cheque_types', []),
            'canManage' => $this->canManage(),
            'selectedBranchId' => $this->currentBranchId(),
            'totalIn' => (float) $entries->sum('amount_in'),
            'totalOut' => (float) $entries->sum('amount_out'),
        ]);
    }

    public function storeMoney(Request $request, DocumentNumberService $numbers)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $type = $request->input('type', MoneyEntry::TYPE_PAYMENT);

        $rules = [
            'type' => ['required', Rule::in([
                MoneyEntry::TYPE_PAYMENT, MoneyEntry::TYPE_RECEIVE, MoneyEntry::TYPE_OPEN_BAL,
                MoneyEntry::TYPE_TRANSFER, MoneyEntry::TYPE_REFUND, MoneyEntry::TYPE_UNPAID_CHEQUE,
                MoneyEntry::TYPE_CHEQUE_FINE,
            ])],
            'ledger_account_id' => ['required', Rule::exists('ledger_accounts', 'id')->where('company_id', $companyId)],
            'payment_date' => 'required|date',
            'amount' => [Rule::requiredIf($type !== MoneyEntry::TYPE_OPEN_BAL), 'nullable', 'numeric', 'min:0.01'],
            'debit' => 'nullable|numeric|min:0',
            'credit' => 'nullable|numeric|min:0',
            'pay_mode' => 'nullable|string|max:32',
            'voucher_no' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'counterpart_account_id' => ['nullable', Rule::exists('ledger_accounts', 'id')->where('company_id', $companyId)],
        ];

        if ($type === MoneyEntry::TYPE_TRANSFER) {
            $rules['counterpart_account_id'] = ['required', 'different:ledger_account_id', Rule::exists('ledger_accounts', 'id')->where('company_id', $companyId)];
            $rules['description'] = 'required|string|max:2000';
        }

        if ($type === MoneyEntry::TYPE_PAYMENT) {
            $rules['supplier_id'] = ['required', Rule::exists('suppliers', 'id')->where('company_id', $companyId)];
            $rules['branch_id'] = ['required', Rule::exists('branches', 'id')->where('company_id', $companyId)];
            $rules['description'] = 'required|string|max:500';
        }

        if ($type === MoneyEntry::TYPE_RECEIVE) {
            $rules['customer_id'] = ['required', Rule::exists('customers', 'id')->where('company_id', $companyId)];
            $rules['branch_id'] = ['required', Rule::exists('branches', 'id')->where('company_id', $companyId)];
            $rules['description'] = 'nullable|string|max:2000';
        }

        if ($type === MoneyEntry::TYPE_OPEN_BAL) {
            $rules['branch_id'] = ['required', Rule::exists('branches', 'id')->where('company_id', $companyId)];
        }

        if ($type === MoneyEntry::TYPE_UNPAID_CHEQUE) {
            $rules['customer_id'] = ['required', Rule::exists('customers', 'id')->where('company_id', $companyId)];
            $rules['branch_id'] = ['required', Rule::exists('branches', 'id')->where('company_id', $companyId)];
            $rules['pay_mode'] = ['required', Rule::in(array_keys(config('sellix.cheque_types', ['incoming' => 'Incoming', 'outgoing' => 'Outgoing'])))];
            $rules['description'] = 'required|string|max:2000';
        }

        $data = $request->validate($rules);
        $debit = round((float) ($data['debit'] ?? 0), 2);
        $credit = round((float) ($data['credit'] ?? 0), 2);
        if ($type === MoneyEntry::TYPE_OPEN_BAL && (($debit <= 0 && $credit <= 0) || ($debit > 0 && $credit > 0))) {
            return back()->with('error', 'Enter either a debit or a credit amount.')->withInput();
        }

        $amount = $type === MoneyEntry::TYPE_OPEN_BAL ? max($debit, $credit) : round((float) $data['amount'], 2);
        $number = $numbers->next($companyId, 'payment');

        $in = in_array($type, [MoneyEntry::TYPE_RECEIVE], true) ? $amount : 0;
        $out = in_array($type, [MoneyEntry::TYPE_PAYMENT, MoneyEntry::TYPE_REFUND, MoneyEntry::TYPE_UNPAID_CHEQUE, MoneyEntry::TYPE_CHEQUE_FINE], true) ? $amount : 0;
        if ($type === MoneyEntry::TYPE_TRANSFER) {
            $out = $amount;
        }
        if ($type === MoneyEntry::TYPE_OPEN_BAL) {
            $in = $debit;
            $out = $credit;
        }

        $payload = [
            'company_id' => $companyId,
            'branch_id' => $data['branch_id'] ?? $this->currentBranchId(),
            'user_id' => auth()->id(),
            'ledger_account_id' => $data['ledger_account_id'],
            'counterpart_account_id' => $data['counterpart_account_id'] ?? null,
            'supplier_id' => $data['supplier_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'type' => $type,
            'number' => $number,
            'voucher_no' => $data['voucher_no'] ?? null,
            'pay_mode' => $data['pay_mode'] ?? optional(LedgerAccount::query()->find($data['ledger_account_id']))->name,
            'payment_date' => $data['payment_date'],
            'description' => $data['description'] ?? null,
            'amount_in' => $in,
            'amount_out' => $out,
        ];

        if ($type === MoneyEntry::TYPE_TRANSFER) {
            $group = (string) Str::uuid();
            $payload['transfer_group'] = $group;
            DB::transaction(function () use ($payload, $data, $amount) {
                MoneyEntry::query()->create($payload);
                MoneyEntry::query()->create(array_merge($payload, [
                    'ledger_account_id' => $data['counterpart_account_id'],
                    'counterpart_account_id' => $data['ledger_account_id'],
                    'amount_in' => $amount,
                    'amount_out' => 0,
                ]));
            });
        } else {
            DB::transaction(function () use ($payload, $type, $data, $amount, $number, $in, $out) {
                MoneyEntry::query()->create($payload);
                if ($type === MoneyEntry::TYPE_OPEN_BAL) {
                    LedgerAccount::query()->whereKey($data['ledger_account_id'])->increment('opening_balance', $in - $out);
                }
                if ($type === MoneyEntry::TYPE_PAYMENT && ! empty($data['supplier_id'])) {
                    $this->applySupplierPayment(
                        (int) $data['supplier_id'],
                        $amount,
                        $payload['branch_id'],
                        $payload['pay_mode'] ?: 'cash',
                        $payload['voucher_no'],
                        $payload['description'],
                        $number,
                        $payload['payment_date']
                    );
                }
                if ($type === MoneyEntry::TYPE_RECEIVE && ! empty($data['customer_id'])) {
                    $this->applyCustomerPayment(
                        (int) $data['customer_id'],
                        $amount,
                        $payload['branch_id'],
                        $payload['pay_mode'] ?: 'cash',
                        $payload['voucher_no'],
                        $payload['description'],
                        $number,
                        $payload['payment_date']
                    );
                }
                if ($type === MoneyEntry::TYPE_UNPAID_CHEQUE && ! empty($data['customer_id'])) {
                    $customer = Customer::query()->find($data['customer_id']);
                    if ($customer) {
                        $customer->increment('opening_balance', $amount);
                    }
                }
            });
        }

        $stay = [
            MoneyEntry::TYPE_PAYMENT => 'make',
            MoneyEntry::TYPE_RECEIVE => 'receive',
            MoneyEntry::TYPE_OPEN_BAL => 'open',
            MoneyEntry::TYPE_TRANSFER => 'transfer',
            MoneyEntry::TYPE_UNPAID_CHEQUE => 'cheques',
            MoneyEntry::TYPE_CHEQUE_FINE => 'fines',
        ];

        return redirect()->route('accounting.money', ['tab' => $stay[$type] ?? ([
            MoneyEntry::TYPE_REFUND => 'refunds',
            MoneyEntry::TYPE_UNPAID_CHEQUE => 'cheques',
            MoneyEntry::TYPE_CHEQUE_FINE => 'fines',
        ][$type] ?? 'payments')])->with('success', 'Transaction saved.');
    }

    public function journal()
    {
        $this->authorizeAccounting();
        $this->catalog->ensure((int) auth()->user()->company_id);

        $lines = JournalLine::query()
            ->with(['account', 'entry'])
            ->whereHas('entry')
            ->orderByDesc('id')
            ->get();

        return $this->page('accounting.journal', 'accounting.journal', [
            'lines' => $lines,
            'accounts' => LedgerAccount::query()->where('is_active', true)->orderBy('name')->get(),
            'canManage' => $this->canManage(),
            'selectedBranchId' => $this->currentBranchId(),
        ]);
    }

    public function createJournal()
    {
        $this->authorizeManage();
        $this->catalog->ensure((int) auth()->user()->company_id);

        return $this->page('accounting.journal-form', 'accounting.journal', [
            'accounts' => LedgerAccount::query()->where('is_active', true)->orderBy('name')->get(),
            'canManage' => true,
        ]);
    }

    public function storeJournal(Request $request, DocumentNumberService $numbers)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;

        $data = $request->validate([
            'entry_date' => 'required|date',
            'debit_account_id' => ['required', 'different:credit_account_id', Rule::exists('ledger_accounts', 'id')->where('company_id', $companyId)],
            'credit_account_id' => ['required', Rule::exists('ledger_accounts', 'id')->where('company_id', $companyId)],
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:2000',
        ]);

        $amount = round((float) $data['amount'], 2);

        DB::transaction(function () use ($data, $amount, $companyId, $numbers) {
            $entry = JournalEntry::query()->create([
                'company_id' => $companyId,
                'branch_id' => $this->currentBranchId(),
                'user_id' => auth()->id(),
                'number' => $numbers->next($companyId, 'journal'),
                'entry_date' => $data['entry_date'],
                'description' => $data['description'],
            ]);
            JournalLine::query()->create([
                'journal_entry_id' => $entry->id,
                'ledger_account_id' => $data['debit_account_id'],
                'debit' => $amount,
                'credit' => 0,
                'description' => $data['description'],
            ]);
            JournalLine::query()->create([
                'journal_entry_id' => $entry->id,
                'ledger_account_id' => $data['credit_account_id'],
                'debit' => 0,
                'credit' => $amount,
                'description' => $data['description'],
            ]);
        });

        return redirect()->route('accounting.journal')->with('success', 'Journal saved.');
    }

    public function storeMultipleJournal(Request $request, DocumentNumberService $numbers)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;

        $data = $request->validate([
            'entry_date' => 'required|date',
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'lines' => 'required|array|min:1',
            'lines.*.ledger_account_id' => ['nullable', Rule::exists('ledger_accounts', 'id')->where('company_id', $companyId)],
            'lines.*.amount' => 'nullable|numeric|min:0',
            'lines.*.side' => ['nullable', Rule::in(['debit', 'credit'])],
            'lines.*.description' => 'nullable|string|max:500',
        ]);

        $lines = collect($data['lines'])->filter(function ($line) {
            return ! empty($line['ledger_account_id'])
                && ! empty($line['side'])
                && ((float) ($line['amount'] ?? 0)) > 0;
        })->map(function ($line) {
            $amount = round((float) $line['amount'], 2);
            $line['debit'] = $line['side'] === 'debit' ? $amount : 0;
            $line['credit'] = $line['side'] === 'credit' ? $amount : 0;
            return $line;
        });

        if ($lines->count() < 2) {
            return back()->with('error', 'Add at least two journal lines.')->withInput();
        }

        $debit = round($lines->sum(fn ($line) => (float) $line['debit']), 2);
        $credit = round($lines->sum(fn ($line) => (float) $line['credit']), 2);
        if ($debit !== $credit) {
            return back()->with('error', 'Debit and credit totals must match.')->withInput();
        }

        $headerNote = $lines->pluck('description')->filter()->unique()->implode('; ') ?: null;

        DB::transaction(function () use ($data, $lines, $companyId, $numbers, $headerNote) {
            $entry = JournalEntry::query()->create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'],
                'user_id' => auth()->id(),
                'number' => $numbers->next($companyId, 'journal'),
                'entry_date' => $data['entry_date'],
                'description' => $headerNote,
            ]);
            foreach ($lines as $line) {
                JournalLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'ledger_account_id' => $line['ledger_account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'description' => $line['description'] ?? null,
                ]);
            }
        });

        return redirect()->route('accounting.journal')->with('success', 'Journal saved.');
    }

    public function destroyJournal(JournalEntry $journal)
    {
        $this->authorizeManage();
        $journal->delete();

        return redirect()->route('accounting.journal')->with('success', 'Journal deleted.');
    }

    public function profitLoss(Request $request)
    {
        $this->authorizeAccounting();
        $this->catalog->ensure((int) auth()->user()->company_id);

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $generated = $request->boolean('show');

        return $this->page('accounting.profit-loss', 'accounting.profit-loss', [
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'report' => $generated ? $this->ledger->profitAndLoss($from, $to) : null,
        ]);
    }

    public function profitLossPdf(Request $request)
    {
        $this->authorizeAccounting();
        $this->catalog->ensure((int) auth()->user()->company_id);

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $report = $this->ledger->profitAndLoss($from, $to);

        $pdf = PDF::loadView('accounting.profit-loss-pdf', array_merge(fleet_shared_view_data(), [
            'from' => $from,
            'to' => $to,
            'report' => $report,
        ]))->setPaper('a4', 'portrait');

        $filename = 'profit-loss-' . $from . '-to-' . $to . '.pdf';
        $output = $pdf->output();

        return response($output, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => strlen($output),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function balanceSheet(Request $request)
    {
        $this->authorizeAccounting();
        $this->catalog->ensure((int) auth()->user()->company_id);

        $asOf = $request->input('as_of', now()->toDateString());
        $generated = $request->boolean('show');

        return $this->page('accounting.balance-sheet', 'accounting.balance-sheet', [
            'asOf' => $asOf,
            'generated' => $generated,
            'report' => $generated ? $this->ledger->balanceSheet($asOf) : null,
        ]);
    }

    public function trialBalance(Request $request)
    {
        $this->authorizeAccounting();
        $this->catalog->ensure((int) auth()->user()->company_id);

        $asOf = $request->input('as_of', now()->toDateString());
        $generated = $request->boolean('show');

        return $this->page('accounting.trial-balance', 'accounting.trial-balance', [
            'asOf' => $asOf,
            'generated' => $generated,
            'rows' => $generated ? $this->ledger->trialBalance($asOf) : collect(),
        ]);
    }

    public function combinedGl(Request $request)
    {
        $this->authorizeAccounting();
        $this->catalog->ensure((int) auth()->user()->company_id);

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $generated = $request->boolean('show');

        return $this->page('accounting.combined-gl', 'accounting.combined-gl', [
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'rows' => $generated ? $this->ledger->combinedGl($from, $to) : collect(),
        ]);
    }

    public function customers()
    {
        $this->authorizeAccounting();

        return $this->page('accounting.customers', 'accounting.customers', [
            'customers' => $this->ledger->customerRows(),
        ]);
    }

    public function suppliers()
    {
        $this->authorizeAccounting();

        return $this->page('accounting.suppliers', 'accounting.suppliers', [
            'suppliers' => $this->ledger->supplierRows(),
        ]);
    }

    private function applySupplierPayment(
        int $supplierId,
        float $amount,
        $branchId,
        string $method,
        ?string $reference,
        ?string $notes,
        string $number,
        $paidAt
    ): void {
        $remaining = $amount;
        $companyId = (int) auth()->user()->company_id;
        $orders = PurchaseOrder::query()
            ->where('supplier_id', $supplierId)
            ->whereNotIn('status', [PurchaseOrder::STATUS_CANCELLED, PurchaseOrder::STATUS_DRAFT])
            ->orderBy('order_date')
            ->orderBy('id')
            ->get();

        foreach ($orders as $order) {
            $due = $order->balance();
            if ($due <= 0 || $remaining <= 0) {
                continue;
            }
            $pay = round(min($due, $remaining), 2);
            $order->increment('paid_amount', $pay);
            Payment::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'user_id' => auth()->id(),
                'supplier_id' => $supplierId,
                'number' => $number,
                'payable_type' => PurchaseOrder::class,
                'payable_id' => $order->id,
                'method' => $method,
                'amount' => $pay,
                'reference' => $reference,
                'paid_at' => $paidAt,
                'notes' => $notes,
            ]);
            $remaining = round($remaining - $pay, 2);
        }

        if ($remaining > 0) {
            $supplier = Supplier::query()->find($supplierId);
            if ($supplier) {
                $supplier->decrement('opening_balance', $remaining);
            }
        }
    }

    private function applyCustomerPayment(
        int $customerId,
        float $amount,
        $branchId,
        string $method,
        ?string $reference,
        ?string $notes,
        string $number,
        $paidAt
    ): void {
        $remaining = $amount;
        $companyId = (int) auth()->user()->company_id;
        $sales = Sale::query()
            ->where('customer_id', $customerId)
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('balance', '>', 0)
            ->orderBy('sale_date')
            ->orderBy('id')
            ->get();

        foreach ($sales as $sale) {
            if ($remaining <= 0) {
                break;
            }
            $due = (float) $sale->balance;
            $pay = round(min($due, $remaining), 2);
            $paid = round((float) $sale->paid_amount + $pay, 2);
            $balance = round(max(0, (float) $sale->total - $paid), 2);
            $status = Sale::PAYMENT_UNPAID;
            if ($balance <= 0) {
                $status = Sale::PAYMENT_PAID;
            } elseif ($paid > 0) {
                $status = Sale::PAYMENT_PARTIAL;
            }
            $sale->update([
                'paid_amount' => $paid,
                'balance' => $balance,
                'payment_status' => $status,
            ]);
            Payment::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'user_id' => auth()->id(),
                'customer_id' => $customerId,
                'number' => $number,
                'payable_type' => Sale::class,
                'payable_id' => $sale->id,
                'method' => $method,
                'amount' => $pay,
                'reference' => $reference,
                'paid_at' => $paidAt,
                'notes' => $notes,
            ]);
            $remaining = round($remaining - $pay, 2);
        }

        if ($remaining > 0) {
            $customer = Customer::query()->find($customerId);
            if ($customer) {
                $customer->decrement('opening_balance', $remaining);
            }
        }
    }

    private function page(string $view, string $activeMenu, array $data)
    {
        return view($view, array_merge(fleet_shared_view_data(), $data, [
            'activeMenu' => $activeMenu,
            'canManage' => $data['canManage'] ?? $this->canManage(),
        ]));
    }

    private function authorizeAccounting(): void
    {
        abort_unless($this->canView(), 403);
    }

    private function authorizeManage(): void
    {
        abort_unless($this->canManage(), 403);
    }

    private function canView(): bool
    {
        $user = auth()->user();

        return $user && (
            $user->hasPermission('accounting.view')
            || $user->hasPermission('accounting.manage')
            || $user->hasPermission('payments.view')
            || $user->hasPermission('reports.view')
            || $user->hasPermission('expenses.view')
        );
    }

    private function canManage(): bool
    {
        $user = auth()->user();

        return $user && (
            $user->hasPermission('accounting.manage')
            || $user->hasPermission('payments.create')
            || $user->hasPermission('payments.update')
            || $user->hasPermission('expenses.create')
        );
    }
}
