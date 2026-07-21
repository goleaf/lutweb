<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Orders\ReconcilePayPalOrder as ReconcilePayPalOrderAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ReconcilePayPalOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $orderId,
    ) {
        $this->onQueue((string) config('paypal.payment_queue', 'payments'));
        $this->afterCommit();
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('paypal-reconcile-'.$this->orderId))->expireAfter(300),
        ];
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function handle(ReconcilePayPalOrderAction $reconcile): void
    {
        $order = Order::query()->find($this->orderId);

        if ($order instanceof Order) {
            $reconcile->handle($order);
        }
    }
}
