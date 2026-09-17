<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CustomerPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use PDF;

class CustomerController extends Controller
{
    public function index()
    {
        $this->authorizePermission('customers.view');

        return view('customers.index', array_merge(fleet_shared_view_data(), $this->listData(false)));
    }

    public function archived()
    {
        $this->authorizePermission('customers.view');

        return view('customers.archived', array_merge(fleet_shared_view_data(), $this->listData(true), [
            'activeMenu' => 'customers.archived',
        ]));
    }

    public function create()
    {
        $this->authorizePermission('customers.create');

        return view('customers.form', array_merge(fleet_shared_view_data(), $this->formData(new Customer([
            'opening_balance' => 0,
            'credit_limit' => 0,
            'loyalty_points' => 0,
            'is_active' => true,
            'branch_id' => $this->currentBranchId(),
        ])), [
            'activeMenu' => 'customers.create',
        ]));
    }

    public function store(Request $request)
    {
        $this->authorizePermission('customers.create');

        $data = $this->validated($request);
        $data['company_id'] = auth()->user()->company_id;
        $data['type'] = Customer::TYPE_REGULAR;
        $data['is_walk_in'] = false;
        $data['is_active'] = true;
        $data['shop_image_path'] = $this->storeImage($request);

        Customer::query()->create($data);

        return redirect()->route('customers.index')->with('success', 'Customer saved.');
    }

    public function edit(Customer $customer)
    {
        $this->authorizePermission('customers.update');
        abort_if($customer->is_walk_in, 404);

        return view('customers.form', array_merge(fleet_shared_view_data(), $this->formData($customer), [
            'activeMenu' => 'customers.create',
        ]));
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorizePermission('customers.update');
        abort_if($customer->is_walk_in, 422, 'The walk-in customer cannot be edited here.');

        $data = $this->validated($request, $customer);
        if ($request->hasFile('shop_image')) {
            if ($customer->shop_image_path) {
                Storage::disk('public')->delete($customer->shop_image_path);
            }
            $data['shop_image_path'] = $this->storeImage($request);
        }

        $customer->update($data);

        return redirect()->route('customers.index')->with('success', 'Customer updated.');
    }

    public function destroy(Customer $customer)
    {
        $this->authorizePermission('customers.delete');
        abort_if($customer->is_walk_in, 422, 'The walk-in customer cannot be archived.');

        $customer->delete();

        return redirect()->route('customers.archived')->with('success', 'Customer archived.');
    }

    public function restore(int $customer)
    {
        $this->authorizePermission('customers.update');
        $model = Customer::onlyTrashed()->findOrFail($customer);
        $model->restore();

        return redirect()->route('customers.index')->with('success', 'Customer restored.');
    }

    public function template()
    {
        $this->authorizePermission('customers.create');

        $headers = 'name,phone,mobile,email,tax_number,opening_balance,credit_limit,national_id,county,estate,postcode,address';
        $sample = 'WALK-IN,0700000000,,,,,,,Nairobi,,,';

        return response($headers . "\n" . $sample . "\n", 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="customers-template.csv"',
        ]);
    }

    public function import(Request $request)
    {
        $this->authorizePermission('customers.create');
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if (! $handle) {
            return back()->with('error', 'Could not read the uploaded file.');
        }

        $header = fgetcsv($handle);
        $map = [];
        foreach ($header ?: [] as $i => $col) {
            $map[strtolower(trim((string) $col))] = $i;
        }
        if (! isset($map['name'])) {
            fclose($handle);

            return back()->with('error', 'The template must include a name column.');
        }

        $companyId = auth()->user()->company_id;
        $branchId = $this->currentBranchId();
        $categoryId = optional(CustomerCategory::query()->orderBy('id')->first())->id;
        $created = 0;

        DB::transaction(function () use ($handle, $map, $companyId, $branchId, $categoryId, &$created) {
            while (($row = fgetcsv($handle)) !== false) {
                $name = trim((string) ($row[$map['name']] ?? ''));
                if ($name === '') {
                    continue;
                }
                Customer::query()->create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'category_id' => $categoryId,
                    'name' => $name,
                    'phone' => $this->cell($row, $map, 'phone'),
                    'mobile' => $this->cell($row, $map, 'mobile'),
                    'email' => $this->cell($row, $map, 'email'),
                    'tax_number' => $this->cell($row, $map, 'tax_number'),
                    'opening_balance' => (float) ($this->cell($row, $map, 'opening_balance') ?: 0),
                    'credit_limit' => (float) ($this->cell($row, $map, 'credit_limit') ?: 0),
                    'national_id' => $this->cell($row, $map, 'national_id'),
                    'county' => $this->cell($row, $map, 'county'),
                    'estate' => $this->cell($row, $map, 'estate'),
                    'postcode' => $this->cell($row, $map, 'postcode'),
                    'address' => $this->cell($row, $map, 'address'),
                    'type' => Customer::TYPE_REGULAR,
                    'is_walk_in' => false,
                    'is_active' => true,
                ]);
                $created++;
            }
        });
        fclose($handle);

        return redirect()->route('customers.index')->with('success', $created . ' customer(s) imported.');
    }

    public function download()
    {
        $this->authorizePermission('customers.view');
        $customers = $this->customerQuery(false)->get();
        $lines = ['name,phone,credit_limit,credit_amount,tax_number,address,category,branch,status'];
        foreach ($customers as $customer) {
            $lines[] = implode(',', array_map(function ($value) {
                $value = str_replace('"', '""', (string) $value);

                return '"' . $value . '"';
            }, [
                $customer->name,
                $customer->phone,
                number_format((float) $customer->credit_limit, 2, '.', ''),
                number_format($customer->creditAmount(), 2, '.', ''),
                $customer->tax_number,
                $customer->address,
                optional($customer->category)->name,
                optional($customer->branch)->name,
                $customer->is_active ? 'Active' : 'Inactive',
            ]));
        }

        return response(implode("\n", $lines) . "\n", 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="customers-list.csv"',
        ]);
    }

    public function statement(Request $request)
    {
        $this->authorizePermission('customers.view');
        $ids = array_filter((array) $request->input('ids', []));
        $query = $this->customerQuery(false);
        if ($ids) {
            $query->whereIn('id', $ids);
        }
        $customers = $query->get();
        $profile = fleet_company_profile();

        $pdf = PDF::loadView('customers.statement-pdf', array_merge(fleet_shared_view_data(), [
            'customers' => $customers,
            'profile' => $profile,
            'logo_pdf_path' => $profile['logo_pdf_path'] ?? null,
        ]))->setPaper('a4', 'portrait');

        return $pdf->download('customer-statement.pdf');
    }

    public function writeOff(Request $request)
    {
        $this->authorizePermission('customers.update');
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        Customer::query()
            ->whereIn('id', $data['ids'])
            ->where('is_walk_in', false)
            ->update(['opening_balance' => 0]);

        return redirect()->route('customers.index')->with('success', 'Selected customer opening balances were written off.');
    }

    public function payments(Customer $customer)
    {
        $this->authorizePermission('customers.view');

        $customer->load(['payments' => function ($query) {
            $query->with(['user', 'payable'])->orderByDesc('paid_at')->orderByDesc('id');
        }]);

        $methods = config('sellix.payment_methods', []);
        $paidTotal = round((float) $customer->payments->sum('amount'), 2);
        $due = $customer->creditAmount();

        return response()->json([
            'name' => $customer->name,
            'phone' => $customer->phone ?: '-',
            'credit_limit' => number_format((float) $customer->credit_limit, 2),
            'outstanding' => number_format($due, 2),
            'paid_total' => number_format($paidTotal, 2),
            'payments' => $customer->payments->map(function (Payment $payment) use ($methods) {
                $appliedTo = '-';
                if ($payment->payable_type === Sale::class && $payment->payable) {
                    $appliedTo = method_exists($payment->payable, 'documentNumber')
                        ? $payment->payable->documentNumber()
                        : (string) ($payment->payable->number ?: ('Sale #' . $payment->payable->id));
                } elseif ($payment->payable_type === Customer::class) {
                    $appliedTo = 'Opening balance';
                }

                return [
                    'number' => $payment->number ?: '-',
                    'date' => optional($payment->paid_at)->format('d-m-Y H:i') ?: '-',
                    'method' => $methods[$payment->method] ?? ucfirst((string) $payment->method),
                    'reference' => $payment->reference ?: '-',
                    'applied_to' => $appliedTo,
                    'amount' => number_format((float) $payment->amount, 2),
                    'notes' => $payment->notes ?: '-',
                    'user' => optional($payment->user)->name ?: '-',
                ];
            })->values(),
        ]);
    }

    public function storePayment(Request $request, Customer $customer, CustomerPaymentService $payments, AuditLogger $audit)
    {
        $this->authorizePermission('payments.create');
        abort_if($customer->is_walk_in, 422, 'Walk-in customers cannot hold credit balances.');

        $due = $customer->creditAmount();
        if ($due <= 0) {
            return back()->with('error', 'This customer has no outstanding balance.');
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . max($due, 0.01),
            'method' => 'required|in:' . implode(',', array_keys(config('sellix.payment_methods', ['cash' => 'Cash']))),
            'reference' => 'nullable|string|max:64',
            'notes' => 'nullable|string|max:1000',
            'paid_at' => 'required|date',
        ]);

        try {
            $result = $payments->apply(
                $customer,
                (float) $data['amount'],
                $data['method'],
                $data['paid_at'],
                $data['reference'] ?? null,
                $data['notes'] ?? null,
                $this->currentBranchId() ?: $customer->branch_id
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        $audit->record('payment', 'customers', $customer, null, [
            'customer_id' => $customer->id,
            'amount' => $result['applied'],
            'method' => $data['method'],
            'payment_count' => count($result['payments']),
        ]);

        return redirect()->route('customers.index')->with(
            'success',
            'Received Ksh ' . number_format($result['applied'], 2) . ' from ' . $customer->name . '.'
        );
    }

    private function listData(bool $archived): array
    {
        $customers = $this->customerQuery($archived)->get();

        return [
            'activeMenu' => $archived ? 'customers.archived' : 'customers.index',
            'customers' => $customers,
            'canCreate' => auth()->user()->hasPermission('customers.create'),
            'canUpdate' => auth()->user()->hasPermission('customers.update'),
            'canDelete' => auth()->user()->hasPermission('customers.delete'),
            'canPay' => auth()->user()->hasPermission('payments.create'),
            'paymentMethods' => config('sellix.payment_methods', []),
        ];
    }

    private function customerQuery(bool $archived)
    {
        $query = $archived
            ? Customer::onlyTrashed()
            : Customer::query();

        return $query
            ->with(['category', 'branch'])
            ->withSum(['sales as credit_sales' => function ($builder) {
                $builder->where('status', Sale::STATUS_COMPLETED);
            }], 'balance')
            ->orderByRaw('is_walk_in desc')
            ->orderBy('name');
    }

    private function formData(Customer $customer): array
    {
        if (! CustomerCategory::query()->exists()) {
            CustomerCategory::query()->create([
                'company_id' => auth()->user()->company_id,
                'name' => 'General',
                'discount_percent' => 0,
                'is_active' => true,
            ]);
        }

        return [
            'customer' => $customer,
            'categories' => CustomerCategory::query()->orderBy('name')->get(),
            'users' => User::query()->where('company_id', auth()->user()->company_id)->orderBy('name')->get(),
            'accounts' => config('sellix.control_accounts', []),
            'counties' => config('sellix.kenya_counties', []),
            'selectedBranchId' => old('branch_id', $customer->branch_id ?: $this->currentBranchId()),
        ];
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        $companyId = auth()->user()->company_id;
        $data = $request->validate([
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:64',
            'mobile' => 'nullable|string|max:64',
            'email' => 'nullable|email|max:255',
            'opening_balance' => 'nullable|numeric',
            'credit_limit' => 'nullable|numeric|min:0',
            'tax_number' => 'nullable|string|max:64',
            'county' => 'nullable|string|max:64',
            'national_id' => 'nullable|string|max:64',
            'estate' => 'nullable|string|max:128',
            'postcode' => 'nullable|string|max:32',
            'migration_account' => 'nullable|string|max:64',
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('company_id', $companyId)],
            'category_id' => ['nullable', Rule::exists('customer_categories', 'id')->where('company_id', $companyId)],
            'address' => 'nullable|string|max:2000',
            'shop_image' => 'nullable|image|max:2048',
        ]);
        unset($data['shop_image']);
        $data['opening_balance'] = round((float) ($data['opening_balance'] ?? 0), 2);
        $data['credit_limit'] = round((float) ($data['credit_limit'] ?? 0), 2);
        $data['assigned_to'] = $data['assigned_to'] ?? null;
        $data['category_id'] = $data['category_id'] ?? null;

        return $data;
    }

    private function storeImage(Request $request): ?string
    {
        if (! $request->hasFile('shop_image')) {
            return null;
        }

        return $request->file('shop_image')->store('customers', 'public');
    }

    private function cell(array $row, array $map, string $key): ?string
    {
        if (! isset($map[$key])) {
            return null;
        }
        $value = trim((string) ($row[$map[$key]] ?? ''));

        return $value === '' ? null : $value;
    }
}
