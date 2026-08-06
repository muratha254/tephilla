<?php

namespace App\Http\Controllers;

use App\Models\FleetMechanic;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FleetMechanicController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $viewMode = $request->query('view', 'grid') === 'list' ? 'list' : 'grid';

        $query = FleetMechanic::query();

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('specialty', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }

        $mechanics = $query->orderBy('name')->get();
        $totalCount = FleetMechanic::query()->count();

        return view('fleet.mechanics.index', array_merge($this->sharedViewData(), [
            'activeMenu' => 'maintenance-mechanic',
            'openMenu' => 'maintenance',
            'mechanics' => $mechanics,
            'search' => $search,
            'viewMode' => $viewMode,
            'totalCount' => $totalCount,
            'specialties' => $this->specialties(),
        ]));
    }

    public function create()
    {
        return view('fleet.mechanics.create', array_merge($this->sharedViewData(), [
            'activeMenu' => 'maintenance-mechanic',
            'openMenu' => 'maintenance',
            'specialties' => $this->specialties(),
        ]));
    }

    public function store(Request $request)
    {
        FleetMechanic::create($this->validated($request));

        return redirect()
            ->route('mechanics.index')
            ->with('success', 'Mechanic added successfully.');
    }

    public function edit(FleetMechanic $mechanic)
    {
        return view('fleet.mechanics.edit', array_merge($this->sharedViewData(), [
            'activeMenu' => 'maintenance-mechanic',
            'openMenu' => 'maintenance',
            'mechanic' => $mechanic,
            'specialties' => $this->specialties(),
        ]));
    }

    public function update(Request $request, FleetMechanic $mechanic)
    {
        $mechanic->update($this->validated($request));

        return redirect()
            ->route('mechanics.index')
            ->with('success', 'Mechanic updated successfully.');
    }

    public function destroy(FleetMechanic $mechanic)
    {
        $mechanic->delete();

        return redirect()
            ->route('mechanics.index')
            ->with('success', 'Mechanic deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'specialty' => ['required', 'string', 'max:100', Rule::in($this->specialties())],
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:50',
        ]);
    }

    private function specialties(): array
    {
        return [
            'Engine',
            'Painting',
            'Electrical',
            'Bodywork',
            'Brakes',
            'Transmission',
            'General',
        ];
    }

    private function sharedViewData(): array
    {
        return [
            'companyName' => 'One Translines Pvt Ltd',
            'notificationCount' => 11,
        ];
    }
}
