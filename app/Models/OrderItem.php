<?php

namespace App\Models;

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
 * @property int|null $product_id
 * @property int|null $product_version_id
 * @property int|null $product_file_id
 * @property string $product_name
 * @property string $product_slug
 * @property string $product_type
 * @property string|null $product_sku
 * @property string $product_version
 * @property int $unit_price_cents
 * @property int $quantity
 * @property int $total_cents
 */
#[Fillable([
    'id',
    'order_id',
    'product_id',
    'product_version_id',
    'product_file_id',
    'product_name',
    'product_slug',
    'product_type',
    'product_sku',
    'product_version',
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
     * @return HasOne<Entitlement, $this>
     */
    public function entitlement(): HasOne
    {
        return $this->hasOne(Entitlement::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price_cents' => 'integer',
            'quantity' => 'integer',
            'total_cents' => 'integer',
        ];
    }
}
