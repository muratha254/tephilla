<?php

namespace App\Providers;

use App\Support\RuntimeSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        view()->composer(['layouts.auth', 'auth.login', 'auth.register', 'auth.register.*'], function ($view) {
            $view->with('setting', RuntimeSettings::forLogin());
            $view->with('companyName', fleet_system_name());
            $view->with('systemName', fleet_system_name());
            $view->with('documentCompanyProfile', fleet_company_profile());
            $view->with('companyLogoUrl', fleet_company_profile()['logo_url'] ?? null);
        });

        view()->composer('layouts.fleet', function ($view) {
            try {
                $view->with(fleet_shared_view_data());
            } catch (Throwable $e) {
                Log::warning('View composer: could not load company profile', ['error' => $e->getMessage()]);
                $view->with('companyName', fleet_system_name());
                $view->with('systemName', fleet_system_name());
            }
        });
    }

    public function boot()
    {
        $appUrl = config('app.url');
        if ($appUrl && ! app()->environment('local')) {
            URL::forceRootUrl($appUrl);
            URL::forceScheme(parse_url($appUrl, PHP_URL_SCHEME) ?: 'http');
        }
    }
}
