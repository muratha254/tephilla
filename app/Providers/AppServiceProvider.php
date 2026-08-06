<?php

namespace App\Providers;

use App\Models\Message;
use App\Models\Setting;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        view()->composer('layouts.master', function ($view) {
            $view->with('setting', $this->safeSetting());
            if (auth()->check()) {
                try {
                    $view->with('chatUnreadCount', Message::unreadBy(auth()->id())->count());
                } catch (Throwable $e) {
                    Log::warning('View composer: could not load chat unread count', ['error' => $e->getMessage()]);
                    $view->with('chatUnreadCount', 0);
                }
            } else {
                $view->with('chatUnreadCount', 0);
            }
        });
        view()->composer('layouts.auth', function ($view) {
            $view->with('setting', $this->safeSetting());
        });
        view()->composer('auth.login', function ($view) {
            $view->with('setting', $this->safeSetting());
        });
        view()->composer('layouts.fleet', function ($view) {
            try {
                $shared = fleet_shared_view_data();
                $view->with($shared);
            } catch (Throwable $e) {
                Log::warning('View composer: could not load fleet settings', ['error' => $e->getMessage()]);
                if (! $view->offsetExists('companyName')) {
                    $view->with('companyName', fleet_system_name());
                }
            }
        });
        view()->composer(['layouts.auth', 'auth.login'], function ($view) {
            try {
                $view->with('documentCompanyProfile', fleet_company_profile());
                $view->with('companyName', fleet_system_name());
                $view->with('systemName', fleet_system_name());
                $view->with('companyLogoUrl', null);
            } catch (Throwable $e) {
                Log::warning('View composer: could not load fleet settings for auth', ['error' => $e->getMessage()]);
            }
        });
    }

    /**
     * Load app settings without breaking the whole page if MySQL is down.
     */
    private function safeSetting()
    {
        try {
            return Setting::first();
        } catch (Throwable $e) {
            Log::warning('View composer: could not load setting', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Only force APP_URL in non-local environments to avoid session loss
        // when browsing via a different host (e.g. localhost vs 127.0.0.1).
        $appUrl = config('app.url');
        if ($appUrl && ! app()->environment('local')) {
            URL::forceRootUrl($appUrl);
            URL::forceScheme(parse_url($appUrl, PHP_URL_SCHEME) ?: 'http');
        }

        try {
            \App\Models\FleetSetting::applyMailConfig();
        } catch (Throwable $e) {
            Log::warning('Could not apply fleet SMTP settings', ['error' => $e->getMessage()]);
        }
    }
}
