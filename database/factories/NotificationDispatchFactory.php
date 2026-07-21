<?php

namespace Database\Factories;

use App\Models\NotificationDispatch;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationDispatch>
 */
class NotificationDispatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_key' => 'demo:notification:'.Str::ulid(),
            'user_id' => User::factory(),
            'notification_type' => 'App\\Notifications\\OrderPaymentConfirmed',
            'related_type' => Order::class,
            'related_id' => Order::factory(),
            'channel' => 'mail',
            'status' => 'sent',
            'queued_at' => now()->subMinute(),
            'sent_at' => now(),
            'failed_at' => null,
            'failure_code' => null,
        ];
    }
}
