<?php

namespace App\Console\Commands;

use App\Notifications\LutReadyForDownload;
use App\Notifications\OrderPaymentConfirmed;
use App\Notifications\PaymentNeedsAttention;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

#[Signature('mail:doctor {--send-test=} {--show-config}')]
#[Description('Check transactional mail configuration without printing secrets.')]
class MailDoctor extends Command
{
    private bool $failed = false;

    public function handle(): int
    {
        $mailer = (string) config('mail.default');
        $fromAddress = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name');
        $supportEmail = (string) (config('seo.support_email') ?: config('mail.from.address'));

        $this->check('Mailer configured', $mailer !== '');
        $this->check('Production does not use log or array mailer', ! app()->isProduction() || ! in_array($mailer, ['log', 'array'], true), required: app()->isProduction());
        $this->check('Sender address configured', $fromAddress !== '');
        $this->check('Sender name configured', $fromName !== '');
        $this->check('Support address configured', $supportEmail !== '');
        $this->check('APP_URL configured', config('app.url') !== null && config('app.url') !== '');
        $this->check('HTTPS APP_URL in production', ! app()->isProduction() || str_starts_with((string) config('app.url'), 'https://'), required: app()->isProduction());
        $this->check('Queue connection configured', config('queue.default') !== null);
        $this->check('Production does not use sync queue', ! app()->isProduction() || config('queue.default') !== 'sync', required: app()->isProduction());
        $this->check('Account order route exists', Route::has('account.orders.show'));
        $this->check('Account LUT library route exists', Route::has('account.luts.index'));
        $this->check('Notification idempotency storage exists', Schema::hasTable('notification_dispatches'));
        $this->check('No direct package attachment behavior exists', ! $this->notificationsContain('attach('));
        $this->check('No permanent download URL is embedded', ! $this->notificationsContain('temporarySignedRoute') && ! $this->notificationsContain('luts.download'));
        $this->check('Transactional notification templates exist', class_exists(OrderPaymentConfirmed::class) && class_exists(LutReadyForDownload::class) && class_exists(PaymentNeedsAttention::class));

        if ((bool) $this->option('show-config')) {
            $this->line('mailer='.$mailer);
            $this->line('from_address_configured='.($fromAddress !== '' ? 'yes' : 'no'));
            $this->line('from_name_configured='.($fromName !== '' ? 'yes' : 'no'));
            $this->line('support_address_configured='.($supportEmail !== '' ? 'yes' : 'no'));
        }

        if ($this->option('send-test')) {
            $this->warn('Test mail sending is intentionally not implemented in this doctor command yet.');
            $this->failed = app()->isProduction() || $this->failed;
        }

        return $this->failed ? self::FAILURE : self::SUCCESS;
    }

    private function check(string $label, bool $passes, bool $required = true): void
    {
        $status = $passes ? 'PASS' : ($required ? 'FAIL' : 'WARN');
        $this->line($status.' '.$label);

        if (! $passes && $required) {
            $this->failed = true;
        }
    }

    private function notificationsContain(string $needle): bool
    {
        return collect(File::glob(app_path('Notifications/*.php')) ?: [])
            ->contains(fn (string $path): bool => str_contains((string) file_get_contents($path), $needle));
    }
}
