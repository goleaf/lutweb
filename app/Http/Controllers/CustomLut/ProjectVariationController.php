<?php

namespace App\Http\Controllers\CustomLut;

use App\Actions\LutWizard\GenerateWizardVariations;
use App\Actions\LutWizard\SelectWizardVariation;
use App\Enums\WizardVariationMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomLut\GenerateWizardVariationsRequest;
use App\Http\Requests\CustomLut\SelectWizardVariationRequest;
use App\Models\User;
use App\Models\WizardProject;
use App\Models\WizardProjectVariant;
use App\Services\LutWizard\WizardProjectPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProjectVariationController extends Controller
{
    public function store(
        GenerateWizardVariationsRequest $request,
        WizardProject $wizardProject,
        GenerateWizardVariations $generateVariations,
        WizardProjectPresenter $presenter,
    ): JsonResponse {
        Gate::authorize('update', $wizardProject);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $variants = $generateVariations->handle(
            $wizardProject,
            $user,
            (int) $request->validated('expected_revision'),
            (string) $request->validated('mutation_id'),
            WizardVariationMode::from((string) $request->validated('mode')),
        );

        $wizardProject->refresh();

        return response()->json([
            'project' => $presenter->project($wizardProject),
            'variants' => $variants
                ->map(fn (WizardProjectVariant $variant): array => $presenter->variant($variant, $wizardProject->parameters_hash))
                ->values()
                ->all(),
        ]);
    }

    public function select(
        SelectWizardVariationRequest $request,
        WizardProject $wizardProject,
        WizardProjectVariant $wizardProjectVariant,
        SelectWizardVariation $selectVariation,
        WizardProjectPresenter $presenter,
    ): JsonResponse {
        abort_unless($wizardProjectVariant->wizard_project_id === $wizardProject->id, 404);
        Gate::authorize('select', $wizardProjectVariant);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $project = $selectVariation->handle(
            $wizardProject,
            $wizardProjectVariant,
            $user,
            (int) $request->validated('expected_revision'),
            (string) $request->validated('mutation_id'),
        );

        $project->load(['variants' => fn ($query) => $query->orderBy('position')]);

        return response()->json([
            'project' => $presenter->project($project),
            'variants' => $project->variants
                ->map(fn (WizardProjectVariant $variant): array => $presenter->variant($variant, $project->parameters_hash))
                ->values()
                ->all(),
        ]);
    }
}
