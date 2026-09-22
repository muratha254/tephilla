<?php

namespace App\Http\Controllers;

use App\Support\SubscriptionCatalog;
use Illuminate\Http\Request;

class SubscriptionGateController extends Controller
{
    public function blocked(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 403);

        if ($user->isSystemOwner()) {
            return redirect()->route('owner.dashboard');
        }

        $company = $user->company;
        $subscription = $company ? $company->subscription()->with('plan')->first() : null;
        $reason = (string) $request->query('reason', $subscription ? $subscription->effectiveStatus() : SubscriptionCatalog::STATUS_EXPIRED);

        if ($company && $company->is_active && $subscription && $subscription->allowsAccess() && $reason !== SubscriptionCatalog::STATUS_DEACTIVATED) {
            return redirect()->route('dashboard');
        }

        $title = 'Subscription expired';
        $message = 'This business subscription is no longer active. Please contact the System Owner to renew access.';

        if ($reason === SubscriptionCatalog::STATUS_PENDING_APPROVAL) {
            $title = 'Pending approval';
            $message = 'Your subscription request is waiting for the System Owner to review it. You cannot use the dashboard until it is approved.';
        } elseif ($reason === SubscriptionCatalog::STATUS_REJECTED) {
            $title = 'Request rejected';
            $message = 'The System Owner rejected this subscription request. Please contact the System Owner for more information.';
        } elseif ($reason === SubscriptionCatalog::STATUS_SUSPENDED) {
            $title = 'Account suspended';
            $message = 'This business account has been suspended. Please contact the System Owner.';
        } elseif ($reason === SubscriptionCatalog::STATUS_CANCELLED) {
            $title = 'Subscription cancelled';
            $message = 'This subscription has been cancelled. Please contact the System Owner to restore access.';
        } elseif ($reason === SubscriptionCatalog::STATUS_DEACTIVATED || ($company && ! $company->is_active)) {
            $title = 'Account deactivated';
            $message = 'This business account is deactivated. Please contact the System Owner.';
            $reason = SubscriptionCatalog::STATUS_DEACTIVATED;
        }

        return view('subscription.blocked', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'subscription.blocked',
            'title' => $title,
            'message' => $message,
            'reason' => $reason,
            'company' => $company,
            'subscription' => $subscription,
            'ownerConsole' => false,
            'hideWorkspaceChrome' => true,
        ]));
    }
}
