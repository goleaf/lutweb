<?php

namespace App\Services\LutWizard;

use App\Models\WizardProject;
use App\Models\WizardProjectPhoto;
use App\Models\WizardProjectVariant;
use App\Models\WizardStyle;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class WizardProjectPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function editor(WizardProject $project): array
    {
        $project->loadMissing([
            'photos' => fn ($query) => $query->orderBy('sort_order'),
            'variants' => fn ($query) => $query->orderBy('position'),
        ]);

        return [
            'project' => $this->project($project),
            'photos' => $project->photos->map(fn (WizardProjectPhoto $photo): array => $this->photo($project, $photo))->values()->all(),
            'variants' => $project->variants->map(fn (WizardProjectVariant $variant): array => $this->variant($variant, $project->parameters_hash))->values()->all(),
            'styles' => WizardStyle::query()
                ->where('is_active', true)
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (WizardStyle $style): array => $this->style($style))
                ->all(),
            'schema' => LutTransformParameters::schema(),
            'config' => [
                'maximum_photo_count' => min(3, (int) config('lut-wizard.maximum_photos_per_project', 3)),
                'preview_lut_size' => (int) config('lut-wizard.preview_lut_size', 33),
                'cpu_fallback_maximum_edge' => (int) config('lut-wizard.cpu_fallback_maximum_edge', 1024),
                'autosave_debounce_ms' => (int) config('lut-wizard.autosave_debounce_ms', 800),
                'maximum_local_undo_entries' => (int) config('lut-wizard.maximum_local_undo_entries', 50),
                'variation_count' => (int) config('lut-wizard.variation_count', 4),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function project(WizardProject $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'status' => $project->isExpired() ? 'expired' : $project->status->value,
            'transform_version' => $project->transform_version->value,
            'revision' => $project->revision,
            'parameters' => $project->parameters,
            'parameters_hash' => $project->parameters_hash,
            'selected_style' => $project->style_name_snapshot === null ? null : [
                'id' => $project->wizard_style_id,
                'name' => $project->style_name_snapshot,
            ],
            'variation_generation' => $project->variation_generation,
            'created_at' => $project->created_at?->toISOString(),
            'updated_at' => $project->updated_at?->toISOString(),
            'expires_at' => $project->expires_at->toISOString(),
            'maximum_photo_count' => min(3, (int) config('lut-wizard.maximum_photos_per_project', 3)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function style(WizardStyle $style): array
    {
        return [
            'id' => $style->id,
            'name' => $style->name,
            'slug' => $style->slug,
            'description' => $style->description,
            'transform_version' => $style->transform_version->value,
            'base_parameters' => $style->base_parameters,
            'minimum_parameters' => $style->minimum_parameters,
            'maximum_parameters' => $style->maximum_parameters,
            'variation_amounts' => $style->variation_amounts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function photo(WizardProject $project, WizardProjectPhoto $photo): array
    {
        return [
            'id' => $photo->id,
            'status' => $photo->isExpired() ? 'expired' : $photo->status->value,
            'original_name' => $photo->original_name,
            'preview_width' => $photo->preview_width,
            'preview_height' => $photo->preview_height,
            'sort_order' => $photo->sort_order,
            'expires_at' => $photo->expires_at->toISOString(),
            'preview_url' => $photo->isReady() ? $this->previewUrl($project, $photo) : null,
            'failure_message' => $photo->failure_message,
            'delete_url' => route('custom-lut.photos.destroy', [$project, $photo]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function variant(WizardProjectVariant $variant, string $projectParametersHash): array
    {
        return [
            'id' => $variant->id,
            'position' => $variant->position,
            'mode' => $variant->mode->value,
            'parameters' => $variant->parameters,
            'parameters_hash' => $variant->parameters_hash,
            'selected' => $variant->selected_at !== null && $variant->parameters_hash === $projectParametersHash,
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, WizardProject>  $projects
     * @return array<string, mixed>
     */
    public function accountProjects(LengthAwarePaginator $projects): array
    {
        return [
            'data' => $projects->getCollection()
                ->map(fn (WizardProject $project): array => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'style_name' => $project->style_name_snapshot ?? 'Neutral',
                    'updated_at' => $project->updated_at?->toISOString(),
                    'expires_at' => $project->expires_at->toISOString(),
                    'active_photo_count' => $project->photos_count,
                    'parameters_hash' => $project->parameters_hash,
                    'continue_url' => route('custom-lut.show', $project),
                    'duplicate_url' => route('custom-lut.duplicate', $project),
                    'delete_url' => route('custom-lut.destroy', $project),
                ])
                ->all(),
            'links' => [
                'first' => $projects->url(1),
                'last' => $projects->url($projects->lastPage()),
                'prev' => $projects->previousPageUrl(),
                'next' => $projects->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
            ],
        ];
    }

    private function previewUrl(WizardProject $project, WizardProjectPhoto $photo): string
    {
        $expiresAt = Carbon::createFromTimestamp(min(
            now()->addMinutes((int) config('lut-wizard.signed_preview_url_lifetime', 10))->timestamp,
            $photo->expires_at->timestamp,
        ));

        return URL::temporarySignedRoute('custom-lut.photos.preview', $expiresAt, [
            'wizardProject' => $project->id,
            'wizardProjectPhoto' => $photo->id,
        ]);
    }
}
