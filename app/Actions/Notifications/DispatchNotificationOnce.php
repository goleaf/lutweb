<?php

namespace App\Actions\Notifications;

use App\Models\NotificationDispatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class DispatchNotificationOnce
{
    public function handle(
        string $eventKey,
        User $user,
        Notification $notification,
        ?Model $related = null,
        string $channel = 'mail',
    ): NotificationDispatch {
        return DB::transaction(function () use ($eventKey, $user, $notification, $related, $channel): NotificationDispatch {
            $dispatch = NotificationDispatch::query()->firstOrCreate(
                ['event_key' => $eventKey],
                [
                    'user_id' => $user->id,
                    'notification_type' => $notification::class,
                    'related_type' => $related?->getMorphClass(),
                    'related_id' => $related?->getKey() !== null ? (string) $related->getKey() : null,
                    'channel' => $channel,
                    'status' => 'queued',
                    'queued_at' => now(),
                ],
            );

            if (! $dispatch->wasRecentlyCreated) {
                return $dispatch;
            }

            DB::afterCommit(function () use ($user, $notification, $dispatch): void {
                try {
                    $user->notify($notification);

                    $dispatch->forceFill([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ])->save();
                } catch (\Throwable $exception) {
                    $dispatch->forceFill([
                        'status' => 'failed',
                        'failed_at' => now(),
                        'failure_code' => $exception::class,
                    ])->save();

                    throw $exception;
                }
            });

            return $dispatch;
        });
    }
}
