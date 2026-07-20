<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn () => Inertia::render('auth/Login', [
            'status' => session('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/Register', [
            'countries' => $this->countries(),
        ]));

        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('auth/ForgotPassword', [
            'status' => session('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->string('email')->toString(),
            'token' => $request->route('token'),
        ]));

        Fortify::verifyEmailView(fn () => Inertia::render('auth/VerifyEmail', [
            'status' => session('status'),
        ]));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }

    /**
     * @return array<string, string>
     */
    private function countries(): array
    {
        /** @var array<string, string> $countries */
        $countries = config('countries');

        return $countries;
    }
}
