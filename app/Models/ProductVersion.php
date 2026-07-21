<?php

namespace App\Models;

use App\Actions\StorefrontMedia\MarkProductExamplesStale;
use App\Enums\ProductVersionStatus;
use Database\Factories\ProductVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

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

    protected static function booted(): void
    {
        static::deleting(function (ProductVersion $version): void {
            if ($version->orderItems()->exists() || $version->entitlements()->exists()) {
                throw ValidationException::withMessages([
                    'version' => 'A purchased product version cannot be deleted. Create a new version for updates.',
                ]);
            }
        });

        static::saved(function (ProductVersion $version): void {
            if ($version->wasChanged(['status', 'is_current']) && $version->product instanceof Product) {
                app(MarkProductExamplesStale::class)->forProduct($version->product);
            }
        });
    }

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
     * @return HasMany<LutTestUpload, $this>
     */
    public function lutTestUploads(): HasMany
    {
        return $this->hasMany(LutTestUpload::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<Entitlement, $this>
     */
    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }

    /**
     * @return HasMany<DownloadEvent, $this>
     */
    public function downloadEvents(): HasMany
    {
        return $this->hasMany(DownloadEvent::class);
    }

    /**
     * @return HasMany<ProductExample, $this>
     */
    public function processedExamples(): HasMany
    {
        return $this->hasMany(ProductExample::class, 'processed_product_version_id');
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
