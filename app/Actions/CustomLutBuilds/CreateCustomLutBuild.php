<?php

namespace App\Actions\CustomLutBuilds;

use App\Enums\CustomLutBuildStatus;
use App\Enums\LutTransformVersion;
use App\Jobs\GenerateCustomLutBuild;
use App\Models\CustomLutBuild;
use App\Models\User;
use App\Models\WizardProject;
use App\Services\CustomLutBuilds\CustomLutBuildFingerprint;
use App\Services\CustomLutBuilds\PackageDocumentSnapshot;
use App\Services\CustomLutBuilds\PackageNameGenerator;
use App\Services\CustomLutBuilds\ResolvePackageDocuments;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateCustomLutBuild
{
    public function __construct(
        private readonly PackageNameGenerator $packageNames,
        private readonly ResolvePackageDocuments $documents,
        private readonly CustomLutBuildFingerprint $fingerprint,
    ) {}

    public function handle(
        User $user,
        WizardProject $project,
        int $expectedRevision,
        string $expectedParametersHash,
        string $buildRequestId,
    ): CustomLutBuild {
        $build = DB::transaction(function () use ($user, $project, $expectedRevision, $expectedParametersHash, $buildRequestId): CustomLutBuild {
            $lockedProject = WizardProject::query()
                ->whereKey($project->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertProjectCanBuild($user, $lockedProject, $expectedRevision, $expectedParametersHash);

            $existingByRequest = CustomLutBuild::query()
                ->where('wizard_project_id', $lockedProject->id)
                ->where('build_request_id', $buildRequestId)
                ->lockForUpdate()
                ->first();

            if ($existingByRequest instanceof CustomLutBuild) {
                return $existingByRequest->loadMissing(['files']);
            }

            $buildId = (string) Str::ulid();
            $packageName = $this->packageNames->make($lockedProject->name, $buildId);
            $documents = $this->documents->handle();
            $fingerprint = $this->fingerprint->make($lockedProject, $packageName, $documents);

            $existingByFingerprint = CustomLutBuild::query()
                ->with('packageFile')
                ->where('wizard_project_id', $lockedProject->id)
                ->where('build_fingerprint', $fingerprint)
                ->whereIn('status', [
                    CustomLutBuildStatus::Queued->value,
                    CustomLutBuildStatus::Processing->value,
                    CustomLutBuildStatus::Ready->value,
                ])
                ->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->lockForUpdate()
                ->latest()
                ->first();

            if ($existingByFingerprint instanceof CustomLutBuild && ($existingByFingerprint->status !== CustomLutBuildStatus::Ready || $existingByFingerprint->packageFile?->existsOnPrivateStorage())) {
                return $existingByFingerprint->loadMissing('files');
            }

            $this->assertBuildLimits($user, $lockedProject);

            $expiresAt = now()->addDays((int) config('custom-lut-builds.build_expiration_days', 7));

            if ($lockedProject->expires_at->lessThan($expiresAt)) {
                $expiresAt = $lockedProject->expires_at;
            }

            $parameters = LutTransformParameters::fromArray($lockedProject->parameters);

            return CustomLutBuild::query()->create([
                'id' => $buildId,
                'user_id' => $user->id,
                'wizard_project_id' => $lockedProject->id,
                'project_name_snapshot' => $lockedProject->name,
                'style_name_snapshot' => $lockedProject->style_name_snapshot,
                'package_stem' => $packageName->stem,
                'project_revision' => $lockedProject->revision,
                'parameters' => $parameters->toArray(),
                'parameters_hash' => $parameters->hash(),
                'build_fingerprint' => $fingerprint,
                'build_request_id' => $buildRequestId,
                'disk' => (string) config('custom-lut-builds.private_disk', 'private'),
                'transform_version' => $lockedProject->transform_version->value,
                'generator_version' => (string) config('custom-lut-builds.generator_version'),
                'package_schema_version' => (string) config('custom-lut-builds.package_schema_version'),
                'status' => CustomLutBuildStatus::Queued,
                'sale_ready' => false,
                'contains_draft_documents' => $documents->containsDraftDocuments(),
                'is_current' => false,
                'zip_validation_completed' => false,
                'parity_validation_passed' => false,
                'ffmpeg_validation_passed' => ! (bool) config('custom-lut-builds.ffmpeg_validation_enabled', true),
                'license_version' => $documents->license->version,
                'license_template_hash' => $documents->license->contentHash,
                'guide_version' => $documents->guide->version,
                'guide_template_hash' => $documents->guide->contentHash,
                'license_document_snapshot' => $this->documentSnapshot($documents->license),
                'guide_document_snapshot' => $this->documentSnapshot($documents->guide),
                'expires_at' => $expiresAt,
            ]);
        }, attempts: 3);

        if ($build->isQueued()) {
            GenerateCustomLutBuild::dispatch($build->id)
                ->onQueue((string) config('custom-lut-builds.queue', 'images'))
                ->afterCommit();
        }

        return $build->loadMissing(['files']);
    }

    private function assertProjectCanBuild(User $user, WizardProject $project, int $expectedRevision, string $expectedParametersHash): void
    {
        if (! (bool) config('custom-lut-builds.enabled', true)) {
            throw ValidationException::withMessages([
                'build' => 'Custom LUT package preparation is currently unavailable.',
            ]);
        }

        if (! $user->hasVerifiedEmail() || $user->is_suspended || ! $project->belongsToUser($user) || $project->isExpired()) {
            abort(404);
        }

        if ($project->getRawOriginal('transform_version') !== LutTransformVersion::V1->value) {
            throw ValidationException::withMessages([
                'build' => 'This project transform version is not supported for package generation.',
            ]);
        }

        if (trim($project->name) === '' || preg_match('/[\x00-\x1F\x7F]/', $project->name) === 1) {
            throw ValidationException::withMessages([
                'name' => 'Give this project a valid package name before preparing the package.',
            ]);
        }

        if ($project->revision !== $expectedRevision || $project->parameters_hash !== $expectedParametersHash) {
            throw new HttpResponseException(response()->json([
                'message' => 'Your project changed before package preparation started.',
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'revision' => $project->revision,
                    'parameters_hash' => $project->parameters_hash,
                    'updated_at' => $project->updated_at?->toISOString(),
                ],
            ], 409));
        }
    }

    private function assertBuildLimits(User $user, WizardProject $project): void
    {
        $projectLimit = (int) config('custom-lut-builds.maximum_builds_per_project_per_hour', 5);
        $userLimit = (int) config('custom-lut-builds.maximum_builds_per_user_per_day', 20);

        if ($projectLimit > 0 && $project->customLutBuilds()->where('created_at', '>=', now()->subHour())->count() >= $projectLimit) {
            throw ValidationException::withMessages([
                'build' => 'You have prepared this LUT too many times recently. Please wait before trying again.',
            ]);
        }

        if ($userLimit > 0 && $user->customLutBuilds()->where('created_at', '>=', now()->subDay())->count() >= $userLimit) {
            throw ValidationException::withMessages([
                'build' => 'You have reached today\'s Custom LUT package preparation limit.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function documentSnapshot(PackageDocumentSnapshot $snapshot): array
    {
        return [
            'id' => $snapshot->id,
            'kind' => $snapshot->kind->value,
            'status' => $snapshot->status->value,
            'version' => $snapshot->version,
            'title' => $snapshot->title,
            'body' => $snapshot->body,
            'is_current' => $snapshot->isCurrent,
            'content_hash' => $snapshot->contentHash,
        ];
    }
}
