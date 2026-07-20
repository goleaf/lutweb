<?php

namespace App\Providers;

use App\Models\LutTestUpload;
use App\Policies\LutTestUploadPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiters();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureAuthorization(): void
    {
        Gate::policy(LutTestUpload::class, LutTestUploadPolicy::class);
    }

    protected function configureRateLimiters(): void
    {
        RateLimiter::for('lut-tester-upload', function (Request $request): array {
            $userKey = $request->user()?->getAuthIdentifier() ?: 'guest';
            $ipKey = $request->ip() ?: 'unknown';

            return [
                Limit::perMinute((int) config('lut-tester.upload_rate_limits.per_minute', 5))
                    ->by($userKey.'|'.$ipKey),
                Limit::perDay((int) config('lut-tester.upload_rate_limits.per_day', 30))
                    ->by((string) $userKey),
            ];
        });
    }
}
