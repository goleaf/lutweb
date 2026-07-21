<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Catalog\EurMoney;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPaymentConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $afterCommit = true;

    public function __construct(
        public readonly Order $order,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->order->loadMissing('item');
        $item = $this->order->item;
        $amount = $this->order->currency.' '.EurMoney::formatCents($this->order->total_cents);

        return (new MailMessage)
            ->subject('Payment confirmed for '.$this->order->number)
            ->greeting('Payment confirmed')
            ->line('Order '.$this->order->number.' for '.($item?->product_name ?? 'your LUT').' has been paid.')
            ->line('Amount: '.$amount)
            ->action('View order', route('account.orders.show', $this->order))
            ->line('The downloadable ZIP is available only after signing in to your LUT Web account.');
    }
}
