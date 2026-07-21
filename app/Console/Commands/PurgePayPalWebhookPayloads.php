<?php

namespace App\Console\Commands;

use App\Models\PayPalWebhookEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('paypal:webhooks:purge-payloads {--dry-run : Count purgeable payloads without changing them} {--older-than=7 : Purge payloads older than this many days}')]
#[Description('Clear retained encrypted PayPal webhook payloads while keeping safe metadata.')]
class PurgePayPalWebhookPayloads extends Command
{
    public function handle(): int
    {
        $olderThan = max(1, (int) $this->option('older-than'));
        $cutoff = now()->subDays($olderThan);
        $dryRun = (bool) $this->option('dry-run');
        $total = 0;

        PayPalWebhookEvent::query()
            ->whereNotNull('encrypted_payload')
            ->where('created_at', '<=', $cutoff)
            ->orderBy('created_at')
            ->orderBy('id')
            ->chunkById(100, function ($events) use ($dryRun, &$total): void {
                foreach ($events as $event) {
                    $total++;

                    if ($dryRun) {
                        continue;
                    }

                    $event->forceFill([
                        'encrypted_payload' => null,
                        'payload_purged_at' => now(),
                    ])->save();
                }
            });

        $this->line(($dryRun ? 'DRY RUN: ' : '').$total.' PayPal webhook payload(s) matched.');

        return self::SUCCESS;
    }
}
