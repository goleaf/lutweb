<?php

namespace App\Models;

use App\Enums\CustomLutBuildFileKind;
use App\Enums\CustomLutBuildStatus;
use Database\Factories\CustomLutBuildFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * @property string $license_version
 * @property string|null $license_template_hash
 * @property string|null $guide_version
 * @property string|null $guide_template_hash
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
    'parameters_hash',
    'build_fingerprint',
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
    'license_version',
    'license_template_hash',
    'guide_version',
    'guide_template_hash',
    'prepared_at',
    'expires_at',
    'locked_at',
    'first_ordered_at',
    'purchased_at',
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
            'status' => CustomLutBuildStatus::class,
            'sale_ready' => 'boolean',
            'contains_draft_documents' => 'boolean',
            'is_current' => 'boolean',
            'zip_validation_completed' => 'boolean',
            'parity_validation_passed' => 'boolean',
            'ffmpeg_validation_passed' => 'boolean',
            'prepared_at' => 'datetime',
            'expires_at' => 'datetime',
            'locked_at' => 'datetime',
            'first_ordered_at' => 'datetime',
            'purchased_at' => 'datetime',
        ];
    }
}
