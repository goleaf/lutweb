<?php

namespace App\Actions\LutWizard;

use App\Models\User;
use App\Models\WizardProject;
use App\Models\WizardProjectVariant;
use App\Services\LutWizard\WizardProjectMutator;

class SelectWizardVariation
{
    public function __construct(
        private readonly WizardProjectMutator $mutator,
    ) {}

    public function handle(
        WizardProject $project,
        WizardProjectVariant $variant,
        User $user,
        int $expectedRevision,
        string $mutationId,
    ): WizardProject {
        if ($variant->wizard_project_id !== $project->id || $variant->generation !== $project->variation_generation) {
            abort(404);
        }

        return $this->mutator->mutate(
            $project,
            $user,
            $expectedRevision,
            $mutationId,
            function (WizardProject $lockedProject) use ($variant): void {
                $currentVariant = $lockedProject->variants()
                    ->whereKey($variant->id)
                    ->where('generation', $lockedProject->variation_generation)
                    ->firstOrFail();

                $lockedProject->setParameters($currentVariant->parametersValue());
                $lockedProject->variants()->update(['selected_at' => null]);
                $currentVariant->forceFill(['selected_at' => now()])->save();
            },
        );
    }
}
