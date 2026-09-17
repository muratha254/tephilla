<?php

namespace App\Http\Controllers;

use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaxSettingsController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->hasPermission('settings.view'), 403);

        return view('settings.taxes.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'settings.tax',
            'taxes' => Tax::query()->orderBy('name')->get(),
            'canManage' => auth()->user()->hasPermission('settings.company') || auth()->user()->hasPermission('settings.view'),
        ]));
    }

    public function create()
    {
        abort_unless(auth()->user()->hasPermission('settings.view'), 403);

        return view('settings.taxes.form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'settings.tax',
            'tax' => new Tax(['is_active' => true, 'rate' => 0]),
        ]));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('settings.view'), 403);

        $data = $this->validated($request);

        Tax::query()->create([
            'company_id' => auth()->user()->company_id,
            'name' => $data['name'],
            'rate' => $data['rate'],
            'is_inclusive' => true,
            'is_default' => false,
            'is_active' => true,
        ]);

        return redirect()->route('settings.tax')->with('success', 'Tax saved.');
    }

    public function edit(Tax $tax)
    {
        abort_unless(auth()->user()->hasPermission('settings.view'), 403);

        return view('settings.taxes.form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'settings.tax',
            'tax' => $tax,
        ]));
    }

    public function update(Request $request, Tax $tax)
    {
        abort_unless(auth()->user()->hasPermission('settings.view'), 403);

        $data = $this->validated($request, $tax->id);
        $tax->update([
            'name' => $data['name'],
            'rate' => $data['rate'],
            'is_active' => ($request->input('is_active', 'Yes') === 'Yes'),
        ]);

        return redirect()->route('settings.tax')->with('success', 'Tax updated.');
    }

    public function destroy(Tax $tax)
    {
        abort_unless(auth()->user()->hasPermission('settings.view'), 403);
        abort_if($tax->products()->exists(), 422, 'Tax is used by products and cannot be deleted.');

        $tax->delete();

        return redirect()->route('settings.tax')->with('success', 'Tax deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $companyId = auth()->user()->company_id;

        return $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('taxes', 'name')->ignore($ignoreId)->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
    }
}
