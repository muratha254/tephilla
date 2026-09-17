<?php

namespace App\Http\Controllers;

use App\Models\CustomerCategory;
use Illuminate\Http\Request;

class CustomerCategoryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePermission('customers.view');

        $this->ensureGeneral();

        $edit = null;
        if ($request->filled('edit')) {
            $edit = CustomerCategory::query()->find((int) $request->input('edit'));
        }

        return view('customers.categories', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'customers.categories',
            'categories' => CustomerCategory::query()->orderBy('name')->get(),
            'edit' => $edit,
            'canManage' => auth()->user()->hasPermission('customers.update') || auth()->user()->hasPermission('customers.create'),
        ]));
    }

    public function store(Request $request)
    {
        $this->authorizePermission('customers.create');

        $data = $this->validated($request);
        CustomerCategory::query()->create(array_merge($data, [
            'company_id' => auth()->user()->company_id,
            'is_active' => true,
        ]));

        return redirect()->route('customers.categories')->with('success', 'Category saved.');
    }

    public function update(Request $request, CustomerCategory $category)
    {
        $this->authorizePermission('customers.update');

        $category->update($this->validated($request));

        return redirect()->route('customers.categories')->with('success', 'Category updated.');
    }

    public function destroy(CustomerCategory $category)
    {
        $this->authorizePermission('customers.delete');

        if ($category->customers()->exists()) {
            return back()->with('error', 'This category is in use and cannot be deleted.');
        }

        $category->delete();

        return redirect()->route('customers.categories')->with('success', 'Category deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'discount_percent' => 'required|numeric|min:0|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $data['discount_percent'] = round((float) $data['discount_percent'], 2);

        return $data;
    }

    private function ensureGeneral(): void
    {
        if (CustomerCategory::query()->exists()) {
            return;
        }

        CustomerCategory::query()->create([
            'company_id' => auth()->user()->company_id,
            'name' => 'General',
            'discount_percent' => 0,
            'description' => null,
            'is_active' => true,
        ]);
    }
}
