<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Services\CompanyProvisioner;
use App\Services\SubscriptionService;
use App\Services\SystemOwnerBootstrapper;
use App\Support\SubscriptionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RegisterBusinessController extends Controller
{
    public const SESSION_KEY = 'signup_wizard';

    public function businessForm()
    {
        return view('auth.register.business', [
            'wizard' => $this->wizard(),
            'step' => 'business',
        ]);
    }

    public function saveBusiness(Request $request)
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->putWizard(['business' => $data]);

        return redirect()->route('register.account');
    }

    public function accountForm()
    {
        if (empty($this->wizard()['business'])) {
            return redirect()->route('register');
        }

        return view('auth.register.account', [
            'wizard' => $this->wizard(),
            'step' => 'account',
        ]);
    }

    public function saveAccount(Request $request)
    {
        if (empty($this->wizard()['business'])) {
            return redirect()->route('register');
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:100', Rule::unique('users', 'username')],
            'password' => ['required', 'string', 'min:4', 'confirmed'],
        ]);

        $this->putWizard(['account' => [
            'email' => $data['email'],
            'username' => $data['username'],
            'password' => $data['password'],
        ]]);

        return redirect()->route('register.plans');
    }

    public function plansForm()
    {
        if (! $this->hasAccount()) {
            return redirect()->route('register.account');
        }

        $plans = $this->activePlans();
        if ($plans->isEmpty()) {
            return redirect()->route('register')->with('error', 'No subscription plans are available. Please contact the System Owner.');
        }

        return view('auth.register.plans', [
            'wizard' => $this->wizard(),
            'step' => 'plans',
            'wide' => true,
            'plans' => $plans,
            'selectedId' => $this->wizard()['plan_id'] ?? null,
        ]);
    }

    public function savePlan(Request $request)
    {
        if (! $this->hasAccount()) {
            return redirect()->route('register.account');
        }

        $data = $request->validate([
            'plan_id' => ['required', Rule::exists('subscription_plans', 'id')->where(fn ($q) => $q->where('is_active', true))],
        ]);

        $this->putWizard(['plan_id' => (int) $data['plan_id']]);

        return redirect()->route('register.review');
    }

    public function reviewForm()
    {
        if (! $this->hasAccount() || empty($this->wizard()['plan_id'])) {
            return redirect()->route('register.plans');
        }

        $plan = SubscriptionPlan::query()
            ->where('is_active', true)
            ->find($this->wizard()['plan_id']);

        if (! $plan) {
            return redirect()->route('register.plans')->with('error', 'Please select an available plan.');
        }

        return view('auth.register.review', [
            'wizard' => $this->wizard(),
            'step' => 'review',
            'wide' => true,
            'plan' => $plan,
        ]);
    }

    public function submit(Request $request, CompanyProvisioner $provisioner, SubscriptionService $subscriptions)
    {
        if (! $this->hasAccount() || empty($this->wizard()['plan_id'])) {
            return redirect()->route('register');
        }

        $wizard = $this->wizard();
        $request->merge([
            'email' => $wizard['account']['email'] ?? null,
            'username' => $wizard['account']['username'] ?? null,
            'plan_id' => $wizard['plan_id'] ?? null,
        ]);
        $request->validate([
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'username' => ['required', 'string', Rule::unique('users', 'username')],
            'plan_id' => ['required', Rule::exists('subscription_plans', 'id')->where(fn ($q) => $q->where('is_active', true))],
        ]);

        $plan = SubscriptionPlan::query()->where('is_active', true)->findOrFail($wizard['plan_id']);
        $business = $wizard['business'];
        $account = $wizard['account'];

        DB::transaction(function () use ($business, $account, $plan, $provisioner, $subscriptions) {
            $provisioned = $provisioner->provision([
                'name' => $business['business_name'],
                'owner_name' => $business['owner_name'],
                'email' => $account['email'],
                'phone' => $business['phone'] ?? null,
                'address' => $business['address'] ?? null,
                'is_active' => true,
            ], [
                'name' => $business['owner_name'],
                'email' => $account['email'],
                'username' => $account['username'],
                'password' => $account['password'],
                'phone' => $business['phone'] ?? null,
            ]);

            app()->instance('currentCompanyId', $provisioned['company']->id);

            $subscriptions->createForCompany(
                $provisioned['company'],
                $plan,
                now()->startOfDay(),
                now()->startOfDay(),
                SubscriptionCatalog::STATUS_PENDING_APPROVAL,
                'Client subscription request awaiting System Owner approval.'
            );
        });

        $request->session()->forget(self::SESSION_KEY);
        $request->session()->regenerate();

        return redirect()->route('register.submitted');
    }

    public function submitted()
    {
        return view('auth.register.submitted', [
            'step' => 'submitted',
        ]);
    }

    protected function wizard(): array
    {
        return session(self::SESSION_KEY, []);
    }

    protected function putWizard(array $data): void
    {
        session([self::SESSION_KEY => array_replace($this->wizard(), $data)]);
    }

    protected function hasAccount(): bool
    {
        $wizard = $this->wizard();

        return ! empty($wizard['business']) && ! empty($wizard['account']['email']) && ! empty($wizard['account']['password']);
    }

    protected function activePlans()
    {
        $plans = SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        if ($plans->isEmpty()) {
            app(SystemOwnerBootstrapper::class)->ensurePlans();
            $plans = SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        }

        return $plans;
    }
}
