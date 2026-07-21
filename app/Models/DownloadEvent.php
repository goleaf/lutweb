<?php

namespace App\Models;

use App\Enums\DigitalAssetKind;
use App\Enums\DownloadStatus;
use Database\Factories\DownloadEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property DigitalAssetKind $digital_asset_kind
 * @property string $entitlement_id
 * @property int|null $user_id
 * @property string $order_id
 * @property int|null $product_id
 * @property int|null $product_version_id
 * @property int|null $product_file_id
 * @property string|null $item_display_name_snapshot
 * @property string|null $item_version_snapshot
 * @property DownloadStatus $status
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $failed_at
 * @property int|null $size_bytes
 */
#[Fillable([
    'id',
    'digital_asset_kind',
    'entitlement_id',
    'user_id',
    'order_id',
    'product_id',
    'product_version_id',
    'product_file_id',
    'wizard_project_id',
    'custom_lut_build_id',
    'custom_lut_build_file_id',
    'item_display_name_snapshot',
    'item_version_snapshot',
    'status',
    'ip_address',
    'user_agent',
    'started_at',
    'completed_at',
    'failed_at',
    'size_bytes',
])]
#[Hidden([
    'ip_address',
    'user_agent',
])]
class DownloadEvent extends Model
{
    /** @use HasFactory<DownloadEventFactory> */
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
     * @return BelongsTo<Entitlement, $this>
     */
    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(Entitlement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'digital_asset_kind' => DigitalAssetKind::class,
            'status' => DownloadStatus::class,
            'ip_address' => 'encrypted',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }
}
