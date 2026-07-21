<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Enums\StorefrontImageStatus;
use App\Enums\StorefrontImageVariantRole;
use App\Enums\StorefrontMediaPipelineVersion;
use Carbon\CarbonInterface;
use Database\Factories\ProductExampleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $product_id
 * @property string|null $title
 * @property string $before_disk
 * @property string $before_path
 * @property string|null $before_original_name
 * @property string $before_alt_text
 * @property string $after_disk
 * @property string $after_path
 * @property string|null $after_original_name
 * @property string $after_alt_text
 * @property bool $is_active
 * @property int $sort_order
 * @property string|null $source_disk
 * @property string|null $source_path
 * @property string|null $source_original_name
 * @property string|null $source_mime_type
 * @property int|null $source_size_bytes
 * @property int|null $source_width
 * @property int|null $source_height
 * @property string|null $source_sha256
 * @property int|null $preview_product_id
 * @property int|null $processed_product_version_id
 * @property int|null $processed_product_file_id
 * @property StorefrontImageStatus $processing_status
 * @property StorefrontMediaPipelineVersion|null $pipeline_version
 * @property string|null $processing_fingerprint
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property CarbonInterface|null $processed_at
 * @property CarbonInterface|null $stale_at
 * @property CarbonInterface|null $rights_confirmed_at
 * @property int|null $rights_confirmed_by
 * @property string|null $rights_note
 * @property string|null $source_credit
 * @property string|null $source_license_reference
 * @property bool $source_credit_is_public
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'product_id',
    'title',
    'before_disk',
    'before_path',
    'before_original_name',
    'before_alt_text',
    'after_disk',
    'after_path',
    'after_original_name',
    'after_alt_text',
    'is_active',
    'sort_order',
    'source_disk',
    'source_path',
    'source_original_name',
    'source_mime_type',
    'source_size_bytes',
    'source_width',
    'source_height',
    'source_sha256',
    'preview_product_id',
    'processed_product_version_id',
    'processed_product_file_id',
    'processing_status',
    'pipeline_version',
    'processing_fingerprint',
    'failure_code',
    'failure_message',
    'processed_at',
    'stale_at',
    'rights_confirmed_at',
    'rights_confirmed_by',
    'rights_note',
    'source_credit',
    'source_license_reference',
    'source_credit_is_public',
])]
#[Hidden([
    'source_disk',
    'source_path',
    'source_original_name',
    'source_mime_type',
    'source_size_bytes',
    'source_sha256',
    'processing_fingerprint',
    'failure_code',
    'rights_note',
    'source_license_reference',
])]
class ProductExample extends Model
{
    /** @use HasFactory<ProductExampleFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'before_disk' => 'public',
        'after_disk' => 'public',
        'is_active' => true,
        'sort_order' => 0,
        'processing_status' => StorefrontImageStatus::Ready->value,
        'pipeline_version' => StorefrontMediaPipelineVersion::V1->value,
        'source_credit_is_public' => false,
    ];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function previewProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'preview_product_id');
    }

    /**
     * @return BelongsTo<ProductVersion, $this>
     */
    public function processedProductVersion(): BelongsTo
    {
        return $this->belongsTo(ProductVersion::class, 'processed_product_version_id');
    }

    /**
     * @return BelongsTo<ProductFile, $this>
     */
    public function processedProductFile(): BelongsTo
    {
        return $this->belongsTo(ProductFile::class, 'processed_product_file_id');
    }

    /**
     * @return MorphMany<StorefrontImageVariant, $this>
     */
    public function variants(): MorphMany
    {
        return $this->morphMany(StorefrontImageVariant::class, 'imageable')
            ->orderBy('role')
            ->orderBy('width')
            ->orderBy('format');
    }

    /**
     * @return Collection<int, StorefrontImageVariant>
     */
    public function beforeVariants(): Collection
    {
        return $this->variants
            ->filter(fn (StorefrontImageVariant $variant): bool => $variant->role === StorefrontImageVariantRole::Before)
            ->values();
    }

    /**
     * @return Collection<int, StorefrontImageVariant>
     */
    public function afterVariants(): Collection
    {
        return $this->variants
            ->filter(fn (StorefrontImageVariant $variant): bool => $variant->role === StorefrontImageVariantRole::After)
            ->values();
    }

    public function resolvePreviewProduct(): Product
    {
        $product = $this->product;

        if ($product->type === ProductType::Bundle && $this->previewProduct instanceof Product) {
            return $this->previewProduct;
        }

        return $product;
    }

    public function isReady(): bool
    {
        return $this->processing_status === StorefrontImageStatus::Ready;
    }

    public function isStale(): bool
    {
        return $this->processing_status === StorefrontImageStatus::Stale;
    }

    public function hasConfirmedUsageRights(): bool
    {
        return $this->rights_confirmed_at !== null;
    }

    protected static function booted(): void
    {
        static::saving(function (ProductExample $example): void {
            $example->before_disk = 'public';
            $example->after_disk = 'public';

            if (($example->isDirty('source_path') && $example->source_path !== null) || $example->isDirty('preview_product_id')) {
                $example->processing_status = StorefrontImageStatus::Pending;
                $example->stale_at = $example->exists ? now() : null;
                $example->processed_at = null;
                $example->processing_fingerprint = null;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'source_size_bytes' => 'integer',
            'source_width' => 'integer',
            'source_height' => 'integer',
            'processing_status' => StorefrontImageStatus::class,
            'pipeline_version' => StorefrontMediaPipelineVersion::class,
            'processed_at' => 'datetime',
            'stale_at' => 'datetime',
            'rights_confirmed_at' => 'datetime',
            'source_credit_is_public' => 'boolean',
        ];
    }
}
