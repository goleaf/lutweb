<?php

namespace App\Models;

use Database\Factories\AuditEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int|null $actor_user_id
 * @property string $action
 * @property string|null $auditable_type
 * @property string|null $auditable_id
 * @property int|null $target_user_id
 * @property string|null $request_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<string, mixed>|null $metadata
 * @property Carbon $occurred_at
 */
#[Fillable([
    'actor_user_id',
    'action',
    'auditable_type',
    'auditable_id',
    'target_user_id',
    'request_id',
    'ip_address',
    'user_agent',
    'metadata',
    'occurred_at',
])]
class AuditEvent extends Model
{
    /** @use HasFactory<AuditEventFactory> */
    use HasFactory, HasUlids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ip_address' => 'encrypted',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
