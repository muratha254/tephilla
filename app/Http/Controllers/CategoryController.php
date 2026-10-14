<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        $this->authorizePermission('categories.view');

        return view('catalog.categories', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'categories.index',
            'categories' => ProductCategory::query()->with('branch')->orderBy('id')->get(),
            'canManage' => auth()->user()->hasPermission('categories.manage'),
            'selectedBranchId' => $this->currentBranchId(),
        ]));
    }

    public function types()
    {
        $user = auth()->user();
        abort_unless($user->hasPermission('products.view') || $user->hasPermission('categories.view'), 403);

        $categories = ProductCategory::query()->withCount(['products', 'children'])->orderBy('name')->get();
        $types = $categories->filter(function ($category) use ($categories) {
            return $category->parent_id || ! $categories->contains('parent_id', $category->id);
        })->values();

        return view('catalog.product-types', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'product-types',
            'types' => $types,
            'canDelete' => $user->hasPermission('categories.manage')
                || $user->hasPermission('products.create')
                || $user->hasPermission('products.update'),
        ]));
    }

    public function destroyType(ProductCategory $category)
    {
        $user = auth()->user();
        abort_unless(
            $user->hasPermission('categories.manage')
            || $user->hasPermission('products.create')
            || $user->hasPermission('products.update'),
            403
        );

        $productCount = $category->products()->count();
        if ($productCount > 0) {
            return back()->with('error', $category->name . ' is used by ' . $productCount . ' product' . ($productCount === 1 ? '' : 's') . '. Move those products to another type before deleting it.');
        }
        if ($category->children()->exists()) {
            return back()->with('error', $category->name . ' still has product types under it. Delete those first.');
        }

        $name = $category->name;
        $category->delete();

        return back()->with('success', $name . ' was deleted.');
    }

    public function storeType(Request $request)
    {
        $user = auth()->user();
        abort_unless(
            $user->hasPermission('categories.manage')
            || $user->hasPermission('products.create')
            || $user->hasPermission('products.update'),
            403
        );

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);
        $name = trim($data['name']);
        $companyId = (int) $user->company_id;

        $existing = ProductCategory::query()
            ->where('company_id', $companyId)
            ->where('name', $name)
            ->first();
        if ($existing) {
            if (! $existing->is_active) {
                $existing->update(['is_active' => true]);
            }

            return response()->json(['id' => $existing->id, 'name' => $existing->name, 'label' => $existing->name]);
        }

        $category = ProductCategory::query()->create([
            'company_id' => $companyId,
            'branch_id' => $this->currentBranchId(),
            'parent_id' => null,
            'name' => $name,
            'show_on_pos' => true,
            'is_active' => true,
        ]);
        $category->update([
            'code' => 'CAT_' . str_pad((string) $category->id, 4, '0', STR_PAD_LEFT),
        ]);

        return response()->json(['id' => $category->id, 'name' => $category->name, 'label' => $category->name]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('categories.manage');

        $companyId = auth()->user()->company_id;
        $request->merge([
            'parent_id' => $request->filled('parent_id') ? $request->input('parent_id') : null,
            'branch_id' => $request->filled('branch_id') ? $request->input('branch_id') : $this->currentBranchId(),
            'show_on_pos' => $request->exists('show_on_pos') ? $request->input('show_on_pos') : 1,
        ]);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'parent_id' => ['nullable', Rule::exists('product_categories', 'id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })],
            'branch_id' => ['required', Rule::exists('branches', 'id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })],
            'show_on_pos' => 'required|boolean',
        ]);

        $category = ProductCategory::query()->create([
            'company_id' => $companyId,
            'branch_id' => $data['branch_id'],
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'show_on_pos' => $request->boolean('show_on_pos'),
            'is_active' => true,
        ]);

        if (empty($category->code)) {
            $category->update([
                'code' => 'CAT_' . str_pad((string) $category->id, 4, '0', STR_PAD_LEFT),
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json(['id' => $category->id, 'name' => $category->name, 'label' => $category->name]);
        }

        return back()->with('success', 'Category saved.');
    }

    public function update(Request $request, ProductCategory $category)
    {
        $this->authorizePermission('categories.manage');

        $companyId = auth()->user()->company_id;
        $request->merge([
            'parent_id' => $request->filled('parent_id') ? $request->input('parent_id') : null,
        ]);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'parent_id' => ['nullable', Rule::notIn([$category->id]), Rule::exists('product_categories', 'id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })],
            'branch_id' => ['required', Rule::exists('branches', 'id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })],
            'show_on_pos' => 'required|boolean',
        ]);

        $category->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'branch_id' => $data['branch_id'],
            'show_on_pos' => $request->boolean('show_on_pos'),
        ]);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(ProductCategory $category)
    {
        $this->authorizePermission('categories.manage');
        $category->delete();

        return back()->with('success', 'Category deleted.');
    }
}
