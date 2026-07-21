<?php

namespace App\Models;

use App\Enums\LutTransformVersion;
use App\Enums\WizardPhotoStatus;
use App\Enums\WizardProjectStatus;
use App\ValueObjects\LutTransformParameters;
use Database\Factories\WizardProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $wizard_style_id
 * @property string $name
 * @property WizardProjectStatus $status
 * @property LutTransformVersion $transform_version
 * @property string|null $style_name_snapshot
 * @property array<string, mixed>|null $style_snapshot
 * @property array<string, int> $parameters
 * @property string $parameters_hash
 * @property string $project_seed
 * @property int $revision
 * @property int $variation_generation
 * @property string|null $last_mutation_id
 * @property Carbon|null $last_autosaved_at
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'id',
    'user_id',
    'wizard_style_id',
    'name',
    'status',
    'transform_version',
    'style_name_snapshot',
    'style_snapshot',
    'parameters',
    'parameters_hash',
    'project_seed',
    'revision',
    'variation_generation',
    'last_mutation_id',
    'last_autosaved_at',
    'expires_at',
])]
#[Hidden([
    'project_seed',
    'last_mutation_id',
])]
class WizardProject extends Model
{
    /** @use HasFactory<WizardProjectFactory> */
    use HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'name' => 'Untitled LUT',
        'status' => 'draft',
        'transform_version' => 'lut_transform_v1',
        'revision' => 1,
        'variation_generation' => 0,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<WizardStyle, $this>
     */
    public function wizardStyle(): BelongsTo
    {
        return $this->belongsTo(WizardStyle::class);
    }

    /**
     * @return HasMany<WizardProjectPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(WizardProjectPhoto::class);
    }

    /**
     * @return HasMany<WizardProjectVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(WizardProjectVariant::class);
    }

    /**
     * @return HasMany<CustomLutBuild, $this>
     */
    public function customLutBuilds(): HasMany
    {
        return $this->hasMany(CustomLutBuild::class);
    }

    /**
     * @return HasMany<CustomLutBuild, $this>
     */
    public function purchasedBuilds(): HasMany
    {
        return $this->customLutBuilds()->whereNotNull('purchased_at');
    }

    public function belongsToUser(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function isExpired(): bool
    {
        return $this->status === WizardProjectStatus::Expired || $this->expires_at->lessThanOrEqualTo(now());
    }

    public function mayBeEditedBy(User $user): bool
    {
        return $this->belongsToUser($user) && ! $user->is_suspended && ! $this->isExpired();
    }

    public function hasAvailablePhotoSlot(): bool
    {
        $maximum = min(3, (int) config('lut-wizard.maximum_photos_per_project', 3));

        return $this->photos()
            ->where('expires_at', '>', now())
            ->whereNot('status', WizardPhotoStatus::Expired->value)
            ->count() < $maximum;
    }

    public function currentParameters(): LutTransformParameters
    {
        return LutTransformParameters::fromArray($this->parameters);
    }

    public function setParameters(LutTransformParameters $parameters): void
    {
        $this->parameters = $parameters->toArray();
        $this->parameters_hash = $parameters->hash();
    }

    public function extendExpiration(): void
    {
        $this->expires_at = now()->addDays((int) config('lut-wizard.project_expiration_days', 30));
    }

    public function snapshotStyle(WizardStyle $style): void
    {
        $baseParameters = LutTransformParameters::fromArray($style->base_parameters);

        $this->wizard_style_id = $style->id;
        $this->style_name_snapshot = $style->name;
        $this->transform_version = $style->transform_version;
        $this->style_snapshot = [
            'name' => $style->name,
            'transform_version' => $style->transform_version->value,
            'base_parameters' => $baseParameters->toArray(),
            'minimum_parameters' => LutTransformParameters::fromArray($style->minimum_parameters)->toArray(),
            'maximum_parameters' => LutTransformParameters::fromArray($style->maximum_parameters)->toArray(),
            'variation_amounts' => $style->variation_amounts,
        ];
        $this->setParameters($baseParameters);
    }

    public function clearStyleSnapshot(): void
    {
        $this->wizard_style_id = null;
        $this->style_name_snapshot = null;
        $this->style_snapshot = null;
        $this->transform_version = LutTransformVersion::V1;
        $this->setParameters(LutTransformParameters::neutral());
    }

    /**
     * @param  Builder<WizardProject>  $query
     * @return Builder<WizardProject>
     */
    public function scopeNonExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now())
            ->where('status', WizardProjectStatus::Draft->value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WizardProjectStatus::class,
            'transform_version' => LutTransformVersion::class,
            'style_snapshot' => 'array',
            'parameters' => 'array',
            'revision' => 'integer',
            'variation_generation' => 'integer',
            'last_autosaved_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
