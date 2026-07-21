<?php

namespace App\Http\Controllers\CustomLut;

use App\Actions\LutWizard\SelectWizardStyle;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomLut\SelectWizardStyleRequest;
use App\Models\WizardProject;
use App\Services\LutWizard\WizardProjectPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProjectStyleController extends Controller
{
    public function store(
        SelectWizardStyleRequest $request,
        WizardProject $wizardProject,
        SelectWizardStyle $selectStyle,
        WizardProjectPresenter $presenter,
    ): JsonResponse {
        Gate::authorize('update', $wizardProject);

        $project = $selectStyle->handle(
            $wizardProject,
            $request->user(),
            (int) $request->validated('expected_revision'),
            (string) $request->validated('mutation_id'),
            $request->validated('style_id'),
        );

        return response()->json([
            'project' => $presenter->project($project),
            'variants' => [],
        ]);
    }
}
