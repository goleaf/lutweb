<?php

namespace App\Models;

use Database\Factories\NotificationDispatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_key
 * @property int|null $user_id
 * @property string $notification_type
 * @property string|null $related_type
 * @property string|null $related_id
 * @property string $channel
 * @property string $status
 * @property Carbon|null $queued_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $failed_at
 */
#[Fillable([
    'event_key',
    'user_id',
    'notification_type',
    'related_type',
    'related_id',
    'channel',
    'status',
    'queued_at',
    'sent_at',
    'failed_at',
    'failure_code',
])]
class NotificationDispatch extends Model
{
    /** @use HasFactory<NotificationDispatchFactory> */
    use HasFactory, HasUlids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
