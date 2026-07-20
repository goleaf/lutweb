<?php

namespace App\Models;

use App\Enums\ProductMediaKind;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property ProductType $type
 * @property ProductStatus $status
 * @property string $name
 * @property string $slug
 * @property string|null $sku
 * @property string $short_description
 * @property string|null $description
 * @property int $price_cents
 * @property string $currency
 * @property bool $is_featured
 * @property Carbon|null $published_at
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'type',
    'status',
    'name',
    'slug',
    'sku',
    'short_description',
    'description',
    'price_cents',
    'currency',
    'is_featured',
    'published_at',
    'meta_title',
    'meta_description',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => ProductType::SingleLut->value,
        'status' => ProductStatus::Draft->value,
        'price_cents' => 0,
        'currency' => 'EUR',
        'is_featured' => false,
    ];

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<CompatibleSoftware, $this>
     */
    public function compatibleSoftware(): BelongsToMany
    {
        return $this->belongsToMany(CompatibleSoftware::class)->withTimestamps();
    }

    /**
     * @return HasMany<BundleItem, $this>
     */
    public function bundleItems(): HasMany
    {
        return $this->hasMany(BundleItem::class, 'bundle_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<BundleItem, $this>
     */
    public function includedInBundles(): HasMany
    {
        return $this->hasMany(BundleItem::class, 'product_id');
    }

    /**
     * @return HasMany<ProductVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ProductVersion::class)->latest('id');
    }

    /**
     * @return HasOne<ProductVersion, $this>
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(ProductVersion::class)->where('is_current', true);
    }

    /**
     * @return HasManyThrough<ProductFile, ProductVersion, $this>
     */
    public function files(): HasManyThrough
    {
        return $this->hasManyThrough(ProductFile::class, ProductVersion::class);
    }

    /**
     * @return HasMany<ProductMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort_order');
    }

    /**
     * @return HasOne<ProductMedia, $this>
     */
    public function coverMedia(): HasOne
    {
        return $this->hasOne(ProductMedia::class)
            ->where('kind', ProductMediaKind::Cover)
            ->orderBy('sort_order')
            ->oldest('id');
    }

    /**
     * @return HasMany<ProductMedia, $this>
     */
    public function galleryMedia(): HasMany
    {
        return $this->hasMany(ProductMedia::class)
            ->where('kind', ProductMediaKind::Gallery)
            ->orderBy('sort_order')
            ->oldest('id');
    }

    /**
     * @return HasMany<ProductExample, $this>
     */
    public function examples(): HasMany
    {
        return $this->hasMany(ProductExample::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ProductExample, $this>
     */
    public function activeExamples(): HasMany
    {
        return $this->hasMany(ProductExample::class)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->oldest('id');
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', ProductStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isFree(): bool
    {
        return $this->type === ProductType::FreeLut;
    }

    public function isBundle(): bool
    {
        return $this->type === ProductType::Bundle;
    }

    public function isPublished(): bool
    {
        return $this->status === ProductStatus::Published
            && $this->published_at !== null
            && $this->published_at->lessThanOrEqualTo(now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'price_cents' => 'integer',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
