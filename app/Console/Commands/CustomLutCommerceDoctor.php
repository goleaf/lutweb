<?php

namespace App\Console\Commands;

use App\Models\CustomLutBuild;
use App\Models\CustomLutBuildFile;
use App\Models\CustomLutCommerceSetting;
use App\Services\Checkout\CheckoutReadiness;
use App\Services\PayPal\PayPalApiException;
use App\Services\PayPal\PayPalHttpClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

#[Signature('custom-lut-commerce:doctor {--remote : Perform safe read-only PayPal API checks} {--show-routes : Print relevant route names and URI templates} {--integrity-sample : Verify one safe sale-ready package sample}')]
#[Description('Check Custom LUT commerce, delivery, and launch readiness.')]
class CustomLutCommerceDoctor extends Command
{
    private int $failures = 0;

    private int $warnings = 0;

    public function handle(CheckoutReadiness $readiness, PayPalHttpClient $paypal): int
    {
        $setting = CustomLutCommerceSetting::query()
            ->where('scope', CustomLutCommerceSetting::Scope)
            ->first();

        $configEnabled = (bool) config('custom-lut-commerce.enabled');
        $databaseEnabled = $setting?->is_enabled === true;
        $acceptingSales = $configEnabled && $databaseEnabled;
        $live = $readiness->isLiveMode();

        $this->pass('Custom LUT commerce configuration exists');
        $this->warnWhen(! $configEnabled, 'Custom LUT commerce is intentionally disabled in configuration');
        $this->check('Database commerce settings row exists', $setting instanceof CustomLutCommerceSetting, required: $configEnabled);
        $this->warnWhen(! $databaseEnabled, 'Custom LUT commerce is disabled in database settings');

        $this->check('Custom LUT price is greater than zero', ($setting->price_cents ?? 0) > 0, required: $acceptingSales);
        $this->check('Price is stored as int cents', is_int($setting?->price_cents), required: $acceptingSales);
        $this->check('Currency is EUR', ($setting->currency ?? config('custom-lut-commerce.currency')) === 'EUR', required: true);
        $this->check('Settings version is valid', ($setting->version ?? 0) >= 1, required: $configEnabled);

        $this->check('Global checkout configuration', (bool) config('checkout.enabled'), required: $acceptingSales);
        $this->check('PayPal enabled state', (bool) config('paypal.enabled'), required: $acceptingSales);
        $this->pass('PayPal mode: '.config('paypal.mode'));
        $this->check('Client ID exists', filled(config('paypal.client_id')), required: $acceptingSales);
        $this->check('Client secret exists', filled(config('paypal.client_secret')), required: $acceptingSales);
        $this->check('Merchant ID exists', filled(config('paypal.merchant_id')), required: $live && $acceptingSales);
        $this->check('Webhook ID exists', filled(config('paypal.webhook_id')), required: $live && $acceptingSales);
        $this->check('Fixed API host', in_array($readiness->apiUrl(), ['https://api-m.sandbox.paypal.com', 'https://api-m.paypal.com'], true), required: true);
        $this->check('PayPal JavaScript SDK v6 URL', str_contains($readiness->sdkUrl(), '/web-sdk/v6/core'), required: true);

        $this->checkRoute('Webhook route exists', 'webhooks.paypal', true);
        $this->checkRoute('Capture route exists', 'account.orders.paypal.capture', true);
        $this->checkRoute('Custom LUT checkout route exists', 'custom-lut.checkout.show', true);
        $this->checkRoute('Custom LUT purchase account route exists', 'account.custom-luts.purchased.index', true);
        $this->checkRoute('Generic entitlement download route supports Custom LUT', 'account.custom-luts.download', true);

        $this->check('Private Custom LUT build disk exists', array_key_exists((string) config('custom-lut-commerce.private_disk', 'private'), config('filesystems.disks', [])), required: true);
        $this->check('Private disk is writable', $this->diskIsWritable(), required: $acceptingSales);
        $this->check('No public Custom LUT build-file route exists', ! $this->publicBuildFileRouteExists(), required: true);
        $this->check('No public storage URL is used for Custom LUT packages', ! $this->sourceContains('Storage::url(', ['app/Services/Checkout', 'app/Services/Downloads', 'app/Http/Controllers/CustomLut']), required: true);

        $this->checkFinalVersion('Active final License template exists', config('legal.license_version'), required: $live && $acceptingSales);
        $this->checkFinalVersion('Terms of Sale version is final for live mode', config('legal.terms_of_sale_version'), required: $live && $acceptingSales);
        $this->checkFinalVersion('Refund Policy version is final for live mode', config('legal.refund_policy_version'), required: $live && $acceptingSales);
        $this->checkFinalVersion('Digital-delivery consent version is final for live mode', config('legal.digital_delivery_consent_version'), required: $live && $acceptingSales);
        $this->check('Active final Guide template exists when required', filled(config('legal.license_version')), required: false);
        $this->check('Seller country exists for live mode', ! $live || $this->validCountry(config('checkout.seller_country_code')), required: $live && $acceptingSales);
        $this->check('Tax readiness is true for live mode', ! $live || (bool) config('checkout.tax_ready'), required: $live && $acceptingSales);
        $this->check('Live payments are explicitly allowed', ! $live || (bool) config('checkout.live_payments_allowed'), required: $live && $acceptingSales);

        $queue = (string) config('queue.default');
        $this->pass('Queue connection: '.$queue);
        $this->check('Production does not use sync queue', ! app()->isProduction() || $queue !== 'sync', required: false);
        $this->check('Mail configuration warning', filled(config('mail.from.address')), required: false);
        $this->check('At least one sale-ready build exists where practical', CustomLutBuild::query()->saleReady()->exists(), required: false);
        $this->check('Build-prune logic protects Order-referenced builds', $this->sourceContains('mayBeDeleted()', ['app/Services/LutWizard/DeleteWizardProject.php']), required: true);
        $this->check('Project deletion protects purchased builds', $this->sourceContains('wizard_project_id', ['app/Services/LutWizard/DeleteWizardProject.php']) && $this->sourceContains('mayBeDeleted()', ['app/Services/LutWizard/DeleteWizardProject.php']), required: true);
        $this->check('Existing PayPal webhook payload purge schedule exists', $this->sourceContains('paypal:webhooks:purge-payloads', ['routes/console.php']), required: $acceptingSales);
        $this->check('Existing Custom LUT build prune schedule exists', $this->sourceContains('lut-wizard:prune', ['routes/console.php']), required: true);
        $this->check('No refund route exists', ! $this->applicationRefundRouteExists(), required: true);
        $this->check('No manual mark-paid action exists', ! $this->manualMarkPaidRouteExists(), required: true);

        if ((bool) $this->option('show-routes')) {
            $this->showRoutes();
        }

        if ((bool) $this->option('integrity-sample')) {
            $this->integritySample();
        }

        if ((bool) $this->option('remote')) {
            $this->remoteChecks($paypal);
        }

        $this->line('Doctor complete: '.$this->failures.' FAIL, '.$this->warnings.' WARN.');

        return $this->failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function checkRoute(string $label, string $routeName, bool $required): void
    {
        $this->check($label, Route::has($routeName), $required);
    }

    private function checkFinalVersion(string $label, mixed $version, bool $required): void
    {
        $this->check($label, is_string($version) && $version !== '' && ! Str::startsWith($version, 'draft-'), $required);
    }

    private function diskIsWritable(): bool
    {
        $diskName = (string) config('custom-lut-commerce.private_disk', 'private');
        $prefix = trim((string) config('custom-lut-commerce.build_prefix', 'custom-lut-builds'), '/');
        $path = $prefix.'/doctor-write-check.txt';

        try {
            $disk = Storage::disk($diskName);
            $disk->put($path, 'ok');
            $exists = $disk->exists($path);
            $disk->delete($path);

            return $exists;
        } catch (Throwable) {
            return false;
        }
    }

    private function publicBuildFileRouteExists(): bool
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_contains($uri, 'custom-lut')) {
                continue;
            }

            if (Str::startsWith($uri, ['admin/', 'account/', '_debugbar/'])) {
                continue;
            }

            if (str_contains($uri, 'download') || str_contains($uri, 'files')) {
                return true;
            }
        }

        return false;
    }

    private function applicationRefundRouteExists(): bool
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();
            $uri = $route->uri();

            if ($name === 'refund-policy' || $uri === 'refund-policy') {
                continue;
            }

            if (str_contains($name, 'refund') || str_contains($uri, 'refund')) {
                return true;
            }
        }

        return false;
    }

    private function manualMarkPaidRouteExists(): bool
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $haystack = strtolower($route->uri().' '.(string) $route->getName().' '.($route->getActionName() ?: ''));

            if (str_contains($haystack, 'mark-paid') || str_contains($haystack, 'markpaid')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $paths
     */
    private function sourceContains(string $needle, array $paths): bool
    {
        foreach ($paths as $path) {
            $absolutePath = base_path($path);

            if (is_file($absolutePath)) {
                if (str_contains((string) file_get_contents($absolutePath), $needle)) {
                    return true;
                }

                continue;
            }

            if (! is_dir($absolutePath)) {
                continue;
            }

            $files = glob($absolutePath.'/*.php') ?: [];

            foreach ($files as $file) {
                if (str_contains((string) file_get_contents($file), $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function showRoutes(): void
    {
        $this->line('Relevant routes:');

        foreach ([
            'webhooks.paypal',
            'account.orders.paypal.capture',
            'custom-lut.checkout.show',
            'custom-lut.checkout.paypal.orders.store',
            'account.custom-luts.purchased.index',
            'account.custom-luts.purchased.show',
            'account.custom-luts.download',
        ] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            if (! $route instanceof LaravelRoute) {
                $this->line(' - '.$routeName.': missing');

                continue;
            }

            $this->line(' - '.$routeName.': '.$route->methods()[0].' /'.$route->uri());
        }
    }

    private function integritySample(): void
    {
        $build = CustomLutBuild::query()
            ->saleReady()
            ->whereDoesntHave('entitlements')
            ->with('packageFile')
            ->first();

        if (! $build instanceof CustomLutBuild) {
            $this->warnLine('No unowned sale-ready build found for integrity sample.');

            return;
        }

        $file = $build->packageFile;

        $this->check('Sale-ready sample has private PackageZip', $file instanceof CustomLutBuildFile && $file->disk === config('custom-lut-commerce.private_disk', 'private'), required: false);
        $this->check('PackageZip metadata exists', $file instanceof CustomLutBuildFile && $file->size_bytes > 0 && filled($file->sha256), required: false);

        if (! $file instanceof CustomLutBuildFile || ! Storage::disk($file->disk)->exists($file->path)) {
            $this->check('Integrity sample package exists', false, required: false);

            return;
        }

        $this->check('Integrity sample package exists', true, required: false);

        if (filled($file->sha256)) {
            $this->check('Integrity sample SHA-256 matches metadata', $this->streamHashMatches($file), required: false);
        }
    }

    private function streamHashMatches(CustomLutBuildFile $file): bool
    {
        $stream = Storage::disk($file->disk)->readStream($file->path);

        if ($stream === null) {
            return false;
        }

        try {
            $context = hash_init('sha256');

            while (! feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);

                if ($chunk === false) {
                    return false;
                }

                hash_update($context, $chunk);
            }

            return hash_equals(strtolower((string) $file->sha256), hash_final($context));
        } finally {
            fclose($stream);
        }
    }

    private function remoteChecks(PayPalHttpClient $paypal): void
    {
        try {
            $response = $paypal->get('/v1/notifications/webhooks');
        } catch (PayPalApiException $exception) {
            $this->check('Remote PayPal webhook/account check completed', false, required: true);

            return;
        }

        $webhooks = is_array($response['webhooks'] ?? null) ? array_values($response['webhooks']) : [];

        $this->check('Remote PayPal webhook/account check completed', true, required: false);
        $this->check('Configured webhook ID exists remotely', $this->remoteWebhookExists($webhooks), required: filled(config('paypal.webhook_id')));
    }

    /**
     * @param  list<mixed>  $webhooks
     */
    private function remoteWebhookExists(array $webhooks): bool
    {
        foreach ($webhooks as $webhook) {
            if (is_array($webhook) && ($webhook['id'] ?? null) === config('paypal.webhook_id')) {
                return true;
            }
        }

        return false;
    }

    private function check(string $label, bool $passes, bool $required): void
    {
        if ($passes) {
            $this->pass($label);

            return;
        }

        if ($required) {
            $this->failLine($label);

            return;
        }

        $this->warnLine($label);
    }

    private function warnWhen(bool $warns, string $label): void
    {
        if ($warns) {
            $this->warnLine($label);
        }
    }

    private function pass(string $label): void
    {
        $this->line('PASS '.$label);
    }

    private function warnLine(string $label): void
    {
        $this->warnings++;
        $this->line('WARN '.$label);
    }

    private function failLine(string $label): void
    {
        $this->failures++;
        $this->line('FAIL '.$label);
    }

    private function validCountry(mixed $country): bool
    {
        return is_string($country) && preg_match('/^[A-Z]{2}$/', $country) === 1;
    }
}
