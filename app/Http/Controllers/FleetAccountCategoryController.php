<?php

namespace App\Http\Controllers;

use App\Models\FleetAccountCategory;
use Illuminate\Http\Request;

class FleetAccountCategoryController extends Controller
{
    public function index()
    {
        $categories = FleetAccountCategory::query()
            ->orderBy('id')
            ->get();

        return view('fleet.accounts.categories.index', array_merge($this->sharedViewData(), [
            'activeMenu' => 'accounts-categories',
            'openMenu' => 'accounts',
            'categories' => $categories,
        ]));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
        ]);

        FleetAccountCategory::create($validated);

        return redirect()
            ->route('account-categories.index')
            ->with('success', 'Category added successfully.');
    }

    public function destroy(FleetAccountCategory $accountCategory)
    {
        $accountCategory->delete();

        return redirect()
            ->route('account-categories.index')
            ->with('success', 'Category deleted.');
    }

    private function sharedViewData(): array
    {
        return [
            'companyName' => 'One Translines Pvt Ltd',
            'notificationCount' => 11,
        ];
    }
}
