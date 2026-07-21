<?php

namespace App\Services\LutWizard;

use App\Enums\CustomLutBuildFileKind;
use App\Enums\DigitalAssetKind;
use App\Models\CustomLutBuild;
use App\Models\CustomLutBuildFile;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\User;
use App\Models\WizardProject;
use App\Models\WizardProjectPhoto;
use App\Models\WizardProjectVariant;
use App\Models\WizardStyle;
use App\Services\Checkout\CustomLutPurchaseEligibility;
use App\Support\Catalog\EurMoney;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class WizardProjectPresenter
{
    public function __construct(
        private readonly CustomLutPurchaseEligibility $customLutPurchaseEligibility,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function editor(WizardProject $project, ?User $user = null): array
    {
        $project->loadMissing([
            'photos' => fn ($query) => $query->orderBy('sort_order'),
            'variants' => fn ($query) => $query->orderBy('position'),
            'latestBuild.files' => fn ($query) => $query->orderBy('sort_order'),
        ]);

        return [
            'project' => $this->project($project),
            'photos' => $project->photos->map(fn (WizardProjectPhoto $photo): array => $this->photo($project, $photo))->values()->all(),
            'variants' => $project->variants->map(fn (WizardProjectVariant $variant): array => $this->variant($variant, $project->parameters_hash))->values()->all(),
            'build' => $project->latestBuild instanceof CustomLutBuild ? $this->build($project->latestBuild, $project, $user) : null,
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
            'links' => [
                'prepare_build' => route('custom-lut.builds.store', $project),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(CustomLutBuild $build, ?WizardProject $project = null, ?User $user = null): array
    {
        $build->loadMissing('files');
        $packageFile = $build->files->first(fn (CustomLutBuildFile $file): bool => $file->kind === CustomLutBuildFileKind::PackageZip);
        $routeProject = $project ?? $build->wizardProject;

        return [
            'id' => $build->id,
            'status' => $build->isExpired() ? 'expired' : $build->status->value,
            'project_revision' => $build->project_revision,
            'project_name_snapshot' => $build->project_name_snapshot,
            'package_stem' => $build->package_stem,
            'parameters_hash' => $build->parameters_hash,
            'transform_version' => $build->transform_version,
            'generator_version' => $build->generator_version,
            'sale_ready' => $build->sale_ready,
            'contains_draft_documents' => $build->contains_draft_documents,
            'parity_metrics' => [
                'mean_millionths' => $build->parity_mean_error_millionths,
                'p95_millionths' => $build->parity_p95_error_millionths,
                'p99_millionths' => $build->parity_p99_error_millionths,
                'max_millionths' => $build->parity_max_error_millionths,
            ],
            'created_at' => $build->created_at?->toISOString(),
            'started_at' => $build->started_at?->toISOString(),
            'completed_at' => $build->completed_at?->toISOString(),
            'expires_at' => $build->expires_at?->toISOString(),
            'failure_message' => $build->failure_message,
            'package_size_bytes' => $packageFile instanceof CustomLutBuildFile ? $packageFile->size_bytes : $build->zip_size_bytes,
            'files' => $build->files
                ->sortBy('sort_order')
                ->map(fn (CustomLutBuildFile $file): array => [
                    'kind' => $file->kind->value,
                    'display_name' => $file->safe_download_name ?? $file->original_name,
                    'size_bytes' => $file->size_bytes,
                    'short_checksum' => $file->sha256 === null ? null : substr($file->sha256, 0, 12),
                ])
                ->values()
                ->all(),
            'commerce' => $this->commerce($build, $routeProject, $user),
            'links' => [
                'status' => $routeProject instanceof WizardProject ? route('custom-lut.builds.show', [$routeProject, $build]) : null,
                'delete' => $routeProject instanceof WizardProject ? route('custom-lut.builds.destroy', [$routeProject, $build]) : null,
            ],
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
                    'revision' => $project->revision,
                    'parameters_hash' => $project->parameters_hash,
                    'latest_build' => $project->latestBuild instanceof CustomLutBuild ? $this->build($project->latestBuild, $project) : null,
                    'continue_url' => route('custom-lut.show', $project),
                    'prepare_build_url' => route('custom-lut.builds.store', $project),
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

    /**
     * @return array<string, mixed>
     */
    private function commerce(CustomLutBuild $build, ?WizardProject $project, ?User $user): array
    {
        if (! $user instanceof User) {
            return $this->commerceUnavailable();
        }

        $result = $this->customLutPurchaseEligibility->check($build, $user);
        $entitlement = $result->state === 'owned'
            ? $this->activeCustomLutEntitlement($build, $user)
            : null;
        $priceCents = null;

        if ($result->settings !== null) {
            $priceCents = $result->settings->price_cents;
        } elseif ($result->order !== null) {
            $priceCents = $result->order->total_cents;
        } elseif ($entitlement instanceof Entitlement && $entitlement->order !== null) {
            $priceCents = $entitlement->order->total_cents;
        }

        $mayOpenCheckout = in_array($result->state, ['eligible', 'resume'], true)
            && $project instanceof WizardProject;

        return [
            'state' => $result->state,
            'message' => $result->message,
            'price_cents' => $priceCents,
            'price' => $priceCents === null || $priceCents <= 0 ? null : 'EUR '.EurMoney::formatCents($priceCents),
            'currency' => 'EUR',
            'checkout_url' => $mayOpenCheckout ? route('custom-lut.checkout.show', [$project, $build]) : null,
            'purchased_url' => $entitlement instanceof Entitlement ? route('account.custom-luts.purchased.show', $entitlement) : null,
            'download_url' => $entitlement instanceof Entitlement && $entitlement->isActive() ? route('account.custom-luts.download', $entitlement) : null,
            'order_url' => $result->order instanceof Order ? route('account.orders.show', $result->order) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commerceUnavailable(): array
    {
        return [
            'state' => 'unavailable',
            'message' => null,
            'price_cents' => null,
            'price' => null,
            'currency' => 'EUR',
            'checkout_url' => null,
            'purchased_url' => null,
            'download_url' => null,
            'order_url' => null,
        ];
    }

    private function activeCustomLutEntitlement(CustomLutBuild $build, User $user): ?Entitlement
    {
        return Entitlement::query()
            ->with(['order', 'orderItem'])
            ->where('user_id', $user->id)
            ->where('digital_asset_kind', DigitalAssetKind::CustomLutBuild->value)
            ->where('custom_lut_build_id', $build->id)
            ->active()
            ->latest('granted_at')
            ->first();
    }
}
