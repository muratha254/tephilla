<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    public function index()
    {
        $this->authorizePermission('units.view');

        return view('catalog.units', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'units.index',
            'units' => Unit::query()->orderBy('id')->get(),
            'canManage' => auth()->user()->hasPermission('units.manage'),
        ]));
    }

    public function store(Request $request)
    {
        $this->authorizePermission('units.manage');

        $companyId = auth()->user()->company_id;
        $this->normalizeUnitRequest($request);

        $data = $request->validate($this->unitRules($companyId));

        $unit = Unit::query()->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'short_name' => $data['short_name'] ?? null,
            'description' => $data['description'] ?? null,
            'base_unit_id' => $data['base_unit_id'] ?? null,
            'multiplier' => $data['multiplier'] ?? 1,
            'is_active' => true,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'id' => $unit->id,
                'name' => $unit->name,
                'label' => $unit->short_name ? $unit->name . ' (' . $unit->short_name . ')' : $unit->name,
            ]);
        }

        return back()->with('success', 'Unit saved.');
    }

    public function update(Request $request, Unit $unit)
    {
        $this->authorizePermission('units.manage');

        $companyId = auth()->user()->company_id;
        $this->normalizeUnitRequest($request);

        $data = $request->validate($this->unitRules($companyId, $unit->id));

        $unit->update([
            'name' => $data['name'],
            'short_name' => $data['short_name'] ?? null,
            'description' => $data['description'] ?? null,
            'base_unit_id' => $data['base_unit_id'] ?? null,
            'multiplier' => $data['multiplier'] ?? 1,
        ]);

        return back()->with('success', 'Unit updated.');
    }

    public function destroy(Unit $unit)
    {
        $this->authorizePermission('units.manage');
        $unit->delete();

        return back()->with('success', 'Unit deleted.');
    }

    private function normalizeUnitRequest(Request $request): void
    {
        $short = trim((string) $request->input('short_name', ''));

        $request->merge([
            'short_name' => $short !== '' ? strtoupper($short) : null,
            'base_unit_id' => $request->filled('base_unit_id') ? $request->input('base_unit_id') : null,
            'multiplier' => $request->filled('multiplier') ? $request->input('multiplier') : 1,
        ]);
    }

    private function unitRules(int $companyId, ?int $ignoreId = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'short_name' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('units', 'short_name')->where(function ($query) use ($companyId) {
                    $query->where('company_id', $companyId);
                })->ignore($ignoreId),
            ],
            'description' => 'nullable|string|max:2000',
            'multiplier' => 'nullable|numeric|min:0',
            'base_unit_id' => array_values(array_filter([
                'nullable',
                $ignoreId ? Rule::notIn([$ignoreId]) : null,
                Rule::exists('units', 'id')->where(function ($query) use ($companyId) {
                    $query->where('company_id', $companyId);
                }),
            ])),
        ];
    }
}
