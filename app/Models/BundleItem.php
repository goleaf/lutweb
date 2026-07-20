<?php

namespace App\Models;

use App\Enums\ProductType;
use Database\Factories\BundleItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $bundle_id
 * @property int $product_id
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['bundle_id', 'product_id', 'sort_order'])]
class BundleItem extends Model
{
    /** @use HasFactory<BundleItemFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
    ];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'bundle_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    protected static function booted(): void
    {
        static::saving(function (BundleItem $item): void {
            $bundle = $item->bundle ?: Product::query()->find($item->bundle_id);
            $product = $item->product ?: Product::query()->find($item->product_id);

            if (! $bundle || ! $product) {
                return;
            }

            if ($bundle->is($product)) {
                throw ValidationException::withMessages([
                    'product_id' => 'A bundle cannot contain itself.',
                ]);
            }

            if ($bundle->type !== ProductType::Bundle) {
                throw ValidationException::withMessages([
                    'bundle_id' => 'Only bundle products may own bundle items.',
                ]);
            }

            if ($product->type === ProductType::Bundle) {
                throw ValidationException::withMessages([
                    'product_id' => 'Nested bundles are not allowed.',
                ]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
