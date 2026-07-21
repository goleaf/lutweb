<?php

namespace App\Actions\LutWizard;

use App\Models\User;
use App\Models\WizardProject;
use App\Models\WizardStyle;
use App\Services\LutWizard\WizardProjectMutator;
use Illuminate\Validation\ValidationException;

class SelectWizardStyle
{
    public function __construct(private readonly WizardProjectMutator $mutator) {}

    public function handle(
        WizardProject $project,
        User $user,
        int $expectedRevision,
        string $mutationId,
        ?string $styleId,
    ): WizardProject {
        return $this->mutator->mutate(
            $project,
            $user,
            $expectedRevision,
            $mutationId,
            function (WizardProject $project) use ($styleId): void {
                if ($styleId === null || $styleId === '') {
                    $project->clearStyleSnapshot();
                } else {
                    $style = WizardStyle::query()->whereKey($styleId)->first();

                    if (! $style instanceof WizardStyle || ! $style->isSelectable()) {
                        throw ValidationException::withMessages([
                            'style_id' => 'The selected style is not available.',
                        ]);
                    }

                    $project->snapshotStyle($style);
                }

                $project->variants()->delete();
            },
        );
    }
}
