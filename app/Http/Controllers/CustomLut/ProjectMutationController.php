<?php

namespace App\Http\Controllers\CustomLut;

use App\Actions\LutWizard\UpdateWizardProject;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomLut\UpdateWizardProjectRequest;
use App\Models\WizardProject;
use App\Services\LutWizard\WizardProjectPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProjectMutationController extends Controller
{
    public function update(
        UpdateWizardProjectRequest $request,
        WizardProject $wizardProject,
        UpdateWizardProject $updateProject,
        WizardProjectPresenter $presenter,
    ): JsonResponse {
        Gate::authorize('update', $wizardProject);

        $project = $updateProject->handle($wizardProject, $request->user(), $request->validated());

        return response()->json([
            'project' => $presenter->project($project),
        ]);
    }
}
