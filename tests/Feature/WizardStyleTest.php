<?php

use App\Enums\LutTransformVersion;
use App\Models\WizardProject;
use App\Models\WizardStyle;
use App\ValueObjects\LutTransformParameters;
use Database\Seeders\WizardStyleSeeder;
use Illuminate\Validation\ValidationException;

test('style selectability respects active soft delete and transform version', function () {
    $style = WizardStyle::factory()->active()->create();

    expect($style->isSelectable())->toBeTrue()
        ->and($style->supportsTransformVersion(LutTransformVersion::V1))->toBeTrue();

    expect(WizardStyle::factory()->inactive()->create()->isSelectable())->toBeFalse();

    $style->delete();

    expect($style->fresh()->isSelectable())->toBeFalse();
});

test('style parameter configuration must be canonical and internally consistent', function (array $overrides, string $message) {
    expect(fn () => WizardStyle::factory()->create($overrides))
        ->toThrow(ValidationException::class, $message);
})->with([
    'invalid base parameters' => [[
        'base_parameters' => [
            ...LutTransformParameters::neutral()->toArray(),
            'contrast' => 1001,
        ],
    ], 'outside the supported range'],
    'minimum greater than base' => [[
        'minimum_parameters' => [
            ...LutTransformParameters::neutral()->toArray(),
            'contrast' => 10,
        ],
    ], 'minimum cannot be greater'],
    'base greater than maximum' => [[
        'maximum_parameters' => [
            ...LutTransformParameters::neutral()->toArray(),
            'contrast' => -10,
        ],
    ], 'base cannot be greater'],
    'negative variation amount' => [[
        'variation_amounts' => [
            ...array_fill_keys(LutTransformParameters::keys(), 0),
            'contrast' => -1,
        ],
    ], 'variation amount must be non-negative'],
    'hue variation above half circle' => [[
        'variation_amounts' => [
            ...array_fill_keys(LutTransformParameters::keys(), 0),
            'shadow_hue' => 1801,
        ],
    ], 'hue variation amount cannot exceed 180 degrees'],
]);

test('style seeder is idempotent and seeded styles validate', function () {
    $this->seed(WizardStyleSeeder::class);
    $this->seed(WizardStyleSeeder::class);

    $styles = WizardStyle::query()->orderBy('sort_order')->get();

    expect($styles)->toHaveCount(6)
        ->and($styles->pluck('name')->all())->toBe([
            'Clean Portrait',
            'Warm Cinematic',
            'Dark Moody',
            'Soft Pastel',
            'Vintage Film',
            'Bright Travel',
        ])
        ->and($styles->every(fn (WizardStyle $style): bool => $style->isSelectable()))->toBeTrue();
});

test('project style snapshot survives style edits and deletion', function () {
    $style = WizardStyle::factory()->active()->create([
        'name' => 'Original Style',
        'base_parameters' => LutTransformParameters::neutral()->withChanges(['contrast' => 100])->toArray(),
    ]);

    $project = WizardProject::factory()->create();
    $project->snapshotStyle($style);
    $project->save();

    $style->forceFill([
        'name' => 'Edited Style',
        'base_parameters' => LutTransformParameters::neutral()->withChanges(['contrast' => -100])->toArray(),
    ])->save();
    $style->delete();

    $project->refresh();

    expect($project->style_name_snapshot)->toBe('Original Style')
        ->and($project->style_snapshot['base_parameters']['contrast'])->toBe(100)
        ->and($project->wizard_style_id)->toBeNull();
});
