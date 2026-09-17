<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index()
    {
        $this->authorizePermission('brands.view');

        return view('catalog.brands', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'brands.index',
            'brands' => Brand::query()->orderBy('id')->get(),
            'canManage' => auth()->user()->hasPermission('brands.manage'),
        ]));
    }

    public function store(Request $request)
    {
        $this->authorizePermission('brands.manage');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);

        $brand = Brand::query()->create([
            'company_id' => auth()->user()->company_id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $request->exists('is_active') ? $request->boolean('is_active') : true,
        ]);

        if (empty($brand->code)) {
            $brand->update([
                'code' => 'BR' . str_pad((string) $brand->id, 4, '0', STR_PAD_LEFT),
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json(['id' => $brand->id, 'name' => $brand->name, 'label' => $brand->name]);
        }

        return back()->with('success', 'Brand saved.');
    }

    public function update(Request $request, Brand $brand)
    {
        $this->authorizePermission('brands.manage');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'is_active' => 'required|boolean',
        ]);

        $brand->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        if (empty($brand->code)) {
            $brand->update([
                'code' => 'BR' . str_pad((string) $brand->id, 4, '0', STR_PAD_LEFT),
            ]);
        }

        return back()->with('success', 'Brand updated.');
    }

    public function destroy(Brand $brand)
    {
        $this->authorizePermission('brands.manage');
        $brand->delete();

        return back()->with('success', 'Brand deleted.');
    }
}
