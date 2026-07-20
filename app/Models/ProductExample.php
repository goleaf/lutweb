<?php

namespace App\Models;

use Database\Factories\ProductExampleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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
        static::saving(function (ProductExample $example): void {
            $example->before_disk = 'public';
            $example->after_disk = 'public';
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
        ];
    }
}
