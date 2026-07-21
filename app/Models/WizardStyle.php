<?php

namespace App\Models;

use App\Enums\LutTransformVersion;
use App\Services\LutWizard\ValidateWizardStyleConfiguration;
use Database\Factories\WizardStyleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property LutTransformVersion $transform_version
 * @property array<string, int> $base_parameters
 * @property array<string, int> $minimum_parameters
 * @property array<string, int> $maximum_parameters
 * @property array<string, int> $variation_amounts
 * @property bool $is_active
 * @property bool $is_featured
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'id',
    'name',
    'slug',
    'description',
    'transform_version',
    'base_parameters',
    'minimum_parameters',
    'maximum_parameters',
    'variation_amounts',
    'is_active',
    'is_featured',
    'sort_order',
])]
class WizardStyle extends Model
{
    /** @use HasFactory<WizardStyleFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'transform_version' => 'lut_transform_v1',
        'is_active' => false,
        'is_featured' => false,
        'sort_order' => 0,
    ];

    protected static function booted(): void
    {
        static::saving(function (WizardStyle $style): void {
            app(ValidateWizardStyleConfiguration::class)->validate(
                (array) $style->base_parameters,
                (array) $style->minimum_parameters,
                (array) $style->maximum_parameters,
                (array) $style->variation_amounts,
            );
        });

        static::deleting(function (WizardStyle $style): void {
            WizardProject::query()
                ->where('wizard_style_id', $style->id)
                ->update(['wizard_style_id' => null]);
        });

        static::deleting(function (WizardStyle $style): void {
            $style->wizardProjects()
                ->update(['wizard_style_id' => null]);
        });
    }

    public function isSelectable(): bool
    {
        return $this->is_active
            && ! $this->trashed()
            && $this->supportsTransformVersion(LutTransformVersion::V1);
    }

    public function supportsTransformVersion(LutTransformVersion $version): bool
    {
        return $this->getRawOriginal('transform_version') === $version->value;
    }

    /**
     * @return HasMany<WizardProject, $this>
     */
    public function wizardProjects(): HasMany
    {
        return $this->hasMany(WizardProject::class);
    }

    /**
     * @return array{name: string, slug: string, transform_version: string, base_parameters: array<string, int>, minimum_parameters: array<string, int>, maximum_parameters: array<string, int>, variation_amounts: array<string, int>}
     */
    public function snapshot(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'transform_version' => $this->transform_version->value,
            'base_parameters' => $this->base_parameters,
            'minimum_parameters' => $this->minimum_parameters,
            'maximum_parameters' => $this->maximum_parameters,
            'variation_amounts' => $this->variation_amounts,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transform_version' => LutTransformVersion::class,
            'base_parameters' => 'array',
            'minimum_parameters' => 'array',
            'maximum_parameters' => 'array',
            'variation_amounts' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }
}
