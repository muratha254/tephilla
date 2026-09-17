<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Throwable;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            \Laravel\Fortify\Http\Requests\LoginRequest::class,
            \App\Http\Requests\LoginRequest::class
        );

        $this->app->singleton(
            \Laravel\Fortify\Contracts\LoginResponse::class,
            \App\Http\Responses\LoginResponse::class
        );

        $this->app->singleton(
            \Laravel\Fortify\Contracts\LogoutResponse::class,
            \App\Http\Responses\LogoutResponse::class
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Fortify::loginView(fn () => view('auth.login'));

        Fortify::authenticateUsing(function (Request $request) {
            $login = $request->input(Fortify::username());

            $user = User::query()
                ->where(function ($query) use ($login) {
                    $query->where('email', $login)->orWhere('username', $login);
                })
                ->first();

            if (
                $user
                && $user->is_active
                && Hash::check($request->password, $user->password)
            ) {
                $user->forceFill(['last_login_at' => now()])->save();

                return $user;
            }
        });

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Share users list with login view - include ALL users including admin
        View::composer('auth.login', function ($view) {
            try {
                User::query()->count();
            } catch (Throwable $e) {
                Log::warning('Login view: could not reach database', ['error' => $e->getMessage()]);
                $view->with('dbUnavailable', true);
            }
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::none();
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::none();
        });
    }
}
