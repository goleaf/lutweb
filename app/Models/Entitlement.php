<?php

namespace App\Models;

use App\Enums\EntitlementStatus;
use Database\Factories\EntitlementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $order_id
 * @property string $order_item_id
 * @property int|null $product_id
 * @property int|null $product_version_id
 * @property int|null $product_file_id
 * @property EntitlementStatus $status
 * @property Carbon $granted_at
 * @property Carbon|null $revoked_at
 * @property string|null $revoke_reason
 * @property Carbon|null $restored_at
 */
#[Fillable([
    'id',
    'user_id',
    'order_id',
    'order_item_id',
    'product_id',
    'product_version_id',
    'product_file_id',
    'status',
    'granted_at',
    'revoked_at',
    'revoke_reason',
    'restored_at',
])]
class Entitlement extends Model
{
    /** @use HasFactory<EntitlementFactory> */
    use HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVersion, $this>
     */
    public function productVersion(): BelongsTo
    {
        return $this->belongsTo(ProductVersion::class);
    }

    /**
     * @return BelongsTo<ProductFile, $this>
     */
    public function productFile(): BelongsTo
    {
        return $this->belongsTo(ProductFile::class);
    }

    /**
     * @return HasMany<DownloadEvent, $this>
     */
    public function downloadEvents(): HasMany
    {
        return $this->hasMany(DownloadEvent::class);
    }

    public function isActive(): bool
    {
        return $this->status === EntitlementStatus::Active;
    }

    public function mayBeDownloadedBy(User $user): bool
    {
        return $this->user_id === $user->id && $this->isActive() && ! $user->is_suspended;
    }

    /**
     * @param  Builder<Entitlement>  $query
     * @return Builder<Entitlement>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', EntitlementStatus::Active);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EntitlementStatus::class,
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'restored_at' => 'datetime',
        ];
    }
}
