<?php

namespace App\Providers;

use App\Models\CustomLutBuild;
use App\Models\DownloadEvent;
use App\Models\Entitlement;
use App\Models\LutTestUpload;
use App\Models\Order;
use App\Models\WizardProject;
use App\Models\WizardProjectPhoto;
use App\Models\WizardProjectVariant;
use App\Policies\CustomLutBuildPolicy;
use App\Policies\DownloadEventPolicy;
use App\Policies\EntitlementPolicy;
use App\Policies\LutTestUploadPolicy;
use App\Policies\OrderPolicy;
use App\Policies\WizardProjectPhotoPolicy;
use App\Policies\WizardProjectPolicy;
use App\Policies\WizardProjectVariantPolicy;
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
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Entitlement::class, EntitlementPolicy::class);
        Gate::policy(CustomLutBuild::class, CustomLutBuildPolicy::class);
        Gate::policy(DownloadEvent::class, DownloadEventPolicy::class);
        Gate::policy(WizardProject::class, WizardProjectPolicy::class);
        Gate::policy(WizardProjectPhoto::class, WizardProjectPhotoPolicy::class);
        Gate::policy(WizardProjectVariant::class, WizardProjectVariantPolicy::class);
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

        RateLimiter::for('lut-wizard-create', fn (Request $request): Limit => Limit::perMinute((int) config('lut-wizard.project_creation_rate_limit', 5))
            ->by((string) ($request->user()?->getAuthIdentifier() ?: $request->ip())));

        RateLimiter::for('lut-wizard-mutation', fn (Request $request): Limit => Limit::perMinute((int) config('lut-wizard.project_mutation_rate_limit', 120))
            ->by((string) ($request->user()?->getAuthIdentifier() ?: $request->ip())));

        RateLimiter::for('lut-wizard-photo-upload', fn (Request $request): Limit => Limit::perMinute((int) config('lut-wizard.photo_upload_rate_limit', 5))
            ->by((string) ($request->user()?->getAuthIdentifier() ?: $request->ip())));

        RateLimiter::for('lut-wizard-variation', function (Request $request): array {
            $userKey = (string) ($request->user()?->getAuthIdentifier() ?: $request->ip());

            return [
                Limit::perMinute((int) config('lut-wizard.variation_per_minute_limit', 5))->by($userKey),
                Limit::perDay((int) config('lut-wizard.variation_daily_limit', 20))->by($userKey),
            ];
        });

        RateLimiter::for('lut-wizard-duplicate', fn (Request $request): Limit => Limit::perMinute((int) config('lut-wizard.duplicate_rate_limit', 10))
            ->by((string) ($request->user()?->getAuthIdentifier() ?: $request->ip())));

        RateLimiter::for('lut-wizard-preview', fn (Request $request): Limit => Limit::perMinute((int) config('lut-wizard.private_preview_rate_limit', 180))
            ->by((string) ($request->user()?->getAuthIdentifier() ?: $request->ip())));

        RateLimiter::for('custom-lut-build', function (Request $request): array {
            $userKey = (string) ($request->user()?->getAuthIdentifier() ?: $request->ip());
            $project = $request->route('wizardProject');
            $projectKey = is_object($project) && method_exists($project, 'getKey') ? (string) $project->getKey() : 'unknown';

            return [
                Limit::perHour((int) config('custom-lut-builds.maximum_builds_per_project_per_hour', 5))->by($userKey.'|'.$projectKey),
                Limit::perDay((int) config('custom-lut-builds.maximum_builds_per_user_per_day', 20))->by($userKey),
            ];
        });

        RateLimiter::for('custom-lut-build-status', fn (Request $request): Limit => Limit::perMinute(120)
            ->by((string) ($request->user()?->getAuthIdentifier() ?: $request->ip())));

        RateLimiter::for('custom-lut-build-delete', fn (Request $request): Limit => Limit::perMinute(20)
            ->by((string) ($request->user()?->getAuthIdentifier() ?: $request->ip())));

        RateLimiter::for('checkout-create', function (Request $request): array {
            $userKey = (string) ($request->user()?->getAuthIdentifier() ?: 'guest');

            return [
                Limit::perMinute((int) config('checkout.throttles.checkout_per_minute', 10))->by($userKey),
                Limit::perHour((int) config('checkout.throttles.checkout_per_hour', 60))->by($userKey),
            ];
        });

        RateLimiter::for('checkout-capture', function (Request $request): array {
            $userKey = (string) ($request->user()?->getAuthIdentifier() ?: 'guest');
            $order = $request->route('order');
            $orderKey = is_object($order) && method_exists($order, 'getKey') ? (string) $order->getKey() : 'unknown';

            return [
                Limit::perMinute((int) config('checkout.throttles.capture_per_minute', 10))->by($userKey.'|'.$orderKey),
            ];
        });

        RateLimiter::for('checkout-free-claim', function (Request $request): array {
            $userKey = (string) ($request->user()?->getAuthIdentifier() ?: 'guest');

            return [
                Limit::perMinute((int) config('checkout.throttles.free_claims_per_minute', 5))->by($userKey),
            ];
        });

        RateLimiter::for('account-downloads', function (Request $request): array {
            $userKey = (string) ($request->user()?->getAuthIdentifier() ?: 'guest');

            return [
                Limit::perMinutes(10, (int) config('checkout.throttles.downloads_per_ten_minutes', 10))->by($userKey),
            ];
        });

        RateLimiter::for('custom-lut-checkout-page', fn (Request $request): Limit => Limit::perMinute((int) config('custom-lut-commerce.rate_limits.checkout_page_per_minute', 30))
            ->by((string) ($request->user()?->getAuthIdentifier() ?: $request->ip())));

        RateLimiter::for('custom-lut-checkout', function (Request $request): array {
            $userKey = (string) ($request->user()?->getAuthIdentifier() ?: 'guest');

            return [
                Limit::perMinute((int) config('custom-lut-commerce.rate_limits.checkout_per_minute', 10))->by($userKey),
            ];
        });

        RateLimiter::for('entitlement-downloads', function (Request $request): array {
            $userKey = (string) ($request->user()?->getAuthIdentifier() ?: 'guest');

            return [
                Limit::perMinutes(10, (int) config('checkout.throttles.downloads_per_ten_minutes', 10))->by($userKey),
            ];
        });

        RateLimiter::for('health', fn (Request $request): Limit => Limit::perMinute((int) config('security.health.rate_limit_per_minute', 120))
            ->by((string) ($request->ip() ?: 'unknown')));
    }
}
