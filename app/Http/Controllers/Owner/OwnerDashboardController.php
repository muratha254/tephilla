<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\User;
use App\Support\SubscriptionCatalog;
use Illuminate\Http\Request;

class OwnerDashboardController extends Controller
{
    public function index(Request $request)
    {
        $companies = Company::query()->with('subscription.plan');
        $subscriptions = Subscription::query()->with(['plan', 'company']);

        $cards = [
            'total' => (clone $companies)->count(),
            'pending' => 0,
            'active' => 0,
            'expiring' => 0,
            'expired' => 0,
            'suspended' => 0,
            'trial' => 0,
            'users' => User::query()->whereNotNull('company_id')->count(),
        ];

        foreach ((clone $companies)->get() as $company) {
            if (! $company->is_active) {
                continue;
            }
            $status = optional($company->subscription)->effectiveStatus() ?: SubscriptionCatalog::STATUS_EXPIRED;
            if ($status === SubscriptionCatalog::STATUS_PENDING_APPROVAL) {
                $cards['pending']++;
            } elseif ($status === SubscriptionCatalog::STATUS_ACTIVE) {
                $cards['active']++;
            } elseif ($status === SubscriptionCatalog::STATUS_EXPIRING_SOON) {
                $cards['expiring']++;
            } elseif ($status === SubscriptionCatalog::STATUS_EXPIRED) {
                $cards['expired']++;
            } elseif ($status === SubscriptionCatalog::STATUS_SUSPENDED) {
                $cards['suspended']++;
            } elseif ($status === SubscriptionCatalog::STATUS_TRIAL) {
                $cards['trial']++;
            }
        }

        $pending = Subscription::query()->with(['plan', 'company'])
            ->where('status', SubscriptionCatalog::STATUS_PENDING_APPROVAL)
            ->orderByDesc('id')
            ->limit(12)
            ->get();
        $recent = Company::query()->with('subscription.plan')->orderByDesc('id')->limit(8)->get();
        $expiring = Subscription::query()->with(['plan', 'company'])->get()
            ->filter(fn (Subscription $row) => $row->effectiveStatus() === SubscriptionCatalog::STATUS_EXPIRING_SOON)
            ->sortBy('expires_at')
            ->take(8)
            ->values();
        $expired = Subscription::query()->with(['plan', 'company'])->get()
            ->filter(fn (Subscription $row) => $row->effectiveStatus() === SubscriptionCatalog::STATUS_EXPIRED)
            ->sortByDesc('expires_at')
            ->take(8)
            ->values();
        $activity = SubscriptionHistory::query()
            ->with(['company', 'actor', 'newPlan'])
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        return view('owner.dashboard', $this->ownerView([
            'activeMenu' => 'owner.dashboard',
            'cards' => $cards,
            'pending' => $pending,
            'recent' => $recent,
            'expiring' => $expiring,
            'expired' => $expired,
            'activity' => $activity,
        ]));
    }

    protected function ownerView(array $data): array
    {
        return array_merge(fleet_shared_view_data(), $data, [
            'ownerConsole' => true,
        ]);
    }
}
