<?php

namespace App\Actions\LutWizard;

use App\Enums\LutTransformVersion;
use App\Enums\WizardProjectStatus;
use App\Models\User;
use App\Models\WizardProject;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateWizardProject
{
    public function handle(User $user): WizardProject
    {
        if ($user->is_suspended) {
            abort(403);
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

        return DB::transaction(function () use ($user): WizardProject {
            $parameters = LutTransformParameters::neutral();

            return WizardProject::query()->create([
                'user_id' => $user->id,
                'wizard_style_id' => null,
                'name' => 'Untitled LUT',
                'status' => WizardProjectStatus::Draft,
                'transform_version' => LutTransformVersion::V1,
                'style_name_snapshot' => null,
                'style_snapshot' => null,
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
