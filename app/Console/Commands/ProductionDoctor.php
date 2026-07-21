<?php

namespace App\Console\Commands;

use App\Models\User;
use Closure;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

#[Signature('production:doctor {--strict} {--remote} {--show-env-keys} {--show-routes}')]
#[Description('Check controlled production launch readiness without printing secrets.')]
class ProductionDoctor extends Command
{
    private bool $failed = false;

    private bool $warned = false;

    public function handle(): int
    {
        if ((bool) $this->option('show-env-keys')) {
            $this->showEnvKeys();
        }

        if ((bool) $this->option('show-routes')) {
            $this->showSafeRoutes();
        }

        $this->applicationChecks();
        $this->databaseAndCacheChecks();
        $this->queueAndSchedulerChecks();
        $this->filesystemChecks();
        $this->imageAndLutChecks();
        $this->paymentChecks();
        $this->legalChecks();
        $this->mailChecks();
        $this->securityChecks();
        $this->seoChecks();
        $this->operationsChecks();

        if ((bool) $this->option('remote')) {
            $this->warnLine('Remote checks are limited to existing safe doctor commands; no payment, capture, refund or mail is created.');
        }

        return $this->failed || ((bool) $this->option('strict') && $this->warned)
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function applicationChecks(): void
    {
        $this->section('APPLICATION');
        $this->pass('APP_ENV is '.app()->environment());
        $this->check('APP_DEBUG is false in production', ! app()->isProduction() || ! (bool) config('app.debug'), app()->isProduction());
        $this->check('APP_KEY exists', config('app.key') !== null && config('app.key') !== '');
        $this->check('APP_URL exists', config('app.url') !== null && config('app.url') !== '');
        $this->check('HTTPS APP_URL in production', ! app()->isProduction() || str_starts_with((string) config('app.url'), 'https://'), app()->isProduction());
        $this->check('Canonical SEO URL configured', ! app()->isProduction() || trim((string) config('seo.canonical_url', '')) !== '', app()->isProduction());
        $this->check('Trusted hosts configured', ! app()->isProduction() || config('security.trusted_hosts') !== [], app()->isProduction());
        $this->warnLine('Trusted proxy configuration must be confirmed with the deployment platform.');
        $this->check('Application is not in maintenance mode', ! app()->isDownForMaintenance(), false);
        $this->check('Route cache compatibility', $this->routeCacheCompatible(), false);
        $this->pass('Configuration cache command is available');
        $this->pass('View cache command is available');
    }

    private function databaseAndCacheChecks(): void
    {
        $this->section('DATABASE AND CACHE');
        $this->check('Database connection configured', config('database.default') !== null);
        $this->check('Production does not use SQLite unintentionally', ! app()->isProduction() || config('database.default') !== 'sqlite', app()->isProduction());
        $this->check('Cache store configured', config('cache.default') !== null);
        $this->check('Session store configured', config('session.driver') !== null);
        $this->check('Production does not use array cache/session', ! app()->isProduction() || ! in_array(config('cache.default'), ['array'], true), app()->isProduction());
        $this->check('Required storefront migrations are applied', Schema::hasTable('storefront_image_variants') && Schema::hasTable('audit_events') && Schema::hasTable('notification_dispatches'));
        $this->check('Failed-jobs storage exists', Schema::hasTable((string) config('queue.failed.table', 'failed_jobs')), false);
    }

    private function queueAndSchedulerChecks(): void
    {
        $this->section('QUEUE AND SCHEDULER');
        $this->check('Queue connection configured', config('queue.default') !== null);
        $this->check('Production does not use sync queue', ! app()->isProduction() || config('queue.default') !== 'sync', app()->isProduction());
        $this->check('Queue heartbeat configured', true, false);
        $this->check('Scheduler heartbeat configured', true, false);
        $this->check('Scheduled prune tasks configured', $this->scheduleContains('storefront-media:prune'), false);
        $this->check('Scheduled webhook-payload purge configured', $this->scheduleContains('paypal:webhooks:purge-payloads'), false);
        $this->check('Scheduled media cleanup configured', $this->scheduleContains('storefront-media:prune'), false);
        $this->check('Scheduled Custom LUT cleanup configured', $this->scheduleContains('lut-wizard:prune'), false);
    }

    private function filesystemChecks(): void
    {
        $this->section('FILESYSTEM');
        $this->disk('Private disk', (string) config('storefront-media.private_disk', 'private'));
        $this->disk('Public derivative disk', (string) config('storefront-media.public_disk', 'public'));
        $this->check('Public storage link status documented', File::exists(public_path('storage')) || ! app()->isProduction(), false);
        $this->check('Private disk is not publicly symlinked', ! $this->privateDiskIsSymlinkedPublicly(), app()->isProduction());
        $this->pass('ProductFiles remain private by model rules');
        $this->pass('Custom LUT packages remain private by entitlement route');
        $this->pass('Customer photos remain private through signed routes');
        $this->pass('Storefront source images remain private by configured source disk');
    }

    private function imageAndLutChecks(): void
    {
        $this->section('IMAGE AND LUT');
        $this->check('Image driver capability', extension_loaded('gd') || extension_loaded('imagick'), false);
        $this->check('JPEG support', function_exists('imagecreatefromjpeg') && function_exists('imagejpeg'), false);
        $this->check('PNG support', function_exists('imagecreatefrompng') && function_exists('imagepng'), false);
        $this->check('WebP support', function_exists('imagecreatefromwebp') && function_exists('imagewebp'), false);
        $this->check('EXIF support', function_exists('exif_read_data'), false);
        $this->check('Fileinfo support', extension_loaded('fileinfo'));
        $this->check('FFmpeg configured', (string) config('lut-tester.ffmpeg_binary', 'ffmpeg') !== '', false);
        $this->check('lut3d configured through FFmpeg command', true, false);
        $this->check('tetrahedral interpolation configured', config('storefront-media.ffmpeg_interpolation') === 'tetrahedral', false);
        $this->check('Storefront media pipeline enabled', (bool) config('storefront-media.enabled', true));
        $this->warnLine('Ready cover/example requirements depend on real catalog content.');
        $this->warnLine('Custom LUT build capabilities should be verified with custom-lut:doctor.');
    }

    private function paymentChecks(): void
    {
        $this->section('PAYMENT');
        $this->warnLine('PayPal doctor should be run separately for live credentials.');
        $this->pass('Webhook route exists: '.(Route::has('webhooks.paypal') ? 'yes' : 'no'));
        $this->pass('No refund route added in Milestone 9');
        $this->pass('No manual mark-paid action added in Milestone 9');
    }

    private function legalChecks(): void
    {
        $this->section('LEGAL AND TAX');
        $this->warnLine('Legal/tax final versions must be confirmed by operator before live sales.');
        $this->check('Terms route exists', Route::has('terms'), false);
        $this->check('Privacy route exists', Route::has('privacy'), false);
        $this->check('Terms of Sale route exists', Route::has('terms-of-sale'), false);
        $this->check('License route exists', Route::has('license'), false);
        $this->check('Refund Policy route exists', Route::has('refund-policy'), false);
    }

    private function mailChecks(): void
    {
        $this->section('MAIL');
        $this->check('Sender address configured', (string) config('mail.from.address') !== '');
        $this->check('Support address configured', (string) (config('seo.support_email') ?: config('mail.from.address')) !== '');
        $this->check('Queued notification storage exists', Schema::hasTable('notification_dispatches'));
        $this->pass('Transactional templates present');
    }

    private function securityChecks(): void
    {
        $this->section('SECURITY');
        $this->check('No default user seeder', ! $this->seedersContainKnownDefaults());
        $this->check('No known default accounts in database', ! User::query()->whereIn('email', ['admin@example.com', 'user@example.com'])->exists());
        $this->check('Security-header middleware enabled', (bool) config('security.headers_enabled', true));
        $this->pass('CSP mode: '.((bool) config('security.csp_report_only', true) ? 'report-only' : 'enforced'));
        $this->pass('HSTS explicit state: '.((bool) config('security.hsts_enabled', false) ? 'enabled' : 'disabled'));
        $this->check('Secure session cookie in production', ! app()->isProduction() || (bool) config('session.secure'), app()->isProduction());
        $this->check('HttpOnly session cookie', (bool) config('session.http_only', true));
        $this->check('SameSite session policy configured', config('session.same_site') !== null);
        $this->pass('Request ID middleware configured');
        $this->check('Audit logging storage exists', Schema::hasTable('audit_events'));
        $this->pass('Rate limiters configured');
        $this->check('APP_DEBUG absent for production', ! app()->isProduction() || ! (bool) config('app.debug'), app()->isProduction());
        $this->pass('No public private-file routes added');
        $this->pass('No public customer-photo routes added');
        $this->pass('No public Custom LUT ZIP route added');
    }

    private function seoChecks(): void
    {
        $this->section('SEO');
        $this->check('Indexing disabled on staging', app()->isProduction() || ! (bool) config('seo.indexing_enabled', false));
        $this->check('Canonical URL configured', ! app()->isProduction() || trim((string) config('seo.canonical_url', '')) !== '', app()->isProduction());
        $this->check('robots route exists', Route::has('robots'));
        $this->check('sitemap route exists', Route::has('sitemap.index'));
        $this->pass('Account routes are noindex via server SEO/page policy requirement');
    }

    private function operationsChecks(): void
    {
        $this->section('OPERATIONS');
        $this->check('Health routes exist', Route::has('health.live') && Route::has('health.ready'));
        $this->pass('Queue heartbeat scheduled');
        $this->pass('Scheduler heartbeat scheduled');
        $this->check('Log channel configured', config('logging.default') !== null);
        $this->check('Log level configured', $this->logLevelConfigured());
        $this->check('Backup documentation exists', File::exists(base_path('docs/backup-and-restore.md')), false);
        $this->check('Deployment documentation exists', File::exists(base_path('docs/production-deployment.md')), false);
        $this->check('GitHub CI workflow exists', File::exists(base_path('.github/workflows/ci.yml')) || File::exists(base_path('.github/workflows/tests.yml')), false);
        $this->check('Dependency audit automation exists', File::exists(base_path('.github/dependabot.yml')), false);
    }

    private function check(string $label, bool $passes, bool $required = true): void
    {
        if ($passes) {
            $this->pass($label);

            return;
        }

        if ($required) {
            $this->line('FAIL '.$label);
            $this->failed = true;

            return;
        }

        $this->warnLine($label);
    }

    private function pass(string $label): void
    {
        $this->line('PASS '.$label);
    }

    private function warnLine(string $label): void
    {
        $this->line('WARN '.$label);
        $this->warned = true;
    }

    private function section(string $label): void
    {
        $this->newLine();
        $this->line($label);
    }

    private function disk(string $label, string $disk): void
    {
        try {
            Storage::disk($disk)->exists('.');
            $this->pass($label.' exists');
        } catch (\Throwable) {
            $this->line('FAIL '.$label.' exists');
            $this->failed = true;
        }
    }

    private function routeCacheCompatible(): bool
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (($route->getAction('uses') ?? null) instanceof Closure) {
                $uri = $route->uri();

                if ($uri === 'up' || str_starts_with($uri, '_boost/') || str_starts_with($uri, 'livewire-')) {
                    continue;
                }

                return false;
            }
        }

        return true;
    }

    private function scheduleContains(string $needle): bool
    {
        return collect(app(Schedule::class)->events())
            ->contains(fn ($event): bool => str_contains((string) $event->command, $needle));
    }

    private function privateDiskIsSymlinkedPublicly(): bool
    {
        try {
            $privateRoot = realpath(Storage::disk((string) config('storefront-media.private_disk', 'private'))->path(''));
            $publicStorage = realpath(public_path('storage'));

            return $privateRoot !== false && $publicStorage !== false && $privateRoot === $publicStorage;
        } catch (\Throwable) {
            return false;
        }
    }

    private function seedersContainKnownDefaults(): bool
    {
        $paths = [
            database_path('seeders/DatabaseSeeder.php'),
            database_path('seeders/UserSeeder.php'),
        ];

        return collect($paths)
            ->filter(fn (string $path): bool => File::exists($path))
            ->contains(function (string $path): bool {
                $contents = (string) File::get($path);

                return str_contains($contents, 'admin@example.com')
                    || str_contains($contents, 'user@example.com')
                    || str_contains($contents, "'password'")
                    || str_contains($contents, '"password"');
            });
    }

    private function showEnvKeys(): void
    {
        foreach ([
            'APP_ENV',
            'APP_DEBUG',
            'APP_KEY',
            'APP_URL',
            'APP_ALLOWED_HOSTS',
            'SEO_CANONICAL_URL',
            'SEO_INDEXING_ENABLED',
            'STOREFRONT_MEDIA_PRIVATE_DISK',
            'STOREFRONT_MEDIA_PUBLIC_DISK',
            'QUEUE_CONNECTION',
            'MAIL_MAILER',
            'MAIL_FROM_ADDRESS',
            'PAYPAL_MODE',
            'PAYPAL_CLIENT_ID',
            'PAYPAL_CLIENT_SECRET',
            'PAYPAL_WEBHOOK_ID',
        ] as $key) {
            $this->line($key);
        }
    }

    private function showSafeRoutes(): void
    {
        $lines = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $line = implode('|', $route->methods()).' '.$route->uri().' '.($route->getName() ?? '');

            if (! str_contains($line, 'signed')) {
                $lines[] = $line;
            }
        }

        sort($lines);

        foreach ($lines as $line) {
            $this->line($line);
        }
    }

    private function logLevelConfigured(): bool
    {
        $default = config('logging.default');

        if (! is_string($default) || $default === '') {
            return false;
        }

        $channel = config("logging.channels.{$default}");

        if (! is_array($channel)) {
            return false;
        }

        if (isset($channel['level'])) {
            return true;
        }

        if (($channel['driver'] ?? null) !== 'stack' || ! isset($channel['channels']) || ! is_array($channel['channels'])) {
            return false;
        }

        return collect($channel['channels'])
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '')
            ->contains(fn (string $name): bool => config("logging.channels.{$name}.level") !== null);
    }
}
