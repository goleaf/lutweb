<?php

namespace App\Models;

use App\Enums\CustomLutBuildFileKind;
use App\Enums\CustomLutBuildStatus;
use Database\Factories\CustomLutBuildFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int|null $user_id
 * @property string|null $wizard_project_id
 * @property string $project_name_snapshot
 * @property string|null $style_name_snapshot
 * @property string $package_stem
 * @property int $project_revision
 * @property string $parameters_hash
 * @property string $build_fingerprint
 * @property string|null $build_request_id
 * @property array<string, int>|null $parameters
 * @property string|null $disk
 * @property string $transform_version
 * @property string $generator_version
 * @property string $package_schema_version
 * @property CustomLutBuildStatus $status
 * @property bool $sale_ready
 * @property bool $contains_draft_documents
 * @property bool $is_current
 * @property bool $zip_validation_completed
 * @property bool $parity_validation_passed
 * @property bool $ffmpeg_validation_passed
 * @property int|null $parity_mean_error_millionths
 * @property int|null $parity_p95_error_millionths
 * @property int|null $parity_p99_error_millionths
 * @property int|null $parity_max_error_millionths
 * @property string $license_version
 * @property string|null $license_template_hash
 * @property string|null $guide_version
 * @property string|null $guide_template_hash
 * @property int|null $zip_size_bytes
 * @property string|null $zip_sha256
 * @property int|null $uncompressed_size_bytes
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property array<string, mixed>|null $license_document_snapshot
 * @property array<string, mixed>|null $guide_document_snapshot
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $superseded_at
 * @property Carbon|null $prepared_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $locked_at
 * @property Carbon|null $first_ordered_at
 * @property Carbon|null $purchased_at
 */
#[Fillable([
    'id',
    'user_id',
    'wizard_project_id',
    'project_name_snapshot',
    'style_name_snapshot',
    'package_stem',
    'project_revision',
    'parameters',
    'parameters_hash',
    'build_fingerprint',
    'build_request_id',
    'disk',
    'transform_version',
    'generator_version',
    'package_schema_version',
    'status',
    'sale_ready',
    'contains_draft_documents',
    'is_current',
    'zip_validation_completed',
    'parity_validation_passed',
    'ffmpeg_validation_passed',
    'parity_mean_error_millionths',
    'parity_p95_error_millionths',
    'parity_p99_error_millionths',
    'parity_max_error_millionths',
    'license_version',
    'license_template_hash',
    'guide_version',
    'guide_template_hash',
    'zip_size_bytes',
    'zip_sha256',
    'uncompressed_size_bytes',
    'failure_code',
    'failure_message',
    'license_document_snapshot',
    'guide_document_snapshot',
    'started_at',
    'completed_at',
    'superseded_at',
    'prepared_at',
    'expires_at',
    'locked_at',
    'first_ordered_at',
    'purchased_at',
])]
#[Hidden([
    'disk',
])]
class CustomLutBuild extends Model
{
    /** @use HasFactory<CustomLutBuildFactory> */
    use HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'queued',
        'sale_ready' => false,
        'contains_draft_documents' => true,
        'disk' => 'private',
        'is_current' => false,
        'zip_validation_completed' => false,
        'parity_validation_passed' => false,
        'ffmpeg_validation_passed' => false,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<WizardProject, $this>
     */
    public function wizardProject(): BelongsTo
    {
        return $this->belongsTo(WizardProject::class);
    }

    /**
     * @return HasMany<CustomLutBuildFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(CustomLutBuildFile::class);
    }

    /**
     * @return HasOne<CustomLutBuildFile, $this>
     */
    public function packageFile(): HasOne
    {
        return $this->hasOne(CustomLutBuildFile::class)
            ->where('kind', CustomLutBuildFileKind::PackageZip->value);
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

    public function isLockedForCommerce(): bool
    {
        return $this->locked_at !== null
            || $this->orderItems()->exists()
            || $this->entitlements()->exists();
    }

    public function hasBeenPurchased(): bool
    {
        return $this->purchased_at !== null || $this->entitlements()->exists();
    }

    public function mayBeDeleted(): bool
    {
        return ! $this->isLockedForCommerce();
    }

    public function belongsToUser(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function isQueued(): bool
    {
        return $this->status === CustomLutBuildStatus::Queued;
    }

    public function isProcessing(): bool
    {
        return $this->status === CustomLutBuildStatus::Processing;
    }

    public function isReady(): bool
    {
        return $this->status === CustomLutBuildStatus::Ready;
    }

    public function isFailed(): bool
    {
        return $this->status === CustomLutBuildStatus::Failed;
    }

    public function isSuperseded(): bool
    {
        return $this->status === CustomLutBuildStatus::Superseded;
    }

    public function isExpired(): bool
    {
        return $this->status === CustomLutBuildStatus::Expired || ($this->expires_at !== null && $this->expires_at->lessThanOrEqualTo(now()));
    }

    public function isCurrentFor(WizardProject $project): bool
    {
        return $this->wizard_project_id === $project->id
            && $this->is_current
            && $this->project_revision === $project->revision
            && $this->parameters_hash === $project->parameters_hash
            && ! $this->isExpired()
            && ! $this->isSuperseded();
    }

    public function mayBePreparedForPurchase(): bool
    {
        return $this->isReady()
            && $this->sale_ready
            && ! $this->contains_draft_documents
            && $this->zip_validation_completed
            && $this->parity_validation_passed
            && $this->ffmpeg_validation_passed
            && ! $this->isExpired();
    }

    /**
     * @param  Builder<CustomLutBuild>  $query
     * @return Builder<CustomLutBuild>
     */
    public function scopeSaleReady(Builder $query): Builder
    {
        return $query
            ->where('status', CustomLutBuildStatus::Ready->value)
            ->where('sale_ready', true)
            ->where('contains_draft_documents', false);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'project_revision' => 'integer',
            'parameters' => 'array',
            'status' => CustomLutBuildStatus::class,
            'sale_ready' => 'boolean',
            'contains_draft_documents' => 'boolean',
            'is_current' => 'boolean',
            'zip_validation_completed' => 'boolean',
            'parity_validation_passed' => 'boolean',
            'ffmpeg_validation_passed' => 'boolean',
            'parity_mean_error_millionths' => 'integer',
            'parity_p95_error_millionths' => 'integer',
            'parity_p99_error_millionths' => 'integer',
            'parity_max_error_millionths' => 'integer',
            'zip_size_bytes' => 'integer',
            'uncompressed_size_bytes' => 'integer',
            'license_document_snapshot' => 'array',
            'guide_document_snapshot' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'superseded_at' => 'datetime',
            'prepared_at' => 'datetime',
            'expires_at' => 'datetime',
            'locked_at' => 'datetime',
            'first_ordered_at' => 'datetime',
            'purchased_at' => 'datetime',
        ];
    }
}
