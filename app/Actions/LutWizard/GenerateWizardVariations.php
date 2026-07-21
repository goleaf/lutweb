<?php

namespace App\Actions\LutWizard;

use App\Enums\WizardVariationMode;
use App\Models\User;
use App\Models\WizardProject;
use App\Models\WizardProjectVariant;
use App\Services\LutWizard\WizardProjectMutator;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GenerateWizardVariations
{
    public function __construct(
        private readonly WizardProjectMutator $mutator,
    ) {}

    /**
     * @return Collection<int, WizardProjectVariant>
     */
    public function handle(
        WizardProject $project,
        User $user,
        int $expectedRevision,
        string $mutationId,
        WizardVariationMode $mode,
    ): Collection {
        $this->mutator->mutate(
            $project,
            $user,
            $expectedRevision,
            $mutationId,
            function (WizardProject $lockedProject) use ($mode): void {
                $generationSeed = bin2hex(random_bytes(32));
                $nextGeneration = $lockedProject->variation_generation + 1;
                $variants = $this->buildVariants($lockedProject, $mode, $nextGeneration, $generationSeed);

                $lockedProject->variants()->delete();
                $lockedProject->variation_generation = $nextGeneration;

                foreach ($variants as $variant) {
                    $lockedProject->variants()->create($variant);
                }
            },
        );

        return WizardProjectVariant::query()
            ->where('wizard_project_id', $project->id)
            ->orderBy('position')
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildVariants(WizardProject $project, WizardVariationMode $mode, int $generation, string $generationSeed): array
    {
        $center = $mode === WizardVariationMode::Fresh
            ? $this->freshCenter($project)
            : $project->currentParameters();

        $minimums = $this->snapshotParameters($project, 'minimum_parameters')?->toArray() ?? LutTransformParameters::minimums();
        $maximums = $this->snapshotParameters($project, 'maximum_parameters')?->toArray() ?? LutTransformParameters::maximums();
        $amounts = $this->variationAmounts($project);
        $centerHash = $center->hash();
        $hashes = [];
        $variants = [];
        $count = (int) config('lut-wizard.variation_count', 4);

        for ($position = 1; $position <= $count; $position++) {
            $variant = null;

            for ($attempt = 1; $attempt <= 20; $attempt++) {
                $parameters = $this->parametersForPosition(
                    $center,
                    $minimums,
                    $maximums,
                    $amounts,
                    $generationSeed,
                    $generation,
                    $position,
                    $attempt,
                );

                $hash = $parameters->hash();

                if ($hash !== $centerHash && ! in_array($hash, $hashes, true)) {
                    $hashes[] = $hash;
                    $variant = [
                        'generation' => $generation,
                        'position' => $position,
                        'mode' => $mode,
                        'seed' => hash('sha256', $generationSeed.'|variant|'.$position),
                        'parameters' => $parameters->toArray(),
                        'parameters_hash' => $hash,
                    ];

                    break;
                }
            }

            if ($variant === null) {
                throw ValidationException::withMessages([
                    'mode' => 'This style range is too narrow to generate four unique variations.',
                ]);
            }

            $variants[] = $variant;
        }

        return $variants;
    }

    private function freshCenter(WizardProject $project): LutTransformParameters
    {
        return $this->snapshotParameters($project, 'base_parameters') ?? LutTransformParameters::neutral();
    }

    /**
     * @return array<string, int>
     */
    private function variationAmounts(WizardProject $project): array
    {
        $snapshot = $project->style_snapshot;
        $amounts = is_array($snapshot) && isset($snapshot['variation_amounts']) && is_array($snapshot['variation_amounts'])
            ? $snapshot['variation_amounts']
            : null;

        if ($amounts !== null) {
            $canonical = [];

            foreach (LutTransformParameters::keys() as $key) {
                $canonical[$key] = max(0, (int) ($amounts[$key] ?? 0));
            }

            return $canonical;
        }

        $defaults = [];

        foreach (LutTransformParameters::keys() as $key) {
            $defaults[$key] = match ($key) {
                'intensity' => 0,
                'exposure' => 25,
                'shadow_hue', 'highlight_hue' => 180,
                default => 90,
            };
        }

        return $defaults;
    }

    /**
     * @param  array<string, int>  $minimums
     * @param  array<string, int>  $maximums
     * @param  array<string, int>  $amounts
     */
    private function parametersForPosition(
        LutTransformParameters $center,
        array $minimums,
        array $maximums,
        array $amounts,
        string $seed,
        int $generation,
        int $position,
        int $attempt,
    ): LutTransformParameters {
        $changes = [];
        $centerValues = $center->toArray();

        foreach (LutTransformParameters::keys() as $key) {
            $amount = min($amounts[$key], LutTransformParameters::isHueKey($key) ? 1800 : LutTransformParameters::span($key));

            if ($amount <= 0) {
                continue;
            }

            $random = $this->randomSigned($seed, $generation, $position, $key, $attempt);
            $value = $centerValues[$key] + (int) round($random * $amount);

            if (LutTransformParameters::isHueKey($key)) {
                $changes[$key] = ($value % 3600 + 3600) % 3600;

                continue;
            }

            $changes[$key] = max($minimums[$key], min($maximums[$key], $value));
        }

        return $center->withChanges($changes);
    }

    private function randomSigned(string $seed, int $generation, int $position, string $key, int $attempt): float
    {
        $hash = hash('sha256', $seed.'|'.$generation.'|'.$position.'|'.$key.'|'.$attempt);
        $integer = hexdec(substr($hash, 0, 12));
        $unit = $integer / 0xFFFFFFFFFFFF;

        return ($unit * 2.0) - 1.0;
    }

    private function snapshotParameters(WizardProject $project, string $key): ?LutTransformParameters
    {
        $snapshot = $project->style_snapshot;

        if (! is_array($snapshot) || ! isset($snapshot[$key]) || ! is_array($snapshot[$key])) {
            return null;
        }

        return LutTransformParameters::fromArray($snapshot[$key]);
    }
}
