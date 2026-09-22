<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SubscriptionHistory;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CompanyProvisioner;
use App\Services\SubscriptionLimitGuard;
use App\Services\SubscriptionService;
use App\Support\SubscriptionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OwnerBusinessController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptions,
        protected CompanyProvisioner $provisioner,
        protected SubscriptionLimitGuard $limits,
        protected AuditLogger $audit
    ) {
    }

    public function index(Request $request)
    {
        $status = (string) $request->query('status', '');
        $planId = $request->query('plan_id');
        $q = trim((string) $request->query('q', ''));

        $companies = Company::query()
            ->with(['subscription.plan'])
            ->withCount(['users', 'branches'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', '%' . $q . '%')
                        ->orWhere('email', 'like', '%' . $q . '%')
                        ->orWhere('phone', 'like', '%' . $q . '%')
                        ->orWhere('owner_name', 'like', '%' . $q . '%');
                });
            })
            ->when($planId, fn ($query) => $query->whereHas('subscription', fn ($s) => $s->where('subscription_plan_id', $planId)))
            ->orderBy('name')
            ->get();

        if ($status !== '') {
            $companies = $companies->filter(function (Company $company) use ($status) {
                if ($status === SubscriptionCatalog::STATUS_DEACTIVATED) {
                    return ! $company->is_active;
                }
                $effective = optional($company->subscription)->effectiveStatus() ?: SubscriptionCatalog::STATUS_EXPIRED;

                return $effective === $status;
            })->values();
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;
        $slice = $companies->forPage($page, $perPage)->values();
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            $companies->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('owner.businesses.index', $this->ownerView([
            'activeMenu' => 'owner.businesses',
            'companies' => $paginator,
            'plans' => SubscriptionPlan::query()->orderBy('name')->get(),
            'statuses' => SubscriptionCatalog::statuses(),
            'filters' => ['q' => $q, 'status' => $status, 'plan_id' => $planId],
        ]));
    }

    public function create()
    {
        return view('owner.businesses.form', $this->ownerView([
            'activeMenu' => 'owner.businesses',
            'company' => new Company(['is_active' => true, 'country' => 'Kenya']),
            'plans' => SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'periods' => SubscriptionCatalog::periods(),
        ]));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'email' => 'required|email|max:190',
            'phone' => 'nullable|string|max:64',
            'address' => 'nullable|string|max:2000',
            'plan_id' => ['required', Rule::exists('subscription_plans', 'id')],
            'status' => ['required', Rule::in([SubscriptionCatalog::STATUS_TRIAL, SubscriptionCatalog::STATUS_ACTIVE])],
            'starts_at' => 'required|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:190|unique:users,email',
            'admin_username' => 'required|string|max:100|unique:users,username',
            'admin_password' => 'required|string|min:4|confirmed',
            'notes' => 'nullable|string|max:2000',
        ]);

        $plan = SubscriptionPlan::query()->findOrFail($data['plan_id']);

        $result = DB::transaction(function () use ($data, $plan) {
            $provisioned = $this->provisioner->provision([
                'name' => $data['name'],
                'owner_name' => $data['owner_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
            ], [
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'username' => $data['admin_username'],
                'password' => $data['admin_password'],
                'phone' => $data['phone'] ?? null,
            ]);

            $starts = \Carbon\Carbon::parse($data['starts_at'])->startOfDay();
            $expires = ! empty($data['expires_at'])
                ? \Carbon\Carbon::parse($data['expires_at'])->startOfDay()
                : $this->subscriptions->addPeriod($starts, $plan);

            $this->subscriptions->createForCompany(
                $provisioned['company'],
                $plan,
                $starts,
                $expires,
                $data['status'],
                $data['notes'] ?? null
            );

            return $provisioned;
        });

        return redirect()->route('owner.businesses.show', $result['company'])
            ->with('success', 'Business registered and subscription created.');
    }

    public function show(Company $company)
    {
        $company->load(['subscription.plan', 'users.role', 'branches']);
        $history = SubscriptionHistory::query()
            ->where('company_id', $company->id)
            ->with(['actor', 'previousPlan', 'newPlan'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();
        $payments = SubscriptionPayment::query()
            ->where('company_id', $company->id)
            ->with(['recorder', 'invoice'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
        $invoices = SubscriptionInvoice::query()
            ->where('company_id', $company->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('owner.businesses.show', $this->ownerView([
            'activeMenu' => 'owner.businesses',
            'company' => $company,
            'history' => $history,
            'payments' => $payments,
            'invoices' => $invoices,
            'unpaidInvoices' => $invoices->where('status', SubscriptionInvoice::STATUS_SENT)->values(),
            'plans' => SubscriptionPlan::query()->where('is_active', true)->orderBy('name')->get(),
            'usage' => $this->limits->usage($company),
            'paymentMethods' => config('sellix.payment_methods', []),
        ]));
    }

    public function edit(Company $company)
    {
        return view('owner.businesses.form', $this->ownerView([
            'activeMenu' => 'owner.businesses',
            'company' => $company,
            'plans' => SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'periods' => SubscriptionCatalog::periods(),
        ]));
    }

    public function update(Request $request, Company $company)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'owner_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:190',
            'phone' => 'nullable|string|max:64',
            'address' => 'nullable|string|max:2000',
        ]);

        $before = $company->only(['name', 'owner_name', 'email', 'phone', 'address']);
        $company->update($data);
        $this->audit->record('update', 'companies', $company, $before, $data);

        return redirect()->route('owner.businesses.show', $company)->with('success', 'Business details updated.');
    }

    public function deactivate(Request $request, Company $company)
    {
        $company->update(['is_active' => false]);
        User::query()->where('company_id', $company->id)->update(['is_active' => false]);
        if ($company->subscription) {
            $company->subscription->update(['status' => SubscriptionCatalog::STATUS_DEACTIVATED]);
        }
        $this->audit->record('deactivate', 'companies', $company, ['is_active' => true], ['is_active' => false]);

        return back()->with('success', 'Business deactivated.');
    }

    public function activateBusiness(Request $request, Company $company)
    {
        $company->update(['is_active' => true]);
        User::query()->where('company_id', $company->id)->update(['is_active' => true]);
        if ($company->subscription) {
            $this->subscriptions->activate($company->subscription, $request->input('notes'));
        }
        $this->audit->record('activate', 'companies', $company, ['is_active' => false], ['is_active' => true]);

        return back()->with('success', 'Business activated.');
    }

    public function renew(Request $request, Company $company)
    {
        $subscription = $this->requireSubscription($company);
        $data = $request->validate([
            'plan_id' => ['nullable', Rule::exists('subscription_plans', 'id')],
            'from_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
            'amount' => 'nullable|numeric|min:0',
            'method' => 'nullable|string|max:32',
            'reference' => 'nullable|string|max:64',
            'paid_at' => 'nullable|date',
        ]);

        $plan = ! empty($data['plan_id']) ? SubscriptionPlan::query()->findOrFail($data['plan_id']) : null;
        $from = ! empty($data['from_date']) ? \Carbon\Carbon::parse($data['from_date']) : null;
        $this->subscriptions->renew($subscription, $plan, $from, $data['notes'] ?? null);

        if (! empty($data['amount']) && (float) $data['amount'] > 0) {
            $this->subscriptions->recordPayment(
                $subscription->fresh(),
                (float) $data['amount'],
                $data['method'] ?? 'other',
                $data['paid_at'] ?? now()->toDateString(),
                $data['reference'] ?? null,
                $data['notes'] ?? null
            );
        }

        return back()->with('success', 'Subscription renewed.');
    }

    public function extend(Request $request, Company $company)
    {
        $subscription = $this->requireSubscription($company);
        $data = $request->validate([
            'days' => 'required|integer|min:1|max:3650',
            'notes' => 'nullable|string|max:2000',
        ]);
        $this->subscriptions->extend($subscription, (int) $data['days'], $data['notes'] ?? null);

        return back()->with('success', 'Subscription extended.');
    }

    public function changePlan(Request $request, Company $company)
    {
        $subscription = $this->requireSubscription($company);
        $data = $request->validate([
            'plan_id' => ['required', Rule::exists('subscription_plans', 'id')],
            'notes' => 'nullable|string|max:2000',
        ]);
        $this->subscriptions->changePlan($subscription, SubscriptionPlan::query()->findOrFail($data['plan_id']), $data['notes'] ?? null);

        return back()->with('success', 'Subscription plan changed.');
    }

    public function suspend(Request $request, Company $company)
    {
        $subscription = $this->requireSubscription($company);
        $data = $request->validate(['notes' => 'nullable|string|max:2000']);
        $this->subscriptions->suspend($subscription, $data['notes'] ?? null);

        return back()->with('success', 'Business subscription suspended.');
    }

    public function activate(Request $request, Company $company)
    {
        $subscription = $this->requireSubscription($company);
        $data = $request->validate(['notes' => 'nullable|string|max:2000']);
        if ($subscription->status === SubscriptionCatalog::STATUS_PENDING_APPROVAL) {
            $this->subscriptions->approveRequest($subscription, $data['notes'] ?? null);
        } else {
            $this->subscriptions->activate($subscription, $data['notes'] ?? null);
        }
        $company->update(['is_active' => true]);

        return back()->with('success', 'Subscription activated.');
    }

    public function approve(Request $request, Company $company)
    {
        $subscription = $this->requireSubscription($company);
        $data = $request->validate(['notes' => 'nullable|string|max:2000']);
        $this->subscriptions->approveRequest($subscription, $data['notes'] ?? null);
        $company->update(['is_active' => true]);

        return back()->with('success', 'Subscription request approved. The client can now sign in to the dashboard.');
    }

    public function reject(Request $request, Company $company)
    {
        $subscription = $this->requireSubscription($company);
        $data = $request->validate(['notes' => 'nullable|string|max:2000']);
        $this->subscriptions->rejectRequest($subscription, $data['notes'] ?? null);

        return back()->with('success', 'Subscription request rejected.');
    }

    public function cancel(Request $request, Company $company)
    {
        $subscription = $this->requireSubscription($company);
        $data = $request->validate(['notes' => 'nullable|string|max:2000']);
        $this->subscriptions->cancel($subscription, $data['notes'] ?? null);

        return back()->with('success', 'Subscription cancelled.');
    }

    public function reset(Request $request, Company $company)
    {
        $subscription = $this->requireSubscription($company);
        $data = $request->validate(['notes' => 'nullable|string|max:2000']);
        $this->subscriptions->resetStatus($subscription, $data['notes'] ?? null);

        return back()->with('success', 'Subscription status reset from the current dates.');
    }

    public function payment(Request $request, Company $company)
    {
        $subscription = $this->requireSubscription($company);
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string|max:32',
            'reference' => 'nullable|string|max:64',
            'paid_at' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'invoice_id' => ['nullable', Rule::exists('subscription_invoices', 'id')->where('company_id', $company->id)],
        ]);
        $invoice = ! empty($data['invoice_id'])
            ? SubscriptionInvoice::query()->where('company_id', $company->id)->find($data['invoice_id'])
            : null;
        $this->subscriptions->recordPayment(
            $subscription,
            (float) $data['amount'],
            $data['method'],
            $data['paid_at'],
            $data['reference'] ?? null,
            $data['notes'] ?? null,
            $invoice
        );

        return back()->with('success', 'Payment recorded.');
    }

    public function invoice(Request $request, Company $company)
    {
        $this->requireSubscription($company);
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'due_date' => 'required|date',
            'email' => 'nullable|email|max:190',
            'notes' => 'nullable|string|max:2000',
        ]);

        $result = $this->subscriptions->issueInvoice(
            $company,
            (float) $data['amount'],
            \Carbon\Carbon::parse($data['due_date'])->startOfDay(),
            $data['email'] ?? $company->email,
            $data['notes'] ?? null,
            true
        );

        $message = 'Invoice '.$result['invoice']->invoice_number.' created.';
        if ($result['mailed']) {
            $message .= ' It was emailed to '.$result['invoice']->sent_to_email.'.';
        } elseif ($result['mail_error']) {
            $message .= ' The invoice is in the system, but email could not be sent: '.$result['mail_error'];
        } else {
            $message .= ' No email address was available, so it was saved for the business to view under Billing.';
        }

        return back()->with('success', $message);
    }

    protected function requireSubscription(Company $company)
    {
        $company->load('subscription.plan');
        abort_unless($company->subscription, 422, 'This business has no subscription yet.');

        return $company->subscription;
    }

    protected function ownerView(array $data): array
    {
        return array_merge(fleet_shared_view_data(), $data, ['ownerConsole' => true]);
    }
}
