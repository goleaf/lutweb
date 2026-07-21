<?php

namespace App\Http\Controllers\CustomLut;

use App\Actions\LutWizard\StoreWizardProjectPhoto;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomLut\StoreWizardProjectPhotoRequest;
use App\Models\User;
use App\Models\WizardProject;
use App\Models\WizardProjectPhoto;
use App\Services\LutWizard\DeleteWizardProjectPhoto;
use App\Services\LutWizard\WizardProjectPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ProjectPhotoController extends Controller
{
    public function store(
        StoreWizardProjectPhotoRequest $request,
        WizardProject $wizardProject,
        StoreWizardProjectPhoto $storePhoto,
        WizardProjectPresenter $presenter,
    ): JsonResponse {
        Gate::authorize('update', $wizardProject);

        $file = $request->file('photo');
        abort_unless($file !== null, 422);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $photo = $storePhoto->handle($wizardProject, $user, $file);

        return response()->json([
            'photo' => $presenter->photo($wizardProject, $photo),
        ], 201);
    }

    public function destroy(
        WizardProject $wizardProject,
        WizardProjectPhoto $wizardProjectPhoto,
        DeleteWizardProjectPhoto $deletePhoto,
    ): Response {
        abort_unless($wizardProjectPhoto->wizard_project_id === $wizardProject->id, 404);
        Gate::authorize('delete', $wizardProjectPhoto);

        $deletePhoto->delete($wizardProjectPhoto);

        return response()->noContent();
    }
}
