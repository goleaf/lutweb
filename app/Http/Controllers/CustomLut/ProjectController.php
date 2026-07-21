<?php

namespace App\Http\Controllers\CustomLut;

use App\Actions\LutWizard\CreateWizardProject;
use App\Actions\LutWizard\DuplicateWizardProject;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WizardProject;
use App\Services\LutWizard\DeleteWizardProject;
use App\Services\LutWizard\WizardProjectPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('CustomLut/Create', [
            'limits' => [
                'maximum_projects' => (int) config('lut-wizard.maximum_active_projects', 10),
                'project_expiration_days' => (int) config('lut-wizard.project_expiration_days', 30),
                'maximum_photos' => min(3, (int) config('lut-wizard.maximum_photos_per_project', 3)),
                'photo_expiration_minutes' => (int) config('lut-wizard.photo_expiration_minutes', 60),
            ],
        ]);
    }

    public function store(CreateWizardProject $createProject): RedirectResponse
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $project = $createProject->handle($user);

        return redirect()->route('custom-lut.show', $project);
    }

    public function show(
        WizardProject $wizardProject,
        WizardProjectPresenter $presenter,
    ): Response {
        Gate::authorize('view', $wizardProject);

        return Inertia::render('CustomLut/Show', $presenter->editor($wizardProject));
    }

    public function destroy(WizardProject $wizardProject, DeleteWizardProject $deleteProject): RedirectResponse
    {
        Gate::authorize('delete', $wizardProject);

        $deleteProject->delete($wizardProject);

        return redirect()->route('account.custom-luts.index');
    }

    public function duplicate(WizardProject $wizardProject, DuplicateWizardProject $duplicateProject): RedirectResponse
    {
        Gate::authorize('duplicate', $wizardProject);

        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $copy = $duplicateProject->handle($wizardProject, $user);

        return redirect()->route('custom-lut.show', $copy);
    }
}
