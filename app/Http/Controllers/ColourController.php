<?php

namespace App\Http\Controllers;

use App\Models\Colour;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ColourController extends Controller
{
    public function index()
    {
        $this->authorizePermission('products.view');

        return view('catalog.colours', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'colours.index',
            'colours' => Colour::query()->orderBy('name')->get(),
            'canManage' => auth()->user()->hasPermission('products.update') || auth()->user()->hasPermission('products.create'),
        ]));
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $this->authorizeManage();
        $data = $this->validated($request);

        $colour = Colour::query()->create([
            'company_id' => auth()->user()->company_id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $audit->record('create', 'colours', $colour, null, $colour->only(['name', 'is_active']));

        return back()->with('success', 'Colour saved.');
    }

    public function update(Request $request, Colour $colour, AuditLogger $audit)
    {
        $this->authorizeManage();
        $before = $colour->only(['name', 'description', 'is_active']);
        $data = $this->validated($request, $colour->id);

        $colour->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $audit->record('update', 'colours', $colour, $before, $colour->only(['name', 'description', 'is_active']));

        return back()->with('success', 'Colour updated.');
    }

    private function authorizeManage(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && ($user->hasPermission('products.update') || $user->hasPermission('products.create')),
            403
        );
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $companyId = auth()->user()->company_id;

        return $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('colours', 'name')->where(function ($query) use ($companyId) {
                    $query->where('company_id', $companyId);
                })->ignore($ignoreId),
            ],
            'description' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);
    }
}
