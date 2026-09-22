<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use App\Services\SystemOwnerBootstrapper;
use App\Support\SubscriptionCatalog;
use Illuminate\Database\Seeder;

class SubscriptionBootstrapSeeder extends Seeder
{
    public function run()
    {
        $bootstrap = app(SystemOwnerBootstrapper::class);
        $bootstrap->ensurePlans();
        $owner = $bootstrap->ensureOwnerUser();

        $plan = SubscriptionPlan::query()->where('slug', 'enterprise')->first();
        $service = app(SubscriptionService::class);

        if ($plan) {
            Company::query()->each(function (Company $company) use ($plan, $service) {
                if ($company->subscription()->exists()) {
                    return;
                }
                $service->createForCompany(
                    $company,
                    $plan,
                    now()->startOfDay(),
                    now()->addYear()->startOfDay(),
                    SubscriptionCatalog::STATUS_ACTIVE,
                    'Bootstrapped so existing businesses stay online.'
                );
            });
        }

        if ($this->command) {
            $this->command->info('System Owner: ' . $owner->email . ' / 1234');
        }
    }
}
