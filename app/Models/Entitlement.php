<?php

namespace App\Models;

use App\Enums\DigitalAssetKind;
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
 * @property int|null $user_id
 * @property DigitalAssetKind $digital_asset_kind
 * @property string $order_id
 * @property string $order_item_id
 * @property int|null $product_id
 * @property int|null $product_version_id
 * @property int|null $product_file_id
 * @property string|null $wizard_project_id
 * @property string|null $custom_lut_build_id
 * @property string|null $custom_lut_build_file_id
 * @property EntitlementStatus $status
 * @property Carbon $granted_at
 * @property Carbon|null $revoked_at
 * @property string|null $revoke_reason
 * @property Carbon|null $restored_at
 */
#[Fillable([
    'id',
    'user_id',
    'digital_asset_kind',
    'order_id',
    'order_item_id',
    'product_id',
    'product_version_id',
    'product_file_id',
    'wizard_project_id',
    'custom_lut_build_id',
    'custom_lut_build_file_id',
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
     * @var array<string, mixed>
     */
    protected $attributes = [
        'digital_asset_kind' => 'catalog_product',
    ];

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
     * @return BelongsTo<WizardProject, $this>
     */
    public function wizardProject(): BelongsTo
    {
        return $this->belongsTo(WizardProject::class);
    }

    /**
     * @return BelongsTo<CustomLutBuild, $this>
     */
    public function customLutBuild(): BelongsTo
    {
        return $this->belongsTo(CustomLutBuild::class);
    }

    /**
     * @return BelongsTo<CustomLutBuildFile, $this>
     */
    public function customLutBuildFile(): BelongsTo
    {
        return $this->belongsTo(CustomLutBuildFile::class);
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

    public function isCatalogProduct(): bool
    {
        return $this->digital_asset_kind === DigitalAssetKind::CatalogProduct;
    }

    public function isCustomLutBuild(): bool
    {
        return $this->digital_asset_kind === DigitalAssetKind::CustomLutBuild;
    }

    public function mayBeDownloadedBy(User $user): bool
    {
        return $this->user_id === $user->id
            && $this->isActive()
            && ! $user->is_suspended
            && $user->hasVerifiedEmail();
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
            'digital_asset_kind' => DigitalAssetKind::class,
            'status' => EntitlementStatus::class,
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'restored_at' => 'datetime',
        ];
    }
}
