<?php

namespace App\Models;

use App\Enums\ProductVersionStatus;
use Database\Factories\ProductVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property string $version
 * @property ProductVersionStatus $status
 * @property bool $is_current
 * @property Carbon|null $released_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['product_id', 'version', 'status', 'is_current', 'released_at', 'notes'])]
class ProductVersion extends Model
{
    /** @use HasFactory<ProductVersionFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ProductVersionStatus::Draft->value,
        'is_current' => false,
    ];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<ProductFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ProductFile::class)->orderBy('sort_order');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProductVersionStatus::class,
            'is_current' => 'boolean',
            'released_at' => 'datetime',
        ];
    }
}
