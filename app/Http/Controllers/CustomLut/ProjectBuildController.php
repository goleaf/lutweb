<?php

namespace App\Http\Controllers\CustomLut;

use App\Actions\CustomLutBuilds\CreateCustomLutBuild;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomLut\PrepareCustomLutBuildRequest;
use App\Models\CustomLutBuild;
use App\Models\User;
use App\Models\WizardProject;
use App\Services\CustomLutBuilds\DeleteCustomLutBuild;
use App\Services\LutWizard\WizardProjectPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ProjectBuildController extends Controller
{
    public function store(
        PrepareCustomLutBuildRequest $request,
        WizardProject $wizardProject,
        CreateCustomLutBuild $createBuild,
        WizardProjectPresenter $presenter,
    ): JsonResponse {
        Gate::authorize('build', $wizardProject);
        $user = $request->user();
        abort_unless($user instanceof User, HttpResponse::HTTP_FORBIDDEN);

        $build = $createBuild->handle(
            user: $user,
            project: $wizardProject,
            expectedRevision: (int) $request->validated('expected_revision'),
            expectedParametersHash: (string) $request->validated('expected_parameters_hash'),
            buildRequestId: (string) $request->validated('build_request_id'),
        );

        return response()->json([
            'build' => $presenter->build($build->loadMissing('files')),
        ]);
    }

    public function show(WizardProject $wizardProject, CustomLutBuild $customLutBuild, WizardProjectPresenter $presenter): JsonResponse
    {
        $this->assertBuildBelongsToProject($wizardProject, $customLutBuild);
        Gate::authorize('view', $customLutBuild);

        return response()->json([
            'build' => $presenter->build($customLutBuild->loadMissing('files')),
        ]);
    }

    public function destroy(WizardProject $wizardProject, CustomLutBuild $customLutBuild, DeleteCustomLutBuild $deleteBuild): JsonResponse
    {
        $this->assertBuildBelongsToProject($wizardProject, $customLutBuild);
        Gate::authorize('delete', $customLutBuild);

        $deleteBuild->delete($customLutBuild);

        return response()->json([
            'deleted' => true,
        ]);
    }

    private function assertBuildBelongsToProject(WizardProject $wizardProject, CustomLutBuild $customLutBuild): void
    {
        abort_unless($customLutBuild->wizard_project_id === $wizardProject->id, HttpResponse::HTTP_NOT_FOUND);
    }
}
