<?php

namespace App\Jobs;

use App\Models\PayPalWebhookEvent;
use App\Services\Webhooks\ProcessPayPalWebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ProcessPayPalWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public bool $afterCommit = true;

    public function __construct(
        public readonly string $eventId,
    ) {
        $this->onQueue((string) config('paypal.webhook_queue', 'payments'));
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('paypal-webhook-'.$this->eventId))->expireAfter(300),
        ];
    }

    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function handle(ProcessPayPalWebhookEvent $processor): void
    {
        $event = PayPalWebhookEvent::query()->find($this->eventId);

        if ($event instanceof PayPalWebhookEvent) {
            $processor->handle($event);
        }
    }
}
