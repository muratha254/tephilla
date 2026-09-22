<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Services\AuditLogger;
use App\Support\SubscriptionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OwnerPlanController extends Controller
{
    public function index()
    {
        return view('owner.plans.index', $this->ownerView([
            'activeMenu' => 'owner.plans',
            'plans' => SubscriptionPlan::query()->orderBy('sort_order')->orderBy('name')->get(),
            'periods' => SubscriptionCatalog::periods(),
        ]));
    }

    public function create()
    {
        return view('owner.plans.form', $this->ownerView([
            'activeMenu' => 'owner.plans',
            'plan' => new SubscriptionPlan([
                'is_active' => true,
                'billing_period' => SubscriptionCatalog::PERIOD_MONTHLY,
                'features' => ['pos', 'products', 'inventory', 'sales', 'customers', 'payments'],
                'price' => 0,
            ]),
            'periods' => SubscriptionCatalog::periods(),
            'features' => SubscriptionCatalog::features(),
        ]));
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $data = $this->validated($request);
        $plan = SubscriptionPlan::query()->create($data);
        $audit->record('create', 'subscription_plans', $plan, null, $plan->only(['name', 'billing_period', 'price']));

        return redirect()->route('owner.plans.index')->with('success', 'Plan saved.');
    }

    public function edit(SubscriptionPlan $plan)
    {
        return view('owner.plans.form', $this->ownerView([
            'activeMenu' => 'owner.plans',
            'plan' => $plan,
            'periods' => SubscriptionCatalog::periods(),
            'features' => SubscriptionCatalog::features(),
        ]));
    }

    public function update(Request $request, SubscriptionPlan $plan, AuditLogger $audit)
    {
        $before = $plan->only(['name', 'price', 'billing_period', 'max_users', 'max_branches', 'is_active']);
        $plan->update($this->validated($request, $plan->id));
        $audit->record('update', 'subscription_plans', $plan, $before, $plan->only(['name', 'price', 'billing_period', 'max_users', 'max_branches', 'is_active']));

        return redirect()->route('owner.plans.index')->with('success', 'Plan updated.');
    }

    public function destroy(SubscriptionPlan $plan, AuditLogger $audit)
    {
        if ($plan->subscriptions()->exists()) {
            return back()->with('error', 'This plan is assigned to one or more businesses and cannot be deleted. Deactivate it instead.');
        }

        $audit->record('delete', 'subscription_plans', $plan, $plan->only(['name']), null);
        $plan->delete();

        return redirect()->route('owner.plans.index')->with('success', 'Plan archived.');
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('subscription_plans', 'name')->ignore($ignoreId)->whereNull('deleted_at'),
            ],
            'description' => 'nullable|string|max:2000',
            'price' => 'required|numeric|min:0',
            'billing_period' => ['required', Rule::in(array_keys(SubscriptionCatalog::periods()))],
            'duration_days' => 'nullable|integer|min:1',
            'max_users' => 'nullable|integer|min:1',
            'max_branches' => 'nullable|integer|min:1',
            'features' => 'nullable|array',
            'features.*' => Rule::in(SubscriptionCatalog::allFeatureKeys()),
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($data['billing_period'] === SubscriptionCatalog::PERIOD_CUSTOM && empty($data['duration_days'])) {
            $data['duration_days'] = 30;
        }
        if ($data['billing_period'] !== SubscriptionCatalog::PERIOD_CUSTOM) {
            $data['duration_days'] = null;
        }

        $data['slug'] = Str::slug($data['name']) ?: 'plan-' . Str::lower(Str::random(6));
        $base = $data['slug'];
        $i = 1;
        while (SubscriptionPlan::query()->where('slug', $data['slug'])->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $data['slug'] = $base . '-' . $i;
            $i++;
        }
        $data['is_active'] = $request->boolean('is_active');
        $data['features'] = SubscriptionCatalog::allFeatureKeys();
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['max_users'] = null;
        $data['max_branches'] = $data['max_branches'] ?? null;

        return $data;
    }

    protected function ownerView(array $data): array
    {
        return array_merge(fleet_shared_view_data(), $data, ['ownerConsole' => true]);
    }
}
