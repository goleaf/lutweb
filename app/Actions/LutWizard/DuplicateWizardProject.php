<?php

namespace App\Actions\LutWizard;

use App\Models\User;
use App\Models\WizardProject;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DuplicateWizardProject
{
    public function handle(WizardProject $project, User $user): WizardProject
    {
        if (! $project->mayBeEditedBy($user)) {
            abort(404);
        }

        $maximum = (int) config('lut-wizard.maximum_active_projects', 10);
        $activeCount = $user->wizardProjects()
            ->nonExpired()
            ->count();

        if ($activeCount >= $maximum) {
            throw ValidationException::withMessages([
                'project' => 'You already have the maximum number of active custom LUT drafts.',
            ]);
        }

        return DB::transaction(function () use ($project, $user): WizardProject {
            $parameters = LutTransformParameters::fromArray($project->parameters);

            return WizardProject::query()->create([
                'user_id' => $user->id,
                'wizard_style_id' => $project->wizard_style_id,
                'name' => mb_substr($project->name.' Copy', 0, 80),
                'status' => $project->status,
                'transform_version' => $project->transform_version,
                'style_name_snapshot' => $project->style_name_snapshot,
                'style_snapshot' => $project->style_snapshot,
                'parameters' => $parameters->toArray(),
                'parameters_hash' => $parameters->hash(),
                'project_seed' => bin2hex(random_bytes(32)),
                'revision' => 1,
                'variation_generation' => 0,
                'expires_at' => now()->addDays((int) config('lut-wizard.project_expiration_days', 30)),
            ]);
        });
    }
}
