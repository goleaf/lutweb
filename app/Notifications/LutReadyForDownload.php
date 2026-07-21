<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LutReadyForDownload extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
    ) {
        $this->afterCommit();
    }

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

        return (new MailMessage)
            ->subject('Your LUT is ready')
            ->greeting('Your LUT is ready')
            ->line(($this->order->item->product_name ?? 'Your LUT').' is ready for secure download.')
            ->line('Order: '.$this->order->number)
            ->action('Go to My LUTs', route('account.luts.index'))
            ->line('No ZIP file is attached to this email. Download access stays inside your account.');
    }
}
