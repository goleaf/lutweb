<?php

namespace App\Models;

use App\Enums\LutTestStatus;
use Database\Factories\LutTestUploadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property int $product_id
 * @property int|null $product_version_id
 * @property int|null $product_file_id
 * @property LutTestStatus $status
 * @property string $disk
 * @property string|null $raw_path
 * @property string|null $normalized_path
 * @property string|null $before_preview_path
 * @property string|null $after_preview_path
 * @property string $original_name
 * @property string $original_mime_type
 * @property int $original_size_bytes
 * @property int $original_width
 * @property int $original_height
 * @property string|null $preview_mime_type
 * @property int|null $preview_width
 * @property int|null $preview_height
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property Carbon $expires_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'id',
    'user_id',
    'product_id',
    'product_version_id',
    'product_file_id',
    'status',
    'disk',
    'raw_path',
    'normalized_path',
    'before_preview_path',
    'after_preview_path',
    'original_name',
    'original_mime_type',
    'original_size_bytes',
    'original_width',
    'original_height',
    'preview_mime_type',
    'preview_width',
    'preview_height',
    'failure_code',
    'failure_message',
    'expires_at',
    'completed_at',
])]
#[Hidden([
    'disk',
    'raw_path',
    'normalized_path',
    'before_preview_path',
    'after_preview_path',
    'product_version_id',
    'product_file_id',
])]
class LutTestUpload extends Model
{
    /** @use HasFactory<LutTestUploadFactory> */
    use HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => LutTestStatus::Queued->value,
        'disk' => 'private',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function isQueued(): bool
    {
        return $this->status === LutTestStatus::Queued;
    }

    public function isProcessing(): bool
    {
        return $this->status === LutTestStatus::Processing;
    }

    public function isReady(): bool
    {
        return $this->status === LutTestStatus::Ready && ! $this->isExpired();
    }

    public function isFailed(): bool
    {
        return $this->status === LutTestStatus::Failed;
    }

    public function isExpired(): bool
    {
        return $this->status === LutTestStatus::Expired || $this->expires_at->lessThanOrEqualTo(now());
    }

    public function mayBeViewedBy(User $user): bool
    {
        return $this->user_id === $user->id && ! $this->isExpired();
    }

    /**
     * @param  Builder<LutTestUpload>  $query
     * @return Builder<LutTestUpload>
     */
    public function scopeNonExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LutTestStatus::class,
            'original_size_bytes' => 'integer',
            'original_width' => 'integer',
            'original_height' => 'integer',
            'preview_width' => 'integer',
            'preview_height' => 'integer',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
