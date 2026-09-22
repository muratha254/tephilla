<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index()
    {
        $this->authorizeBilling();
        $companyId = auth()->user()->company_id;

        $invoices = SubscriptionInvoice::query()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->get();

        $payments = SubscriptionPayment::query()
            ->where('company_id', $companyId)
            ->with('invoice')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        $company = auth()->user()->company;
        $subscription = $company ? $company->subscription : null;
        $home = ($subscription && $subscription->allowsAccess()) ? route('dashboard') : route('billing.index');

        return view('billing.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'billing.index',
            'invoices' => $invoices,
            'payments' => $payments,
            'company' => $company,
            'subscription' => $subscription,
            'billingHome' => $home,
        ]));
    }

    public function show(SubscriptionInvoice $invoice)
    {
        $this->authorizeBilling();
        abort_if((int) $invoice->company_id !== (int) auth()->user()->company_id, 404);
        $invoice->load(['company', 'subscription.plan', 'payments']);

        return view('billing.show', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'billing.index',
            'invoice' => $invoice,
        ]));
    }

    protected function authorizeBilling(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->company_id, 403);
        abort_if($user->isSystemOwner(), 403);
        abort_unless(
            $user->isCompanyAdmin()
            || $user->hasPermission('settings.view')
            || $user->hasPermission('settings.company'),
            403
        );
    }
}
