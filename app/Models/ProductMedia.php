<?php

namespace App\Models;

use App\Enums\ProductMediaKind;
use App\Enums\StorefrontImageStatus;
use App\Enums\StorefrontImageVariantRole;
use App\Enums\StorefrontMediaPipelineVersion;
use Carbon\CarbonInterface;
use Database\Factories\ProductMediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $product_id
 * @property ProductMediaKind $kind
 * @property string $disk
 * @property string $path
 * @property string|null $original_name
 * @property string $alt_text
 * @property int|null $width
 * @property int|null $height
 * @property int $sort_order
 * @property string|null $source_disk
 * @property string|null $source_path
 * @property string|null $source_original_name
 * @property string|null $source_mime_type
 * @property int|null $source_size_bytes
 * @property int|null $source_width
 * @property int|null $source_height
 * @property string|null $source_sha256
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
    'kind',
    'disk',
    'path',
    'original_name',
    'alt_text',
    'width',
    'height',
    'sort_order',
    'source_disk',
    'source_path',
    'source_original_name',
    'source_mime_type',
    'source_size_bytes',
    'source_width',
    'source_height',
    'source_sha256',
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
class ProductMedia extends Model
{
    /** @use HasFactory<ProductMediaFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'disk' => 'public',
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
     * @return MorphMany<StorefrontImageVariant, $this>
     */
    public function variants(): MorphMany
    {
        return $this->morphMany(StorefrontImageVariant::class, 'imageable')
            ->orderBy('width')
            ->orderBy('format');
    }

    /**
     * @return Collection<int, StorefrontImageVariant>
     */
    public function responsiveVariants(): Collection
    {
        return $this->variants
            ->filter(fn (StorefrontImageVariant $variant): bool => $variant->role === StorefrontImageVariantRole::Media)
            ->values();
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
        static::saving(function (ProductMedia $media): void {
            $media->disk = 'public';
            $media->populateImageDimensions();

            if ($media->isDirty('source_path') && $media->source_path !== null) {
                $media->processing_status = StorefrontImageStatus::Pending;
                $media->stale_at = $media->exists ? now() : null;
                $media->processed_at = null;
                $media->processing_fingerprint = null;
            }

            if ($media->kind !== ProductMediaKind::Cover || ! $media->product_id) {
                return;
            }

            $existingCover = self::query()
                ->where('product_id', $media->product_id)
                ->where('kind', ProductMediaKind::Cover)
                ->when($media->exists, fn ($query) => $query->whereKeyNot($media->getKey()))
                ->exists();

            if ($existingCover) {
                throw ValidationException::withMessages([
                    'kind' => 'A product may only have one cover image.',
                ]);
            }
        });
    }

    public function populateImageDimensions(): void
    {
        if ($this->path === '' || $this->width || $this->height) {
            return;
        }

        $disk = Storage::disk($this->disk);

        if (! $disk->exists($this->path)) {
            return;
        }

        $dimensions = @getimagesize($disk->path($this->path));

        if (! $dimensions) {
            return;
        }

        $this->width = $dimensions[0];
        $this->height = $dimensions[1];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ProductMediaKind::class,
            'width' => 'integer',
            'height' => 'integer',
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
