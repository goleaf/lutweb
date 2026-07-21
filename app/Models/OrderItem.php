<?php

namespace App\Models;

use App\Enums\DigitalAssetKind;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $order_id
 * @property DigitalAssetKind $digital_asset_kind
 * @property int|null $product_id
 * @property int|null $product_version_id
 * @property int|null $product_file_id
 * @property string|null $wizard_project_id
 * @property string|null $custom_lut_build_id
 * @property string|null $custom_lut_build_file_id
 * @property string $product_name
 * @property string $product_slug
 * @property string|null $product_type
 * @property string|null $product_sku
 * @property string $product_version
 * @property string|null $custom_lut_build_fingerprint
 * @property string|null $custom_lut_parameters_hash
 * @property string|null $custom_lut_transform_version
 * @property string|null $custom_lut_generator_version
 * @property string|null $custom_lut_package_schema_version
 * @property string|null $custom_lut_package_sha256
 * @property int|null $custom_lut_package_size_bytes
 * @property string|null $custom_lut_style_name_snapshot
 * @property int|null $custom_lut_pricing_version
 * @property int $unit_price_cents
 * @property int $quantity
 * @property int $total_cents
 */
#[Fillable([
    'id',
    'order_id',
    'digital_asset_kind',
    'product_id',
    'product_version_id',
    'product_file_id',
    'wizard_project_id',
    'custom_lut_build_id',
    'custom_lut_build_file_id',
    'product_name',
    'product_slug',
    'product_type',
    'product_sku',
    'product_version',
    'custom_lut_build_fingerprint',
    'custom_lut_parameters_hash',
    'custom_lut_transform_version',
    'custom_lut_generator_version',
    'custom_lut_package_schema_version',
    'custom_lut_package_sha256',
    'custom_lut_package_size_bytes',
    'custom_lut_style_name_snapshot',
    'custom_lut_pricing_version',
    'unit_price_cents',
    'quantity',
    'total_cents',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
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
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
     * @return HasOne<Entitlement, $this>
     */
    public function entitlement(): HasOne
    {
        return $this->hasOne(Entitlement::class);
    }

    public function isCatalogProduct(): bool
    {
        return $this->digital_asset_kind === DigitalAssetKind::CatalogProduct;
    }

    public function isCustomLutBuild(): bool
    {
        return $this->digital_asset_kind === DigitalAssetKind::CustomLutBuild;
    }

    public function displayName(): string
    {
        return $this->product_name;
    }

    public function displaySlug(): string
    {
        return $this->product_slug;
    }

    public function versionLabel(): string
    {
        return $this->product_version;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'digital_asset_kind' => DigitalAssetKind::class,
            'unit_price_cents' => 'integer',
            'quantity' => 'integer',
            'total_cents' => 'integer',
            'custom_lut_package_size_bytes' => 'integer',
            'custom_lut_pricing_version' => 'integer',
        ];
    }
}
