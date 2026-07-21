<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Checkout\CheckoutReadiness;
use App\Services\Checkout\ProductPurchaseEligibility;
use App\Services\PayPal\PayPalApiException;
use App\Services\PayPal\PayPalHttpClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('paypal:doctor {--remote : Perform safe read-only PayPal API checks} {--show-webhook-url : Print the recommended webhook URL}')]
#[Description('Check PayPal checkout, webhook, and live-sale readiness.')]
class PayPalDoctor extends Command
{
    /**
     * @var list<string>
     */
    private array $failures = [];

    /**
     * @var list<string>
     */
    private array $warnings = [];

    public function handle(CheckoutReadiness $readiness, ProductPurchaseEligibility $eligibility, PayPalHttpClient $paypal): int
    {
        $live = $readiness->isLiveMode();

        $this->check('PayPal enabled state: '.($this->bool(config('paypal.enabled'))), (bool) config('paypal.enabled'), required: $live);
        $this->check('Checkout enabled state: '.($this->bool(config('checkout.enabled'))), (bool) config('checkout.enabled'), required: $live);
        $this->pass('PayPal mode: '.config('paypal.mode'));
        $this->check('Public PayPal client ID exists', filled(config('paypal.client_id')), required: $live);
        $this->check('PayPal client secret exists', filled(config('paypal.client_secret')), required: $live);
        $this->check('PayPal merchant ID exists', filled(config('paypal.merchant_id')), required: $live);
        $this->check('PayPal webhook ID exists', filled(config('paypal.webhook_id')), required: $live);
        $this->pass('REST API host: '.$readiness->apiUrl());
        $this->pass('JavaScript SDK v6 host: '.$readiness->sdkUrl());
        $this->check('EUR currency', config('paypal.currency') === 'EUR' && config('checkout.currency') === 'EUR', required: true);
        $this->check('APP_URL exists', filled(config('app.url')), required: $live);
        $this->check('HTTPS APP_URL in live mode', ! $live || Str::startsWith((string) config('app.url'), 'https://'), required: $live);
        $this->check('webhooks.paypal route exists', Route::has('webhooks.paypal'), required: true);

        if (Route::has('webhooks.paypal')) {
            $webhookUrl = route('webhooks.paypal');
            $this->check('Webhook URL uses HTTPS in live mode', ! $live || Str::startsWith($webhookUrl, 'https://'), required: $live);

            if ((bool) $this->option('show-webhook-url')) {
                $this->line('Recommended webhook URL: '.$webhookUrl);
            }
        }

        $queue = (string) config('queue.default');
        $this->pass('Queue connection: '.$queue);
        $this->check('Live mode does not use sync queue', ! $live || $queue !== 'sync', required: false);
        $this->check('Mail from address exists', filled(config('mail.from.address')), required: false);
        $this->check('Seller country exists for live mode', ! $live || $this->validCountry(config('checkout.seller_country_code')), required: $live);
        $this->check('Tax handling ready for live mode', ! $live || (bool) config('checkout.tax_ready'), required: $live);
        $this->check('Live payments explicitly allowed', ! $live || (bool) config('checkout.live_payments_allowed'), required: $live);
        $this->checkLegalVersions($live);
        $this->checkPrivateDisk($live);
        $this->checkPublishedProduct($eligibility);
        $this->check('Scheduled webhook payload purge exists', str_contains((string) file_get_contents(base_path('routes/console.php')), 'paypal:webhooks:purge-payloads'), required: $live);
        $this->recommendedEvents();

        if ((bool) $this->option('remote')) {
            $this->remoteChecks($paypal);
        }

        return $this->failures === [] ? self::SUCCESS : self::FAILURE;
    }

    private function checkLegalVersions(bool $live): void
    {
        foreach ([
            'Terms of Sale' => config('legal.terms_of_sale_version'),
            'License Agreement' => config('legal.license_version'),
            'Refund Policy' => config('legal.refund_policy_version'),
            'digital delivery consent' => config('legal.digital_delivery_consent_version'),
        ] as $label => $version) {
            $this->check($label.' version exists', is_string($version) && $version !== '', required: $live);
            $this->check($label.' version is final in live mode', ! $live || (is_string($version) && ! Str::startsWith($version, 'draft-')), required: $live);
        }
    }

    private function checkPrivateDisk(bool $live): void
    {
        try {
            Storage::disk('private')->exists('.doctor');
            $this->pass('Private disk exists');
        } catch (\Throwable) {
            $this->check('Private disk exists', false, required: $live);
        }
    }

    private function checkPublishedProduct(ProductPurchaseEligibility $eligibility): void
    {
        $product = Product::query()->published()->with('currentVersion.files')->first();

        if (! $product instanceof Product) {
            $this->warnLine('No published product found for PackageZip eligibility check.');

            return;
        }

        $this->check('At least one published product can resolve PackageZip', $eligibility->resolvePackage($product) !== null, required: false);
    }

    private function remoteChecks(PayPalHttpClient $paypal): void
    {
        try {
            $response = $paypal->get('/v1/notifications/webhooks');
        } catch (PayPalApiException $exception) {
            $this->failCheck('Remote PayPal webhook listing failed'.($exception->debugId ? ' (debug ID stored by PayPal)' : ''));

            return;
        }

        $webhooks = collect(is_array($response['webhooks'] ?? null) ? $response['webhooks'] : []);
        $configured = $webhooks->firstWhere('id', config('paypal.webhook_id'));

        $this->check('Configured PayPal webhook ID exists remotely', is_array($configured), required: true);

        if (is_array($configured) && Route::has('webhooks.paypal')) {
            $this->check('Remote webhook URL matches application route', ($configured['url'] ?? null) === route('webhooks.paypal'), required: true);
            $eventNames = collect($configured['event_types'] ?? [])->pluck('name')->filter()->values();
            $missing = collect(config('paypal.recommended_webhook_events', []))->diff($eventNames);
            $this->check('Remote webhook subscribes to recommended events', $missing->isEmpty(), required: false);
        }
    }

    private function recommendedEvents(): void
    {
        $this->line('Recommended PayPal webhook events:');

        foreach ((array) config('paypal.recommended_webhook_events', []) as $event) {
            $this->line(' - '.$event);
        }
    }

    private function check(string $label, bool $passed, bool $required): void
    {
        if ($passed) {
            $this->pass($label);

            return;
        }

        if ($required) {
            $this->failCheck($label);

            return;
        }

        $this->warnLine($label);
    }

    private function pass(string $label): void
    {
        $this->line('<fg=green>PASS</> '.$label);
    }

    private function warnLine(string $label): void
    {
        $this->warnings[] = $label;
        $this->line('<fg=yellow>WARN</> '.$label);
    }

    private function failCheck(string $label): void
    {
        $this->failures[] = $label;
        $this->line('<fg=red>FAIL</> '.$label);
    }

    private function bool(mixed $value): string
    {
        return (bool) $value ? 'true' : 'false';
    }

    private function validCountry(mixed $country): bool
    {
        return is_string($country) && preg_match('/^[A-Z]{2}$/', $country) === 1;
    }
}
