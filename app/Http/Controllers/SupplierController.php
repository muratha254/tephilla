<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index()
    {
        $this->authorizePermission('suppliers.view');

        $suppliers = Supplier::query()
            ->with('branch')
            ->orderBy('name')
            ->get();

        return view('suppliers.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'suppliers.index',
            'suppliers' => $suppliers,
            'canCreate' => auth()->user()->hasPermission('suppliers.create'),
            'canUpdate' => auth()->user()->hasPermission('suppliers.update'),
            'canDelete' => auth()->user()->hasPermission('suppliers.delete'),
        ]));
    }

    public function create()
    {
        $this->authorizePermission('suppliers.create');

        return view('suppliers.form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'suppliers.create',
            'supplier' => new Supplier([
                'opening_balance' => 0,
                'is_active' => true,
            ]),
            'accounts' => config('sellix.control_accounts', []),
        ]));
    }

    public function store(Request $request)
    {
        $this->authorizePermission('suppliers.create');

        $data = $this->validated($request, null, $request->wantsJson());

        $supplier = Supplier::query()->create(array_merge($data, [
            'company_id' => auth()->user()->company_id,
            'branch_id' => $data['branch_id'] ?? session('current_branch_id', auth()->user()->branch_id),
            'is_active' => $data['is_active'] ?? true,
        ]));

        if ($request->wantsJson()) {
            return response()->json(['id' => $supplier->id, 'name' => $supplier->name]);
        }

        return redirect()->route('suppliers.index')->with('success', 'Supplier saved.');
    }

    public function edit(Supplier $supplier)
    {
        $this->authorizePermission('suppliers.update');

        return view('suppliers.form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'suppliers.create',
            'supplier' => $supplier,
            'accounts' => config('sellix.control_accounts', []),
        ]));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $this->authorizePermission('suppliers.update');

        $supplier->update($this->validated($request, $supplier));

        return redirect()->route('suppliers.index')->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier)
    {
        $this->authorizePermission('suppliers.delete');
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', 'Supplier deleted.');
    }

    public function template()
    {
        $this->authorizePermission('suppliers.create');

        $headers = [
            'name', 'phone', 'mobile', 'email', 'tax_number', 'opening_balance',
            'postcode', 'address', 'till_number', 'paybill_number', 'mpesa_account_name',
            'bank_account_name', 'bank_account_number', 'bank_name', 'bank_branch',
        ];
        $csv = implode(',', $headers) . "\n";
        $csv .= 'General Supplier,0700000001,,general@example.com,,0,,,,' . "\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="suppliers-template.csv"',
        ]);
    }

    public function import(Request $request)
    {
        $this->authorizePermission('suppliers.create');

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

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

        $created = 0;
        $companyId = auth()->user()->company_id;
        $branchId = session('current_branch_id', auth()->user()->branch_id);

        DB::transaction(function () use ($handle, $map, $companyId, $branchId, &$created) {
            while (($row = fgetcsv($handle)) !== false) {
                $name = trim((string) ($row[$map['name']] ?? ''));
                if ($name === '') {
                    continue;
                }

                Supplier::query()->create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'name' => $name,
                    'phone' => $this->cell($row, $map, 'phone'),
                    'mobile' => $this->cell($row, $map, 'mobile'),
                    'email' => $this->cell($row, $map, 'email'),
                    'tax_number' => $this->cell($row, $map, 'tax_number'),
                    'opening_balance' => (float) ($this->cell($row, $map, 'opening_balance') ?: 0),
                    'postcode' => $this->cell($row, $map, 'postcode'),
                    'address' => $this->cell($row, $map, 'address'),
                    'till_number' => $this->cell($row, $map, 'till_number'),
                    'paybill_number' => $this->cell($row, $map, 'paybill_number'),
                    'mpesa_account_name' => $this->cell($row, $map, 'mpesa_account_name'),
                    'bank_account_name' => $this->cell($row, $map, 'bank_account_name'),
                    'bank_account_number' => $this->cell($row, $map, 'bank_account_number'),
                    'bank_name' => $this->cell($row, $map, 'bank_name'),
                    'bank_branch' => $this->cell($row, $map, 'bank_branch'),
                    'is_active' => true,
                ]);
                $created++;
            }
        });

        fclose($handle);

        return redirect()->route('suppliers.index')->with('success', $created . ' supplier(s) imported.');
    }

    private function validated(Request $request, ?Supplier $supplier = null, bool $json = false): array
    {
        $companyId = auth()->user()->company_id;

        $rules = [
            'name' => 'required|string|max:255',
            'phone' => $json ? 'nullable|string|max:64' : 'required|string|max:64',
            'mobile' => 'nullable|string|max:64',
            'email' => 'nullable|email|max:255',
            'tax_number' => 'nullable|string|max:64',
            'country' => 'nullable|string|max:64',
            'state' => 'nullable|string|max:64',
            'postcode' => 'nullable|string|max:32',
            'address' => 'nullable|string|max:2000',
            'opening_balance' => 'nullable|numeric',
            'apply_withholding' => 'nullable|boolean',
            'migration_account' => 'nullable|string|max:64',
            'till_number' => 'nullable|string|max:64',
            'paybill_number' => 'nullable|string|max:64',
            'mpesa_account_name' => 'nullable|string|max:128',
            'bank_account_name' => 'nullable|string|max:128',
            'bank_account_number' => 'nullable|string|max:64',
            'bank_name' => 'nullable|string|max:128',
            'bank_branch' => 'nullable|string|max:128',
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'is_active' => 'nullable|boolean',
        ];

        $data = $request->validate($rules);
        $data['opening_balance'] = round((float) ($data['opening_balance'] ?? 0), 2);
        $data['apply_withholding'] = $request->boolean('apply_withholding');
        $data['is_active'] = $request->has('is_active')
            ? $request->boolean('is_active')
            : ($supplier ? (bool) $supplier->is_active : true);

        return $data;
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
