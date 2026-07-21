<?php

namespace App\Models;

use App\Enums\WizardVariationMode;
use App\ValueObjects\LutTransformParameters;
use Database\Factories\WizardProjectVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $wizard_project_id
 * @property int $generation
 * @property int $position
 * @property WizardVariationMode $mode
 * @property string $seed
 * @property array<string, int> $parameters
 * @property string $parameters_hash
 * @property Carbon|null $selected_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'id',
    'wizard_project_id',
    'generation',
    'position',
    'mode',
    'seed',
    'parameters',
    'parameters_hash',
    'selected_at',
])]
#[Hidden([
    'seed',
])]
class WizardProjectVariant extends Model
{
    /** @use HasFactory<WizardProjectVariantFactory> */
    use HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @return BelongsTo<WizardProject, $this>
     */
    public function wizardProject(): BelongsTo
    {
        return $this->belongsTo(WizardProject::class);
    }

    public function parametersValue(): LutTransformParameters
    {
        return LutTransformParameters::fromArray($this->parameters);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generation' => 'integer',
            'position' => 'integer',
            'mode' => WizardVariationMode::class,
            'parameters' => 'array',
            'selected_at' => 'datetime',
        ];
    }
}
