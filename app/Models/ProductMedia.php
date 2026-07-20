<?php

namespace App\Models;

use App\Enums\ProductMediaKind;
use Database\Factories\ProductMediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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
    ];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted(): void
    {
        static::saving(function (ProductMedia $media): void {
            $media->disk = 'public';
            $media->populateImageDimensions();

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
        ];
    }
}
